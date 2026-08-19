<?php

namespace Hametuha\HanmotoHelper\Rest;

use Hametuha\HanmotoHelper\Models\ModelOrderFax;
use Hametuha\HanmotoHelper\Pattern\RestApiPattern;

/**
 * Attach and detach the orders of a fax.
 *
 * 追加・削除はどちらも即時に反映される。更新後の一覧を返すので、
 * 画面はリロードせずに描き直せる（リロードすると未保存の宛先が消える）。
 *
 * @package hanmoto
 */
class RestOrderFaxOrders extends RestApiPattern {

	/**
	 * {@inheritdoc}
	 */
	protected function route() {
		return 'order-fax/(?P<fax_id>\d+)/orders';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function methods() {
		return [ 'POST', 'DELETE' ];
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
			'ids'    => [
				'required'          => true,
				'type'              => 'string',
				'description'       => 'Comma separated post IDs of orders.',
				'validate_callback' => function ( $param ) {
					return (bool) preg_match( '/\A[0-9]+(,[0-9]+)*\z/', (string) $param );
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
		$model = ModelOrderFax::get_instance();
		$ids   = $request->get_param( 'ids' );
		if ( 'DELETE' === $request->get_method() ) {
			$detached = $model->detach_orders( $fax_id, $ids );
			$message  = sprintf(
				// translators: %d is the number of orders.
				__( '%d件の注文を外しました。', 'hanmoto' ),
				$detached
			);
		} else {
			$result  = $model->attach_orders( $fax_id, $ids );
			$message = sprintf(
				// translators: %d is the number of orders.
				__( '%d件の注文を追加しました。', 'hanmoto' ),
				$result['added']
			);
			if ( $result['skipped'] ) {
				$message .= sprintf(
					// translators: %d is the number of orders.
					__( '（%d件はすでに入っていたので飛ばしました）', 'hanmoto' ),
					$result['skipped']
				);
			}
		}
		return new \WP_REST_Response( [
			'message' => $message,
			'orders'  => $model->get_order_summaries( $fax_id ),
			'stats'   => $model->get_stats( $fax_id ),
		] );
	}
}
