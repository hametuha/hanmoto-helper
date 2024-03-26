<?php

namespace Hametuha\HanmotoHelper\Models;

use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * 取引イベントを登録するモデル
 *
 * @package hanmoto
 */
class ModelEvent extends Singleton {

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		// Register Post Type
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		// Add product selector.
		add_action( 'rest_api_init', [ $this, 'rest_api' ], 1 );
	}

	/**
	 * Register post type.
	 *
	 * @return void
	 */
	public function register_post_types() {
		register_post_type( 'inventory-event', [
			'label'             => __( '取引イベント', 'hanmoto' ),
			'has_archive'       => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_nav_menus' => false,
			'show_in_admin_bar' => false,
			'menu_position'     => 80,
			'menu_icon'         => 'dashicons-database',
			'supports'          => [ 'title', 'author', 'excerpt' ],
		] );
	}

	/**
	 * Returns product list.
	 *
	 * @return void
	 */
	public function rest_api() {
		//Product list(for API use)
		register_rest_route( 'hanmoto/v1', 'products', [
			[
				'methods'             => 'GET',
				'args'                => [
					's' => [
						'type'        => 'string',
						'description' => __( '検索語句', 'hanmoto' ),
						'default'     => '',
					],
				],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => function ( \WP_REST_Request $reqeust ) {
					$args = [
						'post_type'      => 'product',
						'post_status'    => 'any',
						'posts_per_page' => - 1,
					];
					if ( $reqeust->get_param( 's' ) ) {
						$args['s'] = $reqeust->get_param( 's' );
					}
					$query = new \WP_Query( $args );
					return new \WP_REST_Response( array_map( function( \WP_Post $post ) {
						return [
							'id'    => $post->ID,
							'name'  => get_the_title( $post ),
							'price' => wc_get_product( $post )->get_price(),
						];
					}, $query->posts ) );
				},
			],
		] );
		// Inventory list
		$arg_post_id = [
			'type'                => 'int',
			'description'         => __( '取引イベント', 'hanmoto' ),
			'required'            => true,
			'validation_callback' => function( $id ) {
				return ( get_post( $id ) && 'inventory-event' === get_post_type( $id ) );
			},
		];
		$permission_callback = function() {
			return current_user_can( 'edit_posts' );
		};
		register_rest_route( 'hanmoto/v1', 'inventories/(?P<post_id>\d+)', [
			[
				'methods'             => 'GET',
				'args'                => [
					'post_id' => $arg_post_id,
				],
				'permission_callback' => $permission_callback,
				'callback'            => function( \WP_REST_Request $request ) {
					$query = new \WP_Query( [
						'post_type'      => 'inventory',
						'posts_per_page' => 200,
						'post_status'    => 'any',
						'meta_query'     => [
							[
								'key'   => '_group',
								'value' => $request->get_param( 'post_id' ),
							],
						],
					] );
					return new \WP_REST_Response( array_map( function( $post ) {
						return ModelInventory::get_instance()->to_rest_response( $post );
					}, $query->posts ) );
				},
			],
			[
				'methods'             => 'POST',
				'args'                => [
					'post_id'          => $arg_post_id,
					'id'               => [
						'type'                => 'int',
						'description'         => __( '商品ID', 'hanmoto' ),
						'required'            => true,
						'validation_callback' => function ( $id ) {
							return ( get_post( $id ) && 'product' === get_post_type( $id ) );
						},
					],
					'price'            => [
						'type'                => 'int',
						'description'         => __( '単価', 'hanmoto' ),
						'required'            => true,
						'validation_callback' => function ( $val ) {
							return 0 <= $val;
						},
					],
					'amount'           => [
						'type'                => 'int',
						'description'         => __( '数量', 'hanmoto' ),
						'required'            => true,
						'validation_callback' => function ( $val ) {
							return is_numeric( $val );
						},
					],
					'margin'           => [
						'type'                => 'int',
						'description'         => __( '料率', 'hanmoto' ),
						'required'            => true,
						'validation_callback' => function ( $val ) {
							return 0 <= $val && 100 >= $val;
						},
					],
					'tax'              => [
						'type'                => 'int',
						'description'         => __( '消費税', 'hanmoto' ),
						'required'            => true,
						'validation_callback' => function ( $val ) {
							return 0 <= $val && 100 >= $val;
						},
					],
					'paid_at'          => [
						'type'                => 'int',
						'description'         => __( '清算日', 'hanmoto' ),
						'required'            => true,
						'validation_callback' => function ( $val ) {
							return 0 <= $val;
						},
					],
					'transaction_type' => [
						'type'                => 'int',
						'description'         => __( '取引種別', 'hanmoto' ),
						'default'             => 0,
						'validation_callback' => function ( $term_id ) {
							if ( 0 === $term_id ) {
								return true;
							}
							// Should exist in taxonomy.
							$term = get_term( $term_id, 'transaction_type' );

							return ( ! $term || is_wp_error( $term ) ) ? $term : true;
						},
					],
				],
				'permission_callback' => $permission_callback,
				'callback'            => function ( \WP_REST_Request $reqeust ) {
					$parent  = get_post( $reqeust->get_param( 'post_id' ) );
					$post_id = wp_insert_post( [
						'post_type'   => 'inventory',
						'post_status' => 'publish',
						'post_title'  => get_the_title( $parent ),
						'post_parent' => $reqeust->get_param( 'id' ),
						'post_date'   => $parent->post_date,
					], true );
					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}
					$taxonomies       = [ 'supplier' ];
					$transaction_type = $reqeust->get_param( 'transaction_type' );
					if ( $transaction_type ) {
						wp_set_object_terms( $post_id, [ $transaction_type ], 'transaction_type' );
					} else {
						$taxonomies[] = 'transaction_type';
					}
					foreach ( $taxonomies as $taxonomy ) {
						$terms = get_the_terms( $parent, $taxonomy );
						if ( $terms && ! is_wp_error( $terms ) ) {
							wp_set_object_terms( $post_id, array_map( function( $term ) {
								return $term->term_id;
							}, $terms ), $taxonomy );
						}
					}
					foreach ( [
						'group'      => $parent->ID,
						'unit_price' => $reqeust->get_param( 'price' ),
						'margin'     => $reqeust->get_param( 'margin' ),
						'amount'     => $reqeust->get_param( 'amount' ),
						'vat'        => $reqeust->get_param( 'tax' ),
					] as $key => $val ) {
						update_post_meta( $post_id, '_' . $key, $val );
					}
					$date = new \DateTime( $parent->post_date, wp_timezone() );
					if ( $reqeust->get_param( 'paid_at' ) ) {
						$date->add( new \DateInterval( 'P%dM' ) );
					}
					update_post_meta( $post_id, '_capture_at', $date->format( 'Y-m-t' ) );
					$response = ModelInventory::get_instance()->to_rest_response( $post_id );
					return is_wp_error( $response ) ? $response : new \WP_REST_Response( $response );
				},
			],
			[
				'methods'             => 'PUT',
				'args'                => [
					'post_id'          => $arg_post_id,
					'ids'              => [
						'type'                => 'string',
						'description'         => __( '在庫変動IDのカンマ区切り形式', 'hanmoto' ),
						'required'            => true,
						'validation_callback' => function ( $ids ) {
							if ( ! preg_match( '/^[0-9,]+$/u', $ids ) ) {
								return false;
							}
							$ids = explode( ',', $ids );
							$valid = array_filter( $ids, function( $id ) {
								return get_post( $id ) && 'inventory' === get_post_type( $id );
							} );
							return count( $ids ) && count( $valid );
						},
					],
				],
				'permission_callback' => $permission_callback,
				'callback'            => function ( \WP_REST_Request $request ) {
					// Check if all events are valid.
					$ids   = explode( ',', $request->get_param( 'ids' ) );
					$event = $request->get_param( 'post_id' );
					$total = 0;
					foreach ( $ids as $id ) {
						$total += update_post_meta( $id, '_group', $event ) ? 1 : 0;
					}
					return new \WP_REST_Response( [
						'updated' => $total,
						'should'  => count( $ids ),
					] );
				},
			]
		] );
	}

	/**
	 * Register meta box
	 *
	 * @return void
	 */
	public function add_meta_box( $post_type ) {
		if ( 'inventory-event' !== $post_type ) {
			return;
		}
		add_meta_box( 'inventories', __( '在庫変動', 'hanmoto' ), [ $this, 'render_meta_box' ], $post_type, 'advanced' );
	}

	/**
	 * Render meta box.
	 *
	 * @param \WP_Post $post Current meta box.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ) {
		$current_transaction = get_the_terms( $post, 'transaction_type' );
		$current_supplier    = get_the_terms( $post, 'supplier' );
		if ( ! $current_supplier || ! $current_transaction || 'publish' !== $post->post_status ) {
			printf(
				'<p class="descrioption">%s</p>',
				esc_html__( '取引イベントの日時を設定・公開した上で、取引先・取引種別を登録してください。', 'hanmoto' )
			);
			return;
		}
		wp_enqueue_style( 'hanmoto-inventory' );
		wp_enqueue_script( 'hanmoto-inventory-helper' );
		wp_localize_script( 'hanmoto-inventory-helper', 'InventoryHelper', [
			'transactions'        => array_map(
				function ( $term ) {
					return [
						'id'   => $term->term_id,
						'name' => $term->name,
					];
				},
				get_terms( [
					'taxonomy'   => 'transaction_type',
					'hide_empty' => false,
				] )
			),
			'current_transaction' => $current_transaction[0]->term_id,
			'suppliers'           => array_map(
				function ( $term ) {
					return [
						'id'     => $term->term_id,
						'name'   => $term->name,
						'parent' => $term->parent,
					];
				},
				get_terms( [
					'taxonomy'   => 'supplier',
					'hide_empty' => false,
				] )
			),
			'current_supplier'    => $current_supplier[0]->term_id,
		] );
		?>
		<div id="hanmoto-inventories" data-post-id="<?php echo esc_attr( $post->ID ); ?>"></div>
		<?php
	}
}
