<?php
/**
 * Smoke test for plugin loading.
 *
 * @package hanmoto
 */

/**
 * Check that plugin is loaded without WooCommerce.
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * Plugin functions are defined.
	 */
	public function test_functions_exist() {
		$this->assertTrue( function_exists( 'hanmoto_version' ) );
		$this->assertTrue( function_exists( 'hanmoto_root_dir' ) );
		$this->assertTrue( function_exists( 'hanmoto_isbn10' ) );
		$this->assertNotEmpty( hanmoto_version() );
	}

	/**
	 * Initializer is hooked and bootstrap is loaded.
	 */
	public function test_bootstrap() {
		$this->assertNotFalse( has_action( 'plugins_loaded', 'hanmoto_plugin_init' ) );
		$this->assertTrue( class_exists( 'Hametuha\HanmotoHelper\Bootstrap' ) );
	}

	/**
	 * Shortcodes are registered.
	 */
	public function test_shortcodes() {
		$this->assertTrue( shortcode_exists( 'book' ) );
		$this->assertTrue( shortcode_exists( 'books' ) );
	}

	/**
	 * ISBN13 is converted to ISBN10.
	 */
	public function test_isbn10() {
		$this->assertSame( '4101010013', hanmoto_isbn10( '9784101010014' ) );
	}
}
