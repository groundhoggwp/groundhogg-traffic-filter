<?php

namespace GroundhoggTrafficFilter;

use function Groundhogg\action_url;
use function Groundhogg\base64url_encode;
use function Groundhogg\get_managed_page_name;
use function Groundhogg\html;
use function Groundhogg\managed_page_url;
use function Groundhogg\notices;

/**
 * Whether the traffic filter is installed
 *
 * @return bool
 */
function is_traffic_filter_installed() {

	$name = get_managed_page_name();

	return file_exists( ABSPATH . $name . '/index.php' );
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

	$automatic_redirection_text = __( 'You will be redirected in %s seconds.', 'groundhogg-traffic-filter' );
	$click_to_continue_text     = __( 'Or click <a href="%1$s">here</a> to continue to %2$s.', 'groundhogg-traffic-filter' );
	$document_title             = sprintf( __( '%s - Traffic Filter', 'groundhogg-traffic-filter' ), get_bloginfo( 'name' ) );
	$redirect_delay             = apply_filters( 'groundhogg/traffic_filter/redirect_delay', 3 );
	$page_root                  = get_managed_page_name();

	$constants = "    
const GH_LOGO_SRC       = '$logo';
const GH_DOCUMENT_TITLE = '$document_title';
const GH_MANAGED_PAGE_ROOT = '$page_root';
const GH_REDIRECT_DELAY = $redirect_delay;
const GH_VERIFIED_PARAM = '__verified';
const GH_AUTOMATIC_REDIRECTION_TEXT = '$automatic_redirection_text';
const GH_CLICK_TO_CONTINUE_TEXT = '$click_to_continue_text';
";

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

	$folder = ABSPATH . get_managed_page_name();

	if ( ! is_dir( $folder ) ) {
		wp_mkdir_p( $folder );
	}

	copy( __DIR__ . '/../files/index.php', $folder . '/index.php' );
	copy( __DIR__ . '/../files/.htaccess', $folder . '/.htaccess' );

	setup_constants( $folder . '/index.php' );
}

/**
 * Remove the gh/index.php file and folder from the root WordPress directory
 * And then save rewrites
 *
 * @return void
 */
function remove_traffic_filter_file() {

	$folder = ABSPATH . get_managed_page_name();

	unlink( $folder . '/index.php' );
	unlink( $folder . '/user-agents.txt' );
	unlink( $folder . '/ips.txt' );
	unlink( $folder . '/.htaccess' );
	rmdir( $folder );
}

/**
 * Upgrades the traffic filter files
 *
 * @return void
 */
function upgrade_traffic_filter_file() {
	$folder = ABSPATH . get_managed_page_name();
	copy( __DIR__ . '/../files/index.php', $folder . '/index.php' );
	copy( __DIR__ . '/../files/.htaccess', $folder . '/.htaccess' );
	setup_constants( $folder . '/index.php' );
}

add_filter( 'groundhogg/is_url_excluded_from_tracking', __NAMESPACE__ . '\exclude_honeypot_url_from_tracking', 10, 2 );

/**
 * Make sure the /ruabot/ link is not tracked
 *
 * @param bool   $matched
 * @param string $url
 *
 * @return bool
 */
function exclude_honeypot_url_from_tracking( $matched, $url ) {
	if ( $matched ) {
		return $matched;
	}

	$name = get_managed_page_name();

	return str_contains( $url, "/$name/c/ruabot/" );
}


add_filter( 'groundhogg/admin/gh_tools/install_traffic_filter_process', function () {

	if ( current_user_can( 'manage_options' ) ) {
		install_traffic_filter_file();

		notices()->add( 'success', __( 'Traffic filter installed!', 'groundhogg-tracking-filter' ) );
	}

	return true;

} );

add_filter( 'groundhogg/admin/gh_tools/remove_traffic_filter_process', function () {

	if ( current_user_can( 'manage_options' ) ) {
		remove_traffic_filter_file();

		notices()->add( 'success', __( 'Traffic filter removed!', 'groundhogg-tracking-filter' ) );
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
            <h2><?php _e( 'Install Traffic Filter', 'groundhogg-tracking-filter' ); ?></h2>
        </div>
        <div class="inside">
            <p><?php _e( 'Creates a special file that is loaded before WordPress and will automatically filter out potentially fake opens and clicks when tracking email engagement.', 'groundhogg-tracking-filter' ); ?></p>
			<?php if ( ! is_traffic_filter_installed() ): ?>
                <p><?php echo html()->e( 'a', [
						'class' => 'gh-button secondary',
						'href'  => action_url( 'install_traffic_filter' ),
					], __( 'Install Filter', 'groundhogg-tracking-filter' ) ) ?></p>
			<?php else: ?>
                <p class="display-flex gap-10"><?php

					echo html()->e( 'a', [
						'class' => 'gh-button secondary',
						'href'  => action_url( 'install_traffic_filter' ),
					], __( 'Re-Install Filter', 'groundhogg-tracking-filter' ) );

					echo html()->e( 'a', [
						'class' => 'gh-button danger',
						'href'  => action_url( 'remove_traffic_filter' ),
					], __( 'Remove Filter', 'groundhogg-tracking-filter' ) )

					?></p>
			<?php endif; ?>
        </div>
    </div>
	<?php
}

// new emails
add_action( 'groundhogg/templates/email/part/footer', __NAMESPACE__ . '\add_bot_trap' );
// legacy emails
add_action( 'groundhogg/templates/email/footer/before', __NAMESPACE__ . '\add_bot_trap' );

/**
 * Adds bot traffic honey-pot after preview text in new templates
 *
 * @return void
 */
function add_bot_trap() {

	// Do not add the link if the traffic filter is not installed
	if ( ! is_traffic_filter_installed() ) {
		return;
	}

	$url = managed_page_url( '/c/ruabot/' . base64url_encode( wp_generate_uuid4() ) . '/?yes=1' );
	$src = managed_page_url( '/o/pixelbot/' );

	?>
    <a title="Click here if you are a robot" href="<?php echo $url; ?>" style="display: none;visibility: hidden;">
        <img alt="" src="<?php echo $src; ?>" height="0" width="0"/>
    </a>
	<?php
}
