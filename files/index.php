<?php

/**
 * Special file to filter traffic from inbox providers when sending emails to avoid:
 *
 * 1. Fake link clicks from link-crawling spam filters.
 * 2. Fake opens by image pre-fetching.
 *
 * This file runs before WordPress is loaded.
 */

// Check if in the correct directory.
if ( ! file_exists( __DIR__ . '/../wp-config.php' ) ) {
	die();
}

### REPLACE ###
const GH_LOGO_SRC                   = '';
const GH_MANAGED_PAGE_ROOT          = 'gh';
const GH_DOCUMENT_TITLE             = 'Traffic Filter';
const GH_REDIRECT_DELAY             = 3;
const GH_VERIFIED_PARAM             = '__verified';
const GH_CLIENT_IP_HEADERS          = [ 'CF-Connecting-IP', 'True-Client-IP', 'X-Real-IP', 'X-Forwarded-For' ];
const GH_AUTOMATIC_REDIRECTION_TEXT = 'You will be redirected in %s seconds.';
const GH_CLICK_TO_CONTINUE_TEXT     = 'Or click <a href="%1$s">here</a> to continue to %2$s.';
### END REPLACE ###

/**
 * How long a fingerprint should be treated as bot traffic.
 */
const GH_BOT_FINGERPRINT_TTL = 2 * 60;

/**
 * Directory containing temporary bot fingerprints.
 */
const GH_BOT_FINGERPRINT_DIR = __DIR__ . '/bot-fingerprints';

/**
 * Get the current request user agent.
 *
 * @return string
 */
