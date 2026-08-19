<?php

namespace Hametuha\HanmotoHelper\Rest;

use Hametuha\HanmotoHelper\Models\ModelOrderFax;
use Hametuha\HanmotoHelper\Pattern\RestApiPattern;

/**
 * Search the orders which can be added to a fax.
 *
 * @package hanmoto
 */
class RestOrderFaxCandidates extends RestApiPattern {

	/**
	 * {@inheritdoc}
	 */
	protected function route() {
		return 'order-fax/(?P<fax_id>\d+)/candidates';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_arguments( $method ) {
		return [
			'fax_id' => [
				'required'    => true,
				'type'        => 'integer',
				'description' => 'Post ID of fax.',
			],
			'q'      => [
				'required'          => true,
				'type'              => 'string',
				'description'       => 'Search query. Bookshop name, book title or order ID.',
				'validate_callback' => function ( $param ) {
					return '' !== trim( (string) $param );
				},
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function callback( $request ) {
		$fax_id = (int) $request->get_param( 'fax_id' );
		$fax    = get_post( $fax_id );
		if ( ! $fax || ModelOrderFax::POST_TYPE !== $fax->post_type ) {
			return new \WP_Error( 'not_found', __( 'FAX送付分が見つかりません。', 'hanmoto' ), [ 'status' => 404 ] );
		}
		$orders = ModelOrderFax::get_instance()->search_candidates( $request->get_param( 'q' ), $fax_id );
		return new \WP_REST_Response( [
			'orders'  => $orders,
			'message' => empty( $orders )
				? __( '該当する注文がありません。すでにこの送付分に入っている注文と返品は候補に出ません。', 'hanmoto' )
				: '',
		] );
	}
}
