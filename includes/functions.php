<?php

namespace GroundhoggTrafficFilter;

use Groundhogg\Plugin;
use function Groundhogg\action_url;
use function Groundhogg\array_to_css;
use function Groundhogg\html;
use function Groundhogg\install_custom_rewrites;
use function Groundhogg\notices;

/**
 * Whether the traffic filter is installed
 *
 * @return bool
 */
function is_traffic_filter_installed() {
	return file_exists( ABSPATH . 'gh/index.php' );
}

/**
 * Add dynamically generated constants to special files
 *
 * @param $file
 *
 * @return void
 */
function setup_constants( $file ) {

	$contents = file_get_contents( $file );
	$logo     = wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' );
	$logo     = empty( $logo ) ? '' : $logo[0];

	$constants = "    
const GH_LOGO_SRC       = '$logo';
const GH_REDIRECT_DELAY = 3;
const GH_VERIFIED_PARAM = '__verified';";

	$contents = preg_replace( '/### REPLACE ###([^#]+)### END REPLACE ###/', $constants, $contents );

	file_put_contents( $file, $contents );

}

/**
 * Install the gh/index.php file in the root directory of WordPress
 * then update the rewrites
 *
 * @return void
 */
function install_traffic_filter_file() {

	$folder = ABSPATH . 'gh';

	if ( ! is_dir( $folder ) ) {
		wp_mkdir_p( $folder );
	}

	copy( __DIR__ . '/../files/index.php', $folder . '/index.php' );
	copy( __DIR__ . '/../files/catch.php', $folder . '/catch.php' );
	file_put_contents( $folder . '/user-agents.txt', '' );

	setup_constants( $folder . '/index.php' );
	setup_constants( $folder . '/catch.php' );

	$exclusions = get_option( 'gh_url_tracking_exclusions' );
	if ( ! preg_match( '@/gh/catch\.php\$@', $exclusions ) ) {
		$exclusions .= "\n/gh/catch.php$";
		update_option( 'gh_url_tracking_exclusions', $exclusions );
	}

	install_custom_rewrites();
}

/**
 * Remove the gh/index.php file and folder from the root WordPress directory
 * And then save rewrites
 *
 * @return void
 */
function remove_traffic_filter_file() {

	$folder = ABSPATH . 'gh';

	unlink( $folder . '/index.php' );
	unlink( $folder . '/catch.php' );
	unlink( $folder . '/user-agents.txt' );
	rmdir( $folder );

	install_custom_rewrites();
}

add_filter( 'groundhogg/admin/gh_tools/install_traffic_filter_process', function () {

	if ( current_user_can( 'manage_options' ) ) {
		install_traffic_filter_file();

		notices()->add( 'success', __( 'Traffic filter installed!' ) );
	}

	return true;

} );

add_filter( 'groundhogg/admin/gh_tools/remove_traffic_filter_process', function () {

	if ( current_user_can( 'manage_options' ) ) {
		remove_traffic_filter_file();

		notices()->add( 'success', __( 'Traffic filter removed!' ) );
	}

	return true;

} );

add_action( 'groundhogg/tools/misc', __NAMESPACE__ . '\show_install_traffic_filter_tool' );

/**
 * Show the traffic filter installation tool in the misc tools tab
 *
 * @return void
 */
function show_install_traffic_filter_tool() {
	?>

    <div class="gh-panel">
        <div class="gh-panel-header">
            <h2><?php _e( 'Install Traffic Filter', 'groundhogg' ); ?></h2>
        </div>
        <div class="inside">
            <p><?php _e( 'Creates a special file that is loaded before WordPress and will automatically filter out potentially fake opens and clicks when tracking email engagement.', 'groundhogg' ); ?></p>
			<?php if ( ! is_traffic_filter_installed() ): ?>
                <p><?php echo html()->e( 'a', [
						'class' => 'gh-button secondary',
						'href'  => action_url( 'install_traffic_filter' ),
					], __( 'Install Filter', 'groundhogg' ) ) ?></p>
			<?php else: ?>
                <p class="display-flex gap-10"><?php

					echo html()->e( 'a', [
						'class' => 'gh-button secondary',
						'href'  => action_url( 'install_traffic_filter' ),
					], __( 'Re-Install Filter', 'groundhogg' ) );

					echo html()->e( 'a', [
						'class' => 'gh-button danger',
						'href'  => action_url( 'remove_traffic_filter' ),
					], __( 'Remove Filter', 'groundhogg' ) )

					?></p>
			<?php endif; ?>
        </div>
    </div>
	<?php
}

add_action( 'groundhogg/templates/email/content/before', __NAMESPACE__ . '\add_bot_trap_link_to_emails' );

/**
 * Adds a hidden link to all emails that bots would probably click on
 *
 * @return void
 */
function add_bot_trap_link_to_emails() {

	// Do not add the link if the traffic filter is not installed
	if ( ! is_traffic_filter_installed() ) {
		return;
	}

	$link = home_url( '/gh/catch.php' );

	$style = [
		'text-decoration' => 'none',
		'color'           => 'transparent',
		'visibility'      => 'hidden',
		'font-size'       => '1px'
	];

	?>
    <div style="display: none">
        <a style="<?php echo array_to_css( $style ); ?>"
           href="<?php echo $link ?>"><?php echo get_bloginfo( 'name' ); ?></a>
    </div>
	<?php
}
