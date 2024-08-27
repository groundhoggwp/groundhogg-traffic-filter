<?php

/**
 * Special file to filter traffic from inboxes providers when sending emails to avoid...
 * 1. Fake link clicks from link crawling spam filters
 * 2. Fake opens by image pre-fetching
 *
 * Methods to detect bot activity, fraudulent clicks, and image pre-fetching
 *
 * 1. Too many clicks from a single IP within a time range //todo
 * 2. Request headers match known image pre-fetch user-agents.
 */

// Check if in the correct directory
if ( ! file_exists( __DIR__ . '/../wp-config.php' ) ) {
	die();
}

### REPLACE ###
const GH_LOGO_SRC                   = '';
const GH_MANAGED_PAGE_ROOT          = 'gh';
const GH_DOCUMENT_TITLE             = 'Traffic Filter';
const GH_REDIRECT_DELAY             = 3;
const GH_VERIFIED_PARAM             = '__verified';
const GH_AUTOMATIC_REDIRECTION_TEXT = 'You will be redirected in %s seconds.';
const GH_CLICK_TO_CONTINUE_TEXT     = 'Or click <a href="%1$s">here</a> to continue to %2$s.';
### END REPLACE ###

const GH_USER_AGENT_FILE            = __DIR__ . '/user-agents.txt';
const GH_IPS_FILE                   = __DIR__ . '/ips.txt';

/**
 * Check if a string is in a file
 *
 * @param string $text
 * @param string $filePath
 *
 * @return bool
 */
function groundhogg_in_file( string $text, string $filePath ) {

	$file = fopen( $filePath, 'r' );

	if ( $file === false ) {
		return false;
	}

	while ( ! feof( $file ) ) {
		$line = fgets( $file );
		if ( trim( $line ) == $text ) {
			fclose( $file );

			return true;
		}
	}

	fclose( $file );

	return false;
}

/**
 * Add a string to a file if and only if it is not already present in the file
 *
 * @param string $text
 * @param string $filePath
 *
 * @return void
 */
function groundhog_add_to_file( string $text, string $filePath ) {

	if ( groundhogg_in_file( $text, $filePath ) ) {
		return;
	}

	$file = fopen( $filePath, 'a' );
	fwrite( $file, $text . PHP_EOL );
	fclose( $file );
}

/**
 * Given a string and a file, remove the line from the file
 *
 * @param string $text
 * @param string $filePath
 *
 * @return void
 */
function groundhogg_remove_from_file( string $text, string $filePath ) {

	// Open input file for reading
	$inputFile = fopen( $filePath, 'r' );
	if ( $inputFile === false ) {
		return;
	}

	// Create a temporary file for writing
	$tempFilePath = tempnam( sys_get_temp_dir(), 'user_agents' );
	$tempFile     = fopen( $tempFilePath, 'w' );
	if ( $tempFile === false ) {
		return;
	}

	// Iterate through each line in the input file
	while ( ( $line = fgets( $inputFile ) ) !== false ) {
		// Remove newline character
		$line = trim( $line );
		// Write the line to the temporary file if it's not the user agent to remove
		if ( $line !== $text ) {
			fwrite( $tempFile, $line . PHP_EOL );
		}
	}

	// Close files
	fclose( $inputFile );
	fclose( $tempFile );

	// Rename the temporary file to the original file
	rename( $tempFilePath, $filePath );
}

/**
 * Save the user agent because it's a bot
 *
 * @param string $userAgent
 *
 * @return void
 */
function groundhogg_store_ua( string $userAgent = '' ) {

	if ( empty( $userAgent ) ) {
		$userAgent = $_SERVER['HTTP_USER_AGENT'];
	}

	$hashedUserAgent = hash( 'sha256', $userAgent );

	groundhog_add_to_file( $hashedUserAgent, GH_USER_AGENT_FILE );
}

/**
 * Check if a user agent is in our list
 *
 * @param string $userAgent
 *
 * @return bool
 */
function groundhogg_is_ua_stored( string $userAgent = '' ) {

	if ( empty( $userAgent ) ) {
		$userAgent = $_SERVER['HTTP_USER_AGENT'];
	}

	$hashedUserAgent = hash( 'sha256', $userAgent );

	return groundhogg_in_file( $hashedUserAgent, GH_USER_AGENT_FILE );
}

/**
 * Remove a user agent
 *
 * @param string $userAgent
 *
 * @return void
 */
