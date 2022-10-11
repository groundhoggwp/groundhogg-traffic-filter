<?php

/**
 * Any requests to this file will be treated as a potential bot, and the user agent will be added
 * to list of user agents that should be treated as "fake"
 */

// Check if in the correct directory
if ( ! file_exists( __DIR__ . '/../wp-config.php' ) || basename( __DIR__ ) !== 'gh' ) {
	die();
}

### REPLACE ###
const GH_LOGO_SRC       = '';
const GH_REDIRECT_DELAY = 3;
### END REPLACE ###

$user_agent             = $_SERVER['HTTP_USER_AGENT'];
$user_agents            = explode( PHP_EOL, file_get_contents( 'user-agents.txt' ) );
$full_url               = "https://{$_SERVER['HTTP_HOST']}";

if ( ! in_array( $user_agent, $user_agents ) ) {
	$user_agents[] = $user_agent;
}

file_put_contents( 'user-agents.txt', implode( PHP_EOL, $user_agents ) );

http_response_code( 200 );

?>
    <!doctype html>
    <html>
    <head>
        <title>Traffic Filter</title>
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
        <p>You will be automatically redirected in <span id="delay"><?php echo GH_REDIRECT_DELAY ?></span> seconds.
        </p>
    </div>
    <p>Or <a href="/">click here</a> to continue
        to <?php echo parse_url( $full_url, PHP_URL_HOST ) ?>.</p>
    </body>
    </html>
<?php

die();

