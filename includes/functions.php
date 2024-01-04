<?php

namespace GroundhoggTrafficFilter;

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

	$automatic_redirection_text = __( 'You will be redirected in %s seconds.', 'groundhogg-traffic-filter' );
	$click_to_continue_text     = __( 'Or click <a href="%1$s">here</a> to continue to %2$s.', 'groundhogg-traffic-filter' );
	$document_title             = sprintf( __( '%s - Traffic Filter', 'groundhogg-traffic-filter' ), get_bloginfo( 'name' ) );
	$redirect_delay             = apply_filters( 'groundhogg/traffic_filter/redirect_delay', 3 );

	$constants = "    
const GH_LOGO_SRC       = '$logo';
const GH_DOCUMENT_TITLE = '$document_title';
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

/**
 * Upgrades the traffic filter files
 *
 * @return void
 */
function upgrade_traffic_filter_file() {

	$folder = ABSPATH . 'gh';

	copy( __DIR__ . '/../files/index.php', $folder . '/index.php' );
	copy( __DIR__ . '/../files/catch.php', $folder . '/catch.php' );

	setup_constants( $folder . '/index.php' );
	setup_constants( $folder . '/catch.php' );
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

add_action( 'groundhogg/templates/email/content/before', __NAMESPACE__ . '\add_bot_trap_link_to_emails_for_legacy_emails' );

/**
 * Adds a hidden link to all emails that bots would probably click on
 *
 * Legacy emails only!
 *
 * @deprecated
 *
 * @return void
 */
function add_bot_trap_link_to_emails_for_legacy_emails() {

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

	$preview_text = apply_filters( 'groundhogg/email_template/pre_header_text', '' );
	$trap_text    = empty( $preview_text ) ? '' : '&nbsp;-&nbsp;';

	?>
    <div style="display: none">
        <a style="<?php echo array_to_css( $style ); ?>" href="<?php echo $link ?>"><?php echo $trap_text; ?></a>
    </div>
	<?php
}

add_action( 'groundhogg/templates/email/preview-text/after', __NAMESPACE__ . '\add_bot_trap_after_preview_text' );

/**
 * Adds bot traffic filter after preview text in new templates
 *
 * @return void
 */
function add_bot_trap_after_preview_text() {

	// Do not add the link if the traffic filter is not installed
	if ( ! is_traffic_filter_installed() ) {
		return;
	}

	$link = home_url( '/gh/catch.php' );

	?>
    <p style="display: none;line-height: 0;margin: 0;font-size: 1px;color: transparent;">
        <a style="text-decoration: none; color: transparent;visibility: hidden;font-size: 1px"
           href="<?php echo $link ?>"><?php bloginfo() ?></a>
    </p>
	<?php
}