function groundhogg_get_user_agent(): string {
	return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Get the IP of the visitor making the current request.
 *
 * Behind a CDN or reverse proxy, REMOTE_ADDR is the proxy's IP, which is
 * shared by many unrelated visitors. To avoid flagging real users because a
 * bot happened to come through the same edge node, prefer the client IP
 * reported by the proxy (see GH_CLIENT_IP_HEADERS), and only fall back to
 * REMOTE_ADDR when no usable header is present.
 *
 * @return string
 */
function groundhogg_get_current_ip(): string {

	foreach ( GH_CLIENT_IP_HEADERS as $header ) {

		$key = 'HTTP_' . strtoupper( str_replace( '-', '_', $header ) );

		if ( empty( $_SERVER[ $key ] ) ) {
			continue;
		}

		// X-Forwarded-For style lists are "client, proxy1, proxy2"
		foreach ( explode( ',', (string) $_SERVER[ $key ] ) as $candidate ) {

			$candidate = trim( $candidate );

			// Only accept public addresses, a private one is just another hop
			if ( filter_var(
				$candidate,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			) ) {
				return $candidate;
			}
		}
	}

	$remote = trim( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );

	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
}

/**
 * Create a fingerprint for the current request.
 *
 * @return string
 */
function groundhogg_get_request_fingerprint(): string {

	return hash(
		'sha256',
		implode(
			'|',
			[
				groundhogg_get_current_ip(),
				groundhogg_get_user_agent(),
			]
		)
	);
}

/**
 * Ensure the fingerprint directory exists.
 *
 * @return bool
 */
function groundhogg_ensure_fingerprint_dir(): bool {

	if ( is_dir( GH_BOT_FINGERPRINT_DIR ) ) {
		return true;
	}

	return @mkdir( GH_BOT_FINGERPRINT_DIR, 0755, true );
}

/**
 * Get the path for a fingerprint file.
 *
 * @param string $fingerprint
 *
 * @return string
 */
function groundhogg_get_fingerprint_file( string $fingerprint = '' ): string {

	if ( ! $fingerprint ) {
		$fingerprint = groundhogg_get_request_fingerprint();
	}

	return GH_BOT_FINGERPRINT_DIR . '/' . $fingerprint;
}

/**
 * Mark the current fingerprint as bot traffic.
 *
 * The file contents are irrelevant.
 * The file modification time represents when the bot was last seen.
 *
 * @return void
 */
function groundhogg_store_bot_fingerprint(): void {

	if ( ! groundhogg_ensure_fingerprint_dir() ) {
		return;
	}

	$file = groundhogg_get_fingerprint_file();

	if ( is_file( $file ) ) {
		@touch( $file );

		return;
	}

	/**
	 * Create an empty file.
	 */
	@file_put_contents(
		$file,
		'',
		LOCK_EX
	);
}

/**
 * Check whether the current fingerprint was recently identified
 * as bot traffic.
 *
 * Expired files are ignored and can be removed later by cron.
 *
 * @return bool
 */
function groundhogg_is_bot_fingerprint(): bool {

	$file = groundhogg_get_fingerprint_file();

	if ( ! is_file( $file ) ) {
		return false;
	}

	$modified = @filemtime( $file );

	if ( ! $modified ) {
		return false;
	}

	return $modified > time() - GH_BOT_FINGERPRINT_TTL;
}

/**
 * Remove the current bot fingerprint.
 *
 * @return void
 */
function groundhogg_remove_bot_fingerprint(): void {

	$file = groundhogg_get_fingerprint_file();

	if ( is_file( $file ) ) {
		@unlink( $file );
	}
}

/**
 * Add a query parameter to a URL.
 *
 * @param string $url
 * @param string $key
 * @param string $value
 *
 * @return string
 */
function groundhogg_add_query_arg(
	string $url,
	string $key,
	string $value
): string {

	$separator = strpos( $url, '?' ) === false ? '?' : '&';

	return $url
	       . $separator
	       . rawurlencode( $key )
	       . '='
	       . rawurlencode( $value );
}

/**
 * Basic HTML escaping without WordPress.
 *
 * @param string $text
 *
 * @return string
 */
function groundhogg_esc_html( string $text ): string {

	return htmlspecialchars(
		$text,
		ENT_QUOTES | ENT_SUBSTITUTE,
		'UTF-8'
	);
}

/**
 * Escape a URL for use inside an HTML attribute.
 *
 * @param string $url
 *
 * @return string
 */
function groundhogg_esc_url_attr( string $url ): string {
	return groundhogg_esc_html( $url );
}

/**
 * Show an intermediate page which requires browser-side JavaScript
 * before the tracking request is allowed through.
 *
 * @param string $redirect_to
 *
 * @return void
 */
function groundhogg_show_redirect_page( string $redirect_to = '' ): void {

	if ( ! $redirect_to ) {
		$redirect_to = $_SERVER['REQUEST_URI'] ?? '/';
	}

	$redirect_to = groundhogg_add_query_arg(
		$redirect_to,
		GH_VERIFIED_PARAM,
		'true'
	);

	$redirect_json = json_encode(
		$redirect_to,
		JSON_HEX_TAG
		| JSON_HEX_AMP
		| JSON_HEX_APOS
		| JSON_HEX_QUOT
		| JSON_UNESCAPED_SLASHES
	);

	if ( $redirect_json === false ) {
		http_response_code( 400 );
		die();
	}

	$host = $_SERVER['HTTP_HOST'] ?? '';

	http_response_code( 200 );

	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Pragma: no-cache' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'X-Content-Type-Options: nosniff' );

	?>
    <!doctype html>
    <html lang="en">
    <head>
        <title><?php echo groundhogg_esc_html( GH_DOCUMENT_TITLE ); ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">

        <style>
            html {
                background-color: #F6F9FB;
                position: initial !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                line-height: 1.6em;
                padding-top: 50px;
            }

            body {
                margin: 0;
                padding: 0 20px;
            }

            img {
                width: 300px;
                max-width: 100%;
                height: auto;
                margin: 50px auto;
                display: block;
            }

            #main {
                max-width: 500px;
                margin: 0 auto;
                padding: 30px;
                box-sizing: border-box;
                font-weight: 400;
                overflow: hidden;
                background: #FFFFFF;
                box-shadow: 5px 5px 30px rgba(24, 45, 70, 0.05);
                border-radius: 5px;
                border: none;
            }

            #main p {
                font-size: 18px;
            }

            #delay {
                font-weight: bold;
            }

            body > p {
                margin: 1.1em 0;
                text-align: center;
                font-size: 14px;
            }
        </style>
    </head>

    <body>

	<?php if ( GH_LOGO_SRC ): ?>
        <img
                id="logo"
                src="<?php echo groundhogg_esc_url_attr( GH_LOGO_SRC ); ?>"
                alt=""
        >
	<?php endif; ?>

    <div id="main">
        <p>
			<?php
			printf(
				GH_AUTOMATIC_REDIRECTION_TEXT,
				sprintf(
					'<span id="delay">%d</span>',
					GH_REDIRECT_DELAY
				)
			);
			?>
        </p>
    </div>

    <p>
		<?php
		printf(
			GH_CLICK_TO_CONTINUE_TEXT,
			groundhogg_esc_url_attr( $redirect_to ),
			groundhogg_esc_html( $host )
		);
		?>
    </p>

    <script>
      (() => {

        const redirectTo = <?php echo $redirect_json; ?>;
        let delay = <?php echo (int) GH_REDIRECT_DELAY; ?>;

        const delayView = document.getElementById('delay');
        const message = document.querySelector('#main p');
        const continueLink = document.querySelector('body > p a');

        let interval;

        const redirect = () => {
          window.location.assign(redirectTo);
        };

        interval = window.setInterval(() => {

          delay--;

          if (delay < 1) {

            window.clearInterval(interval);

            if (message) {
              message.textContent = 'Redirecting you now...';
            }

            redirect();

            return;
          }

          if (delayView) {
            delayView.textContent = String(delay);
          }

        }, 1000);

        if (continueLink) {
          continueLink.addEventListener('click', () => {
            window.clearInterval(interval);
          });
        }

      })();
    </script>

    </body>
    </html>
	<?php

	die();
}

/**
 * Output a 1x1 transparent PNG.
 *
 * @return void
 */