function groundhogg_remove_ua( string $userAgent = '' ) {

	if ( empty( $userAgent ) ) {
		$userAgent = $_SERVER['HTTP_USER_AGENT'];
	}

	$hashedUserAgent = hash( 'sha256', $userAgent );

	groundhogg_remove_from_file( $hashedUserAgent, GH_USER_AGENT_FILE );
}

/**
 * Returns IPv6 or IPv4 address of the current visitor
 *
 * @return mixed|null
 */
function groundhogg_get_current_ip() {
	$places = [
		'REMOTE_ADDR',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_CLIENT_IP',
	];

	$found = '';

	foreach ( $places as $place ) {
		if ( ! empty( $_SERVER[ $place ] ) ) {
			$found = $_SERVER[ $place ];
			break;
		}
	}

	$ips = array_map( 'trim', explode( ',', $found ) );

	return array_pop( $ips );
}

/**
 * Store the IP address
 *
 * @param string $ip_address
 *
 * @return void
 */
function groundhogg_store_ip( string $ip_address = '' ) {

	if ( empty( $ip_address ) ) {
		$ip_address = groundhogg_get_current_ip();
	}

	groundhog_add_to_file( $ip_address, GH_IPS_FILE );
}

/**
 * If the IP is stored
 *
 * @param string $ip_address
 *
 * @return bool
 */
function groundhogg_is_ip_stored( string $ip_address = '' ) {

	if ( empty( $ip_address ) ) {
		$ip_address = groundhogg_get_current_ip();
	}

	return groundhogg_in_file( $ip_address, GH_IPS_FILE );
}

/**
 * @param string $ip_address
 *
 * @return void
 */
function groundhogg_remove_ip( string $ip_address = '' ) {

	if ( empty( $ip_address ) ) {
		$ip_address = groundhogg_get_current_ip();
	}

	groundhogg_remove_from_file( $ip_address, GH_IPS_FILE );
}

/**
 * Show the page to redirect to the ultimate destination with JavaScript
 * If the current request is from a link crawler, JavaScript will not execute!
 *
 * @return void
 */
function groundhogg_show_redirect_page( $redirect_to = '' ) {

	if ( empty( $redirect_to ) ) {
		$redirect_to = $_SERVER['REQUEST_URI'];
	}

	// has query string
	$redirect_to .= strpos( $redirect_to, '?' ) === false ? '?' : '&';
	$redirect_to .= GH_VERIFIED_PARAM . '=true';

	http_response_code( 200 );

	?>
    <!doctype html>
    <html>
    <head>
        <title><?php echo GH_DOCUMENT_TITLE; ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name='robots' content='noindex, follow'>
        <script>
          window.addEventListener('load', () => {
            console.log('Loaded!')

            let delay = <?php echo GH_REDIRECT_DELAY ?>;

            let delayView = document.getElementById('delay')

            let interval = setInterval(() => {

              delay--

              if (delay < 1) {
                document.querySelector('#main p').innerHTML = 'Redirecting you now...'
                window.open('<?php echo $redirect_to ?>', '_self')
                clearInterval(interval)
                return
              }

              delayView.innerHTML = delay

            }, 1000)

            document.querySelector('p a').addEventListener('click', () => {
              clearInterval(interval)
            })

          })
        </script>
        <style>
            html {
                background-color: #F6F9FB;
                position: initial !important;

                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                line-height: 1.6em;

                padding-top: 50px;
            }

            img {
                width: 300px;
                margin: 50px auto;
                display: block;
            }

            #main {
                max-width: 500px;
                margin: 0 auto;
                padding: 30px;
                font-weight: 400;
                overflow: hidden;
                background: #FFFFFF;
                box-shadow: 5px 5px 30px rgba(24, 45, 70, 0.05);
                /*margin: 20px;*/
                border-radius: 5px;
                border: none;
            }

            #main p {
                font-size: 18px;
            }

            #delay {
                font-weight: bold;
            }

            body p {
                margin: 1.1em 0;
                text-align: center;
                font-size: 14px;
            }

        </style>
    </head>
    <body>
	<?php if ( GH_LOGO_SRC ): ?>
        <img id="logo" src="<?php echo GH_LOGO_SRC ?>">
	<?php endif; ?>
    <div id="main">
        <p><?php printf( GH_AUTOMATIC_REDIRECTION_TEXT, sprintf( '<span id="delay">%s</span>', GH_REDIRECT_DELAY ) ); ?></p>
    </div>
    <p><?php printf( GH_CLICK_TO_CONTINUE_TEXT, $redirect_to, $_SERVER['HTTP_HOST'] ); ?></p>
    </body>
    </html>
	<?php

	die();

}

/**
 * Show a 1x1px transparent PNG
 *
 * @return void
 */
