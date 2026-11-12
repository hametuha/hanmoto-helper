<?php
/**
 * Books post type must not be readable via REST API by the public.
 *
 * @package hanmoto
 */

use Hametuha\HanmotoHelper\Controller\PostType;

/**
 * Test REST API read access to books.
 */
class Test_Rest_Privacy extends WP_UnitTestCase {

	/**
	 * Published book ID.
	 *
	 * @var int
	 */
	private $book_id = 0;

	/**
	 * Register post type and create a published book.
	 */
	public function set_up() {
		parent::set_up();
		// Post type is registered only when enabled in settings.
		PostType::get_instance()->create_post_type();
		PostType::get_instance()->register_meta_fields();
		// Never call openBD while testing.
		add_filter( 'pre_http_request', function () {
			return [
				'headers'  => [],
				'body'     => '[{"summary":{"isbn":"9784000000000"}}]',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		} );
		// Rebuild REST server to register routes for the post type above.
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
		$this->book_id = self::factory()->post->create( [
			'post_type'    => 'books',
			'post_status'  => 'publish',
			'post_title'   => 'Unpublished book',
			'post_content' => 'Internal note',
		] );
		update_post_meta( $this->book_id, PostType::META_KEY_ISBN, '9784000000000' );
	}

	/**
	 * Reset REST server.
	 */
	public function tear_down() {
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	/**
	 * Send GET request.
	 *
	 * @param string $route Route.
	 * @return WP_REST_Response
	 */
	private function get( $route ) {
		return rest_do_request( new WP_REST_Request( 'GET', $route ) );
	}

	/**
	 * Anonymous users cannot list or read published books.
	 */
	public function test_anonymous_cannot_read() {
		wp_set_current_user( 0 );
		$list = $this->get( '/wp/v2/books' );
		$this->assertSame( 401, $list->get_status() );
		$this->assertArrayNotHasKey( 'X-WP-Total', $list->get_headers() );
		$this->assertSame( 401, $this->get( '/wp/v2/books/' . $this->book_id )->get_status() );
		$this->assertSame( 401, $this->get( '/hanmoto/v1/book/' . $this->book_id )->get_status() );
	}

	/**
	 * Logged-in users without edit capability cannot read either.
	 */
	public function test_subscriber_cannot_read() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 403, $this->get( '/wp/v2/books' )->get_status() );
		$this->assertSame( 403, $this->get( '/wp/v2/books/' . $this->book_id )->get_status() );
		$this->assertSame( 403, $this->get( '/hanmoto/v1/book/' . $this->book_id )->get_status() );
	}

	/**
	 * Editors keep access so that the block editor works.
	 */
	public function test_editor_can_read() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$list = $this->get( '/wp/v2/books' );
		$this->assertSame( 200, $list->get_status() );
		$this->assertCount( 1, $list->get_data() );
		$single = $this->get( '/wp/v2/books/' . $this->book_id );
		$this->assertSame( 200, $single->get_status() );
		$this->assertSame( '9784000000000', $single->get_data()['meta'][ PostType::META_KEY_ISBN ] );
		$info = $this->get( '/hanmoto/v1/book/' . $this->book_id );
		$this->assertSame( 200, $info->get_status() );
		$this->assertSame( '9784000000000', $info->get_data()[0]['summary']['isbn'] );
	}
}
