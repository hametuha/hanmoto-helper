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
	 * {@inheritDoc}
	 */
	protected function init() {
		parent::init();
		add_action( 'init', function() {
			add_rewrite_rule( 'stock/of/(\d+)/?$', 'index.php?post_type=product&p=$matches[1]&hanmoto-stats=book', 'top' );
		} );
		add_filter( 'query_vars', function( $vars ) {
			$vars[] = 'hanmoto-stats';
			return $vars;
		}  );
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ] );
	}

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
					return get_post( $var ) && ( 'product' === get_post_type( $var ) );
				},
			],
			'password' => [
				'required' => true,
				'type'     => 'string',
				'validate_callback' => function( $var, \WP_REST_Request $request ) {
					return ModelInventory::is_password_valid_for( $request->get_param( 'post_id' ), $var );
				},
			],
			'start'   => [
				'required'          => false,
				'type'              => 'string',
				'validate_callback' => [ $this, 'is_date_or_empty' ],
				'default'           => '',
			],
			'end'     => [
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
			$subtotal          += $change['amount'];
			$change['subtotal'] = $subtotal;
		}

		// Return results.
		return new \WP_REST_Response( $changes );
	}

	/**
	 * {@inheritdoc}
	 */
	public function permission_callback( $request ) {
		return true;
	}

	/**
	 * Hijack hanmoto stats.
	 *
	 * @param \WP_Query $query
	 * @return void
	 */
	public function pre_get_posts( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->get( 'hanmoto-stats' )  ) {
			return;
		}
		// Check if password is valid.
		$password = filter_input( INPUT_GET, 'pw' );
		if ( is_wp_error( ModelInventory::is_password_valid_for( $query->get( 'p' ), $password ) ) ) {
			// Do nothing.
			return;
		}
		// locate template and die.
		nocache_headers();
		add_filter( 'template_include', function( $template ) {
			return apply_filters( 'hanmoto_stats_template', hanmoto_root_dir() . '/template-parts/hanmoto/stats.php' );
		} );
	}
}
