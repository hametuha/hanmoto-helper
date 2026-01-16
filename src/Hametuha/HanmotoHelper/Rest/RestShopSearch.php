<?php

namespace Hametuha\HanmotoHelper\Rest;

use Hametuha\HanmotoHelper\Pattern\RestApiPattern;

/**
 * Shop search REST API.
 *
 * @package hanmoto
 */
class RestShopSearch extends RestApiPattern {

	/**
	 * {@inheritdoc}
	 */
	protected function route() {
		return 'shops/search';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_arguments( $method ) {
		return [
			'q' => [
				'required'          => true,
				'type'              => 'string',
				'description'       => 'Search query',
				'validate_callback' => function( $param ) {
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
		$terms = get_terms( [
			'taxonomy'   => 'supplier',
			'hide_empty' => false,
			'number'     => 20,
			'search'     => $query,
		] );

		if ( is_wp_error( $terms ) ) {
			return new \WP_REST_Response( [], 200 );
		}

		$results = array_map( function( $term ) {
			return [
				'term_id'    => $term->term_id,
				'name'       => $term->name,
				'wholesaler' => get_term_meta( $term->term_id, 'wholesaler', true ),
				'line_code'  => get_term_meta( $term->term_id, 'line_code', true ),
				'shop_code'  => get_term_meta( $term->term_id, 'shop_code', true ),
			];
		}, $terms );

		return new \WP_REST_Response( $results, 200 );
	}
}
