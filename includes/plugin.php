<?php

namespace GroundhoggTrafficFilter;

use Groundhogg\Extension;

class Plugin extends Extension {

	/**
	 * Override the parent instance.
	 *
	 * @var Plugin
	 */
	public static $instance;

	/**
	 * @var Updater
	 */
	public $updater;

	/**
	 * Include any files.
	 *
	 * @return void
	 */
	public function includes() {
        require  __DIR__ . '/functions.php';
	}

	/**
	 * Init any components that need to be added.
	 *
	 * @return void
	 */
	public function init_components() {

		$this->updater = new Updater();

		add_action( 'groundhogg/install_custom_rewrites', [ $this, 'rewrites' ] );
	}

	public function rewrites(){
		if ( is_traffic_filter_installed() ){
			add_rewrite_rule( 'gh/tracking/email', 'gh/index.php' );
			add_rewrite_rule( 'gh/c/', 'gh/index.php' );
			add_rewrite_rule( 'gh/o/', 'gh/index.php' );
		}
	}

	/**
	 * Get the ID number for the download in EDD Store
	 *
	 * @return int
	 */
	public function get_download_id() {
		return 135217;
	}

	/**
	 * Get the version #
	 *
	 * @return mixed
	 */
	public function get_version() {
		return GROUNDHOGG_TRAFFIC_FILTER_VERSION;
	}

	/**
	 * @return string
	 */
	public function get_plugin_file() {
		return GROUNDHOGG_TRAFFIC_FILTER__FILE__;
	}

	public function register_funnel_steps( $manager ) {
	}

	/**
	 * Register autoloader.
	 *
	 * Groundhogg autoloader loads all the classes needed to run the plugin.
	 *
	 * @since 1.6.0
	 * @access private
	 */
	protected function register_autoloader() {
		require __DIR__ . '/autoloader.php';
		Autoloader::run();
	}
}

Plugin::instance();
