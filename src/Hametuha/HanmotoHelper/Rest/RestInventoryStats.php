<?php

namespace Hametuha\HanmotoHelper\Rest;


use Hametuha\HanmotoHelper\Models\ModelInventory;
use Hametuha\HanmotoHelper\Pattern\RestApiPattern;
use Hametuha\HanmotoHelper\Utility\Validator;

/**
 * Inventory stats API.
 *
 * @package hanmoto
 */
class RestInventoryStats extends RestApiPattern {

	use Validator;

	/**
	 * {@inheritdoc}
	 */
	protected function route() {
		return 'stats/inventory/(?P<post_id>\d+)/?';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_arguments( $method ) {
		return [
			'post_id' => [
				'required'          => true,
				'type'              => 'int',
				'validate_callback' => function( $var ) {
					return (bool) get_post( $var );
				},
			],
			'start' => [
				'required'          => false,
				'type'              => 'string',
				'validate_callback' => [ $this, 'is_date_or_empty' ],
				'default'           => '',
			],
			'end' => [
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'validate_callback' => [ $this, 'is_date_or_empty' ],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function callback( $request ) {
		$post_id = $request->get_param( 'post_id' );
		$start   = $request->get_param( 'start' );
		$end     = $request->get_param( 'end' );
		// Get current stock.
		$total   = ModelInventory::get_instance()->get_stock( $post_id, $start );
		$changes = ModelInventory::get_instance()->get_inventory_changes( $post_id, $start, $end );
		if ( is_wp_error( $changes ) ) {
			return $changes;
		}
		$subtotal = $total;
		foreach ( $changes as &$change ) {
			$subtotal += $change['amount'];
			$change['subtotal'] = $subtotal;
		}

		// Return results.
		return new \WP_REST_Response( $changes );
	}

	/**
	 * {@inheritdoc}
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'edit_others_posts' );
	}
}
