<?php

namespace GroundhoggTrafficFilter;

use function Groundhogg\install_custom_rewrites;

class Updater extends \Groundhogg\Updater{

	protected function get_updater_name() {
		return GROUNDHOGG_TRAFFIC_FILTER_NAME;
	}

	/**
	 * @return array[]
	 */
	protected function get_available_updates() {
		return [
			'1.0.2' => [
				'automatic' => true,
				'description' => __( 'Update the traffic filter to be compatible with shortened tracking URL structure.' ),
				'callback' => function () {
					if ( is_traffic_filter_installed() ){
						upgrade_traffic_filter_file();

						install_custom_rewrites();
					}
				}
			]
		];
	}
}