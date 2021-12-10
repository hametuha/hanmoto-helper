<?php
/*
Plugin Name: Hanmoto Helper
Plugin URI: https://github.com/hametuha/isbn-beautify
Description: Display Book information with ISBN. Using OpenBD.
Author: hametuha,Takahashi_Fumiki
Version: 1.0.0
Author URI: https://hametuha.co.jp/
License: GPL 3.0 or later
Text Domain: hanmoto
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die();


/**
 * Get plugin version.
 *
 * @return string
 */
function hanmoto_version() {
	static $info = null;
	// Get version number.
	if ( is_null( $info ) ) {
		$info = get_file_data( __FILE__, array(
			'version' => 'Version',
		) );
	}
	return $info['version'];
}

/**
 * Get root path.
 *
 * @return string
 */
function hanmoto_root_dir() {
	return __DIR__;
}

/**
 * Register hooks.
 */
function hanmoto_plugin_init() {
	// Functions.
	require_once  __DIR__ . '/includes/utility.php';
	// autoloader.
	load_plugin_textdomain( 'hanmoto', true, basename( __DIR__ ) . '/languages' );
	$autoloader = __DIR__ . '/vendor/autoload.php';
	if ( file_exists( $autoloader ) ) {
		require_once $autoloader;
		\Hametuha\HanmotoHelper\Bootstrap::get_instance();
	}
}
add_action( 'plugins_loaded', 'hanmoto_plugin_init' );