function groundhogg_show_pixel_image() {
	http_response_code( 200 );
	header( 'Content-Type: image/png' );
	echo hex2bin( '89504e470d0a1a0a0000000d494844520000000100000001010300000025db56ca00000003504c5445000000a77a3dda0000000174524e530040e6d8660000000a4944415408d76360000000020001e221bc330000000049454e44ae426082' );
	die();
}

/**
 * Test if the current user agent matches the given
 *
 * @param $agent
 *
 * @return bool
 */
function groundhogg_user_agent_is( $agent ) {
	return $_SERVER['HTTP_USER_AGENT'] === $agent;
}

/**
 * Include the WordPress code index.php file
 */
function groundhogg_load_wp() {
	include __DIR__ . '/../index.php';
}

/**
 * Perform checks on the current request to test if bot or real user
 * If checks pass, include the main WordPress index.php file
 * Otherwise, show either the redirect page or the image pixel
 *
 * @return void
 */
function groundhogg_check_if_crawler_or_include_index() {

    header( 'X-Groundhogg: /' . GH_MANAGED_PAGE_ROOT . '/' );

	$request = $_SERVER['REQUEST_URI'];
	$needles = [
		'/' . GH_MANAGED_PAGE_ROOT . '/tracking/email/',
		'/' . GH_MANAGED_PAGE_ROOT . '/c/',
		'/' . GH_MANAGED_PAGE_ROOT . '/o/',
	];

	if ( ! preg_match( '@' . implode( '|', $needles ) . '@', $request ) ) {

		groundhogg_load_wp();

		return;
	}

	if ( strpos( $request, GH_MANAGED_PAGE_ROOT . '/c/' ) !== false ) {
		$function = 'click';
	} else if ( strpos( $request, GH_MANAGED_PAGE_ROOT . '/o/' ) !== false ) {
		$function = 'open';
	} else {
		// backwards compat
		$parts    = array_values( array_filter( explode( '/', $request ) ) );
		$function = $parts[3];
	}

	switch ( $function ) {
		case 'open':

			// dummy image handling for the honeypot
			if ( strpos( $request, GH_MANAGED_PAGE_ROOT . '/o/pixelbot' ) !== false ) {
				groundhogg_show_pixel_image();
			}

			$request_checks = [
				// Google Image pre-fetch (not the same as GoogleImageProxy)
				function () {
					return
						groundhogg_user_agent_is( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246 Mozilla/5.0' )
						&& $_SERVER['HTTP_REFERER'] === 'http://mail.google.com/';
				},
				// Apple Mail Privacy Protection
				function () {
					return
						groundhogg_user_agent_is( 'Mozilla/5.0' )
						&& empty( $_SERVER['HTTP_REFERER'] );
				},
				// Any none get requests to the CLICK urls should be blocked
				function () {
					return $_SERVER['REQUEST_METHOD'] !== 'GET';
				},
				// is a known bot user agent
				function () {
					return groundhogg_is_ua_stored();
				},
				// IP is stored
				function () {
					return groundhogg_is_ip_stored();
				},
			];

			// if any of the checks predict bot behaviour, do not track and output tracking image
			foreach ( $request_checks as $request_check ) {
				if ( call_user_func( $request_check ) ) {
					groundhogg_show_pixel_image();
				}
			}

			break;
		case 'click':

			// already went through the redirect process, remove the user-agent
			if ( isset( $_GET[ GH_VERIFIED_PARAM ] ) ) {
				groundhogg_remove_ua();
				groundhogg_remove_ip();
				break;
			}

			// Honeypot bot trap
			if ( strpos( $request, GH_MANAGED_PAGE_ROOT . '/c/ruabot' ) !== false ) {

				// Store the user agent
				groundhogg_store_ua();

				// Store the IP
				groundhogg_store_ip();

				// Redirect to preferences center
				groundhogg_show_redirect_page( "/gh/" );
			}

			$request_checks = [
				// Any none get requests to the CLICK urls should be blocked
				function () {
					return $_SERVER['REQUEST_METHOD'] !== 'GET';
				},
				// If the user agent is stored, then they might be a bot
				function () {
					return groundhogg_is_ua_stored();
				},
				// IP is stored
				function () {
					return groundhogg_is_ip_stored();
				},
			];

			foreach ( $request_checks as $request_check ) {
				if ( call_user_func( $request_check ) ) {
					groundhogg_show_redirect_page();
				}
			}

			break;
	}

	groundhogg_load_wp();
}

groundhogg_check_if_crawler_or_include_index();
