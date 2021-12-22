<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Pattern\RestApiPattern;
use Hametuha\HanmotoHelper\Utility\OpenDbApi;
use Hametuha\HanmotoHelper\Utility\SettingsAccessor;

/**
 * REST API for single book info.
 *
 * @package hanmoto
 */
class RestBookInfo extends RestApiPattern {

	use SettingsAccessor,
		OpenDbApi;

	/**
	 * @inheritDoc
	 */
	protected function route() {
		return 'book/(?P<post_id>\d+)';
	}

	/**
	 * @return string[]
	 */
	protected function methods() {
		return [ 'GET' ];
	}

	/**
	 * @inheritDoc
	 */
	public function get_arguments( $method ) {
		return [
			'post_id' => [
				'require'           => true,
				'type'              => 'integer',
				'validate_callback' => function( $var ) {
					$post = get_post( $var );
					return $post && $this->option()->post_type === $post->post_type;
				},
			],
			'isbn'    => [
				'type'              => 'string',
				'default'           => '',
				'validate_callback' => function( $var ) {
					if ( '' === $var ) {
						return true;
					}
					$isbns    = explode( ',', $var );
					$filtered = array_filter( $isbns, function( $isbn ) {
						return preg_match( '/\d{13}/', $isbn );
					} );
					return count( $isbns ) === count( $filtered );
				},
			],
		];
	}

	/**
	 * Get ISBN information.
	 *
	 * @param \WP_REST_Request $request
	 * @return void|\WP_Error|\WP_REST_Response
	 */
	public function callback( $request ) {
		$post_id = $request->get_param( 'post_id' );
		$isbn    = $request->get_param( 'isbn' );
		if ( empty( $isbn ) ) {
			$post_isbn = PostType::get_instance()->get_isbn( $post_id );
			if ( ! $post_isbn ) {
				return new \WP_Error( 'invalid_request', __( 'Post has no ISBN.', 'hanmoto' ), [
					'status' => 400,
				] );
			}
			$isbn = [ $post_isbn ];
		} else {
			$isbn = explode( ',', $isbn );
		}
		$response = $this->openbd_get( $isbn );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return new \WP_REST_Response( $response );
	}
}
