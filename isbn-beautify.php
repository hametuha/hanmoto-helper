<?php
/*
Plugin Name: ISBN Beautify
Plugin URI: https://github.com/hametuha/isbn-beautify
Description: Display Book information with ISBN. Using OpenBD.
Author: Takahashi_Fumiki
Version: 1.0.0
PHP Version: 5.4
Author URI: https://hametuha.co.jp/
License: MIT
Text Domain: isbn-beautify
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die();

// Get version number.
$isbn_beautify_info = get_file_data( __FILE__, array(
	'version' => 'Version',
	'php_version' => 'PHP Version',
	'domain' => 'Text Domain',
) );

load_plugin_textdomain( $isbn_beautify_info['domain'], true, basename( __DIR__ ) . '/languages' );

/**
 * Display error message
 *
 * @internal This function is error message only.
 */
function isbnb_beautify_message() {
	global $isbn_beautify_info;
	printf(
		'<div class="error"><p>%s</p></div>',
		sprintf(
			__( '[ERROR] ISBN Beautify requires PHP %s but your PHP is %s.', 'isbn-beautify' ),
			phpversion(),
			$isbn_beautify_info['php_version']
		)
	);
}

// Check PHP version and start plugin if O.K.
if ( version_compare( $isbn_beautify_info['php_version'], phpversion(), '>' ) ) {
	add_action( 'admin_notices', 'isbn_beautify_message' );
} else {
	foreach ( array( 'functions', 'hooks' ) as $dir ) {
		$base = __DIR__."/{$dir}";
		foreach ( scandir( $base ) as $file ) {
			if ( ! preg_match( '#^[^.].*\.php$#u', $file ) ) {
				continue;
			}
			include "{$base}/{$file}";
		}
	}
}

