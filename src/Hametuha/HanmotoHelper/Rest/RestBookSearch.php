<?php

namespace Hametuha\HanmotoHelper\Rest;

use Hametuha\HanmotoHelper\Controller\PostType;
use Hametuha\HanmotoHelper\Pattern\RestApiPattern;

/**
 * Book search REST API.
 *
 * @package hanmoto
 */
class RestBookSearch extends RestApiPattern {

	/**
	 * {@inheritdoc}
	 */
	protected function route() {
		return 'books/search';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_arguments( $method ) {
		return [
			'q' => [
				'required'          => true,
				'type'              => 'string',
				'description'       => 'Search query (ISBN or title)',
				'validate_callback' => function ( $param ) {
					return ! empty( $param ) && mb_strlen( $param ) >= 2;
				},
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function callback( $request ) {
		$query = $request->get_param( 'q' );
		$query = trim( $query );

		// Check if query looks like ISBN (digits only).
		$is_isbn = preg_match( '/^[0-9]+$/', $query );

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => 20,
		];

		if ( $is_isbn ) {
			// Search by ISBN.
			$args['meta_query'] = [
				[
					'key'     => PostType::META_KEY_ISBN,
					'value'   => $query,
					'compare' => 'LIKE',
				],
			];
		} else {
			// Search by title.
			$args['s'] = $query;
		}

		$products = new \WP_Query( $args );

		$results = [];
		foreach ( $products->posts as $product ) {
			$results[] = [
				'id'    => $product->ID,
				'title' => $product->post_title,
				'isbn'  => get_post_meta( $product->ID, PostType::META_KEY_ISBN, true ),
				'price' => get_post_meta( $product->ID, '_price', true ),
			];
		}

		return new \WP_REST_Response( $results, 200 );
	}
}
