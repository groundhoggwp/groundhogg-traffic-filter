<?php

namespace GroundhoggTrafficFilter;

use function Groundhogg\get_managed_page_name;
use function Groundhogg\install_custom_rewrites;

class Updater extends \Groundhogg\Updater{

	protected function get_updater_name() {
		return GROUNDHOGG_TRAFFIC_FILTER_NAME;
	}

	protected function get_plugin_file() {
		return GROUNDHOGG_TRAFFIC_FILTER__FILE__;
	}

	/**
	 * @return array[]
	 */
	protected function get_available_updates() {
		return [
			'1.0.2' => [
				'automatic' => true,
				'description' => __( 'Update the traffic filter to be compatible with shortened tracking URL structure.', 'groundhogg-traffic-filter' ),
				'callback' => function () {
					if ( is_traffic_filter_installed() ){
						upgrade_traffic_filter_file();

						install_custom_rewrites();
					}
				}
			],
			'1.1' => [
				'automatic' => true,
				'description' => __( 'Use an image link instead of a text link for the bot trap. Hash user-agents for faster lookups.', 'groundhogg-traffic-filter' ),
				'callback' => function () {

					// Not installed
					if ( ! is_traffic_filter_installed() ){
						return;
					}

					$folder = ABSPATH . '/' . get_managed_page_name();

					$inputFilePath = $folder . '/user-agents.txt';
					$outputFilePath = $folder . '/user-agents-hashed.txt';

					// Open input file for reading
					$inputFile = fopen($inputFilePath, 'r');
					if ($inputFile === false) {
						echo "Error opening input file.";
						return;
					}

					// Open output file for writing
					$outputFile = fopen($outputFilePath, 'w');
					if ($outputFile === false) {
						fclose($inputFile);
						echo "Error opening output file.";
						return;
					}

					// Iterate through each line in the input file
					while (($line = fgets($inputFile)) !== false) {
						// Remove newline character
						$line = trim($line);

						if ( empty( $line ) ){
							continue;
						}

						// Hash the user agent
						$hashedUserAgent = hash('sha256', $line);

						// Write hashed user agent to the output file
						fwrite($outputFile, $hashedUserAgent . PHP_EOL);
					}

					// Close files
					fclose($inputFile);
					fclose($outputFile);

					// Delete the original file
					unlink( $inputFilePath );

					// Rename it back to user-agents.txt
					rename( $outputFilePath, $inputFilePath );

					// Remove catch.php because we don't need it anymore. All done in index.php.
					unlink( $folder . '/catch.php' );

					// Upgrade the traffic filter finally
					upgrade_traffic_filter_file();
				}
			],
			'1.2' => [
				'automatic' => true,
				'description' => __( 'Add .htaccess for Apache systems and file for handling IPs separately.', 'groundhogg-traffic-filter' ),
				'callback' => function () {

					if ( ! is_traffic_filter_installed() ){
						return;
					}

					upgrade_traffic_filter_file();
				}
			]
		];
	}
}
