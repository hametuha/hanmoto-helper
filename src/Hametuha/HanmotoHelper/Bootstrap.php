<?php

namespace Hametuha\HanmotoHelper;


use Hametuha\HanmotoHelper\Controller\Blocks;
use Hametuha\HanmotoHelper\Controller\OrderManager;
use Hametuha\HanmotoHelper\Controller\PostType;
use Hametuha\HanmotoHelper\Controller\RestBookInfo;
use Hametuha\HanmotoHelper\Controller\Settings;
use Hametuha\HanmotoHelper\Controller\WooCommerceHelper;
use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * Plugin Bootstrap.
 *
 * @package hanmoto
 */
class Bootstrap extends Singleton {

	/**
	 * @inheritDoc
	 */
	protected function init() {
		Settings::get_instance();
		PostType::get_instance();
		RestBookInfo::get_instance();
		Blocks::get_instance();
		// Register command.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'hanmoto', \Hametuha\HanmotoHelper\Utility\Commands::class );
		}
		add_action( 'init', [ $this, 'register_assets' ] );
		// If WooCommerce is supported,
		// Register it.
		if ( class_exists( 'WooCommerce' ) ) {
			WooCommerceHelper::get_instance();
			OrderManager::get_instance();
		}
	}

	/**
	 * Register assets.
	 */
	public function register_assets() {
		// libraries.
		wp_register_script( 'chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js', [], '4.4.1', true );
		wp_register_script( 'chart-js-adapter-moment', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.1/dist/chartjs-adapter-moment.min.js', [ 'chart-js', 'moment' ], '1.0.1', true );
		// local files.
		$json = hanmoto_root_dir() . '/wp-dependencies.json';
		if ( ! file_exists( $json ) ) {
			return;
		}
		$assets = json_decode( file_get_contents( $json ), true );
		if ( empty( $assets ) ) {
			return;
		}
		foreach ( $assets as $asset ) {
			if ( empty( $asset['path'] ) ) {
				continue;
			}
			$url = plugin_dir_url( hanmoto_root_dir() . '/dist' ) . $asset['path'];
			switch ( $asset['ext'] ) {
				case 'css':
					wp_register_style( $asset['handle'], $url, $asset['deps'], $asset['hash'], $asset['media'] );
					break 1;
				case 'js':
					wp_register_script( $asset['handle'], $url, $asset['deps'], $asset['hash'], $asset['footer'] );
					if ( in_array( 'wp-i18n', $asset['deps'], true ) ) {
						wp_set_script_translations( $asset['handle'], 'hanmoto' );
					}
					break 1;
			}
		}
	}
}