function groundhogg_show_pixel_image(): void {

	http_response_code( 200 );

	header( 'Content-Type: image/png' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

	echo hex2bin(
		'89504e470d0a1a0a'
		. '0000000d494844520000000100000001010300000025db56ca'
		. '00000003504c5445000000a77a3dda'
		. '0000000174524e530040e6d866'
		. '0000000a4944415408d76360000000020001e221bc33'
		. '0000000049454e44ae426082'
	);

	die();
}

/**
 * Check whether the current user agent exactly matches a value.
 *
 * @param string $agent
 *
 * @return bool
 */
function groundhogg_user_agent_is( string $agent ): bool {
	return groundhogg_get_user_agent() === $agent;
}

/**
 * Load WordPress.
 *
 * @return void
 */
function groundhogg_load_wp(): void {
	include __DIR__ . '/../index.php';
}

/**
 * Perform crawler detection before WordPress is loaded.
 *
 * @return void
 */
function groundhogg_check_if_crawler_or_include_index(): void {

	header(
		'X-Groundhogg: /'
		. GH_MANAGED_PAGE_ROOT
		. '/'
	);

	$request = $_SERVER['REQUEST_URI'] ?? '/';

	$managed_root = '/'
	                . trim( GH_MANAGED_PAGE_ROOT, '/' )
	                . '/';

	$click_path = $managed_root . 'c/';
	$open_path  = $managed_root . 'o/';

	$needles = [
		$managed_root . 'tracking/email/',
		$click_path,
		$open_path,
	];

	$is_managed_request = false;

	foreach ( $needles as $needle ) {

		if ( strpos( $request, $needle ) !== false ) {
			$is_managed_request = true;
			break;
		}
	}

	if ( ! $is_managed_request ) {
		groundhogg_load_wp();

		return;
	}

	/**
	 * Determine the tracking function.
	 */
	if ( strpos( $request, $click_path ) !== false ) {

		$function = 'click';

	} elseif ( strpos( $request, $open_path ) !== false ) {

		$function = 'open';

	} else {

		/**
		 * Backwards compatibility with:
		 *
		 * /gh/tracking/email/{function}/...
		 */
		$path = parse_url( $request, PHP_URL_PATH );

		$parts = array_values(
			array_filter(
				explode(
					'/',
					is_string( $path ) ? $path : ''
				)
			)
		);

		$function = $parts[3] ?? '';
	}

	switch ( $function ) {

		case 'open':

			/**
			 * Dummy tracking image honeypot.
			 *
			 * A real recipient should never intentionally request this,
			 * so mark the requester as suspicious.
			 */
			if (
				strpos(
					$request,
					$open_path . 'pixelbot'
				) !== false
			) {
				groundhogg_store_bot_fingerprint();
				groundhogg_show_pixel_image();
			}

			$request_checks = [

				/**
				 * Google Image pre-fetch.
				 */
				static function (): bool {

					return
						groundhogg_user_agent_is(
							'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
							. 'AppleWebKit/537.36 (KHTML, like Gecko) '
							. 'Chrome/42.0.2311.135 Safari/537.36 '
							. 'Edge/12.246 Mozilla/5.0'
						)
						&&
						(
							$_SERVER['HTTP_REFERER'] ?? ''
						) === 'http://mail.google.com/';
				},

				/**
				 * Apple Mail Privacy Protection.
				 */
				static function (): bool {

					return
						groundhogg_user_agent_is( 'Mozilla/5.0' )
						&&
						empty( $_SERVER['HTTP_REFERER'] );
				},

				/**
				 * Open tracking should only be requested via GET.
				 */
				static function (): bool {

					return (
						       $_SERVER['REQUEST_METHOD']
						       ?? ''
					       ) !== 'GET';
				},

				/**
				 * Recently identified crawler fingerprint.
				 */
				static function (): bool {
					return groundhogg_is_bot_fingerprint();
				},
			];

			foreach ( $request_checks as $request_check ) {

				if ( $request_check() ) {
					groundhogg_show_pixel_image();
				}
			}

			break;

		case 'click':

			/**
			 * A real browser successfully completed the intermediate
			 * verification step.
			 */
			if (
				isset( $_GET[ GH_VERIFIED_PARAM ] )
				&&
				$_GET[ GH_VERIFIED_PARAM ] === 'true'
			) {
				groundhogg_remove_bot_fingerprint();
				break;
			}

			/**
			 * Link honeypot.
			 */
			if (
				strpos(
					$request,
					$click_path . 'ruabot'
				) !== false
			) {

				groundhogg_store_bot_fingerprint();

				/**
				 * Never server-side redirect traffic that reached
				 * the honeypot.
				 */
				groundhogg_show_redirect_page(
					$managed_root
				);
			}

			$request_checks = [

				/**
				 * Click tracking should only be requested via GET.
				 */
				static function (): bool {

					return (
						       $_SERVER['REQUEST_METHOD']
						       ?? ''
					       ) !== 'GET';
				},

				/**
				 * Was this IP + UA combination recently identified
				 * by one of our honeypots?
				 */
				static function (): bool {
					return groundhogg_is_bot_fingerprint();
				},
			];

			foreach ( $request_checks as $request_check ) {

				if ( $request_check() ) {
					groundhogg_show_redirect_page();
				}
			}

			break;
	}

	/**
	 * Request wasn't blocked, or browser verification succeeded.
	 */
	groundhogg_load_wp();
}

groundhogg_check_if_crawler_or_include_index();
