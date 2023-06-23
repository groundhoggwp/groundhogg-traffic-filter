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
if ( ! file_exists( __DIR__ . '/../wp-config.php' ) || basename( __DIR__ ) !== 'gh' ) {
	die();
}

### REPLACE ###
const GH_LOGO_SRC                   = '';
const GH_DOCUMENT_TITLE             = 'Traffic Filter';
const GH_REDIRECT_DELAY             = 3;
const GH_VERIFIED_PARAM             = '__verified';
const GH_AUTOMATIC_REDIRECTION_TEXT = 'You will be redirected in %s seconds.';
const GH_CLICK_TO_CONTINUE_TEXT     = 'Or click <a href="%1$s">here</a> to continue to %2$s.';
### END REPLACE ###

/**
 * Show the page to redirect to the ultimate destination with JavaScript
 * If the current request is from a link crawler, JavaScript will not execute!
 *
 * @return void
 */
function groundhogg_show_redirect_page() {

	$full_url = "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

	// has query string
	if ( strpos( $full_url, '?' ) === false ) {
		$full_url .= '?';
	}

	$full_url .= '&' . GH_VERIFIED_PARAM . '=true';

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
                window.open('<?php echo $full_url ?>', '_self')
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
	<p><?php printf( GH_CLICK_TO_CONTINUE_TEXT, '?' . GH_VERIFIED_PARAM . '=true', parse_url( $full_url, PHP_URL_HOST ) ); ?></p>
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

	$request = $_SERVER['REQUEST_URI'];

	$needles = [
		'/gh/tracking/email/',
		'/gh/c/',
		'/gh/o/',
	];

	if ( ! preg_match( '@' . implode( '|', $needles ) . '@', $request ) ) {

		groundhogg_load_wp();

		return;
	}

	if ( strpos( $request, '/gh/c/' ) !== false ) {
		$function = 'click';
	} else if ( strpos( $request, '/gh/o/' ) !== false ) {
		$function = 'open';
	} else {
		// backwards compat
		$parts    = array_values( array_filter( explode( '/', $request ) ) );
		$function = $parts[3];
	}

	$caught_problem_user_agents = explode( PHP_EOL, file_get_contents( 'user-agents.txt' ) );

	$known_problem_user_agents = [
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
		'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.1.16) Gecko/20080702 Firefox/2.0.0.16',
		'Mozilla/5.0 (Apple Mac OS X v10.9.3; Trident/7.0; rv:11.0) like Gecko',
		'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',
		'Barracuda Sentinel (EE)',
		'Mozilla/5.0',
	];

	$known_problem_user_agents = array_merge( $caught_problem_user_agents, $known_problem_user_agents );

	switch ( $function ) {
		case 'open':

			// known user agents that are crawling links or preloading images
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
						$_SERVER['HTTP_USER_AGENT'] === 'Mozilla/5.0'
						&& empty( $_SERVER['HTTP_REFERER'] );
				},
				function () use ( $known_problem_user_agents ) {
					return in_array( $_SERVER['HTTP_USER_AGENT'], $known_problem_user_agents );
				},
			];

			foreach ( $request_checks as $request_check ) {
				if ( call_user_func( $request_check ) ) {
					groundhogg_show_pixel_image();
				}
			}

			break;
		case 'click':

			$request_checks = [
				function () {
					return $_SERVER['REQUEST_METHOD'] !== 'GET';
				},
				function () use ( $known_problem_user_agents ) {
					return in_array( $_SERVER['HTTP_USER_AGENT'], $known_problem_user_agents );
				},
			];

			foreach ( $request_checks as $request_check ) {
				if ( call_user_func( $request_check ) && ! isset( $_GET[ GH_VERIFIED_PARAM ] ) ) {
					groundhogg_show_redirect_page();
				}
			}


			break;
	}

	groundhogg_load_wp();
}

groundhogg_check_if_crawler_or_include_index();
