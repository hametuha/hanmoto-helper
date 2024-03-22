<?php

namespace Hametuha\HanmotoHelper\Models;


use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * Delivery models.
 *
 *
 */
class ModelDelivery extends Singleton {

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'admin_head', [ $this, 'enqueue_assets' ] );
		add_filter('bulk_actions-edit-inventory', function( $bulk_actions ) {
			$bulk_actions['make-delivery-of-goods'] = __( '納品書を作成', 'hanmoto' );
			return $bulk_actions;
		});
		add_action( 'rest_api_init', [ $this, 'register_apis' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_delivery-of-goods', [ $this, 'save_post' ], 10, 2 );
		add_filter( 'template_include', [ $this, 'override_template' ] );
	}

	/**
	 * Register post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		register_post_type( 'delivery-of-goods', [
			'label'             => __( '納品書', 'hanmoto' ),
			'supports'          => [ 'title', 'author', 'excerpt' ],
			'public'            => current_user_can( 'edit_others_posts' ),
			'show_in_nav_menu'  => false,
			'show_in_admin_bar' => false,
			'show_in_menu'      => 'edit.php?post_type=inventory-event',
			'capability_type'   => 'post',
			'map_meta_cap'      => true,
			'capabilities'      => [
				'create_posts' => 'create_delivery_of_goods',
			],
		] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( 'edit-inventory' !== $screen->id ) {
			return;
		}
		wp_enqueue_script( 'hanmoto-delivery-helper' );
	}

	/**
	 * Register APIs.
	 *
	 * @return void
	 */
	public function register_apis() {
		register_rest_route( 'hanmoto/v1', 'delivery-of-goods', [
			[
				'methods'             => 'POST',
				'args'                => [
					'ids' => [
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => function( $vars ) {
							$ids = array_map( 'intval', array_filter( explode( ',', $vars ), 'is_numeric' ) );
							if ( empty( $ids ) ) {
								return false;
							}
							$query = $this->get_inventories( $ids );
							return count( $ids ) === $query->found_posts;
						},
					],
				],
				'permission_callback' => function( $request ) {
					return current_user_can( 'edit_others_posts' );
				},
				'callback'            => function( $request ) {
					$query   = $this->get_inventories( $request->get_param( 'ids' ) );
					$post_id = wp_insert_post( [
						'post_type'   => 'delivery-of-goods',
						'post_status' => 'draft',
						'post_title'  => '納品書' . date_i18n( __( 'Y年n月d日', 'hanmoto' ) ),
						'author'      => get_current_user_id(),
					] );
					if ( ! $post_id ) {
						return new \WP_Error( 'bad_reuqest', __( '納品書の作成に失敗しました。', 'hanmoto' ) );
					}
					foreach ( $query->posts as $id ) {
						add_post_meta( $post_id, '_inventory', $id->ID );
					}
					return new \WP_REST_Response( [
						'message'  => __( '納品書のドラフトを作成しました。', 'hanmoto' ),
						'id'       => $post_id,
						'edit_url' => get_edit_post_link( $post_id, 'display' ),
					] );
				},
			],
		] );
	}

	/**
	 * Get inventories.
	 *
	 * @param string|int[] $ids
	 *
	 * @return \WP_Query
	 */
	public function get_inventories( $ids ) {
		if ( ! is_array( $ids ) ) {
			$ids = explode( ',', $ids );
		}
		if ( empty( $ids ) ) {
			return null;
		}
		return new \WP_Query( [
			'post_type' => 'inventory',
			'post__in'  => $ids,
			'orderby'   => [ 'date' => 'ASC' ],
		] );
	}

	/**
	 * Register meta box.
	 *
	 * @param string $post_type
	 *
	 * @return void
	 */
	public function add_meta_boxes( $post_type ) {
		if ( 'delivery-of-goods' !== $post_type ) {
			return;
		}
		add_meta_box( 'delivery-of-goods-meta', __( '納品情報', 'hanmoto' ), function( $post ) {
			wp_nonce_field( 'update_delivery', '_hanmotononce', false );
			?>
			<p>
				<label>
					<?php esc_html_e( '発行日', 'hanmoto' ); ?><br />
					<input type="date" name="issued_at" value="<?php echo esc_attr( get_post_meta( $post->ID, '_issued_at', true ) ); ?>"
						placeholder="<?php echo esc_attr( $post->post_date ); ?>"/>
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( '発行者', 'hanmoto' ); ?><br />
					<input class="regular-text" type="text" name="issued_by" value="<?php echo esc_attr( get_post_meta( $post->ID, '_issued_by', true ) ); ?>"
						placeholder="<?php echo esc_attr( get_option( 'hanmoto_issued_by' ) ); ?>"/>
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( '担当者', 'hanmoto' ); ?><br />
					<input class="regular-text" type="text" name="issue_owner" value="<?php echo esc_attr( get_post_meta( $post->ID, '_issue_owner', true ) ); ?>"
						placeholder="<?php echo esc_attr( get_option( 'hanmoto_issue_owner' ) ); ?>"/>
				</label>
			</p>
			<?php
			$inventories = get_post_meta( $post->ID, '_inventory' );
			$query       = $this->get_inventories( $inventories );
			if ( ! $query->have_posts() ) {
				printf(
					'<div class="notice-error"><p>%s</p></div>',
					esc_html__( '在庫情報が紐付けられていません。', 'hanmoto' )
				);
				return;
			}
			?>
			<ol>
				<?php foreach ( $query->posts as $inventory ) : ?>
				<li>
					<strong>
						<?php echo esc_html( get_the_title( $inventory ) ); ?>
					</strong>
					<span><?php echo mysql2date( __( 'Y年m月d日', 'hanmoto' ), $post->post_date ); ?></span>
					<br />
					<?php
					$total = ModelInventory::get_instance()->get_total( $inventory );
					echo ModelInventory::get_instance()->color_price( $total );
					?>
				</li>
				<?php endforeach; ?>
			</ol>
			<?php
		} );
	}

	/**
	 * Save metadata.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function save_post( $post_id, $post ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hanmotononce' ), 'update_delivery' ) ) {
			return;
		}
		foreach ( [ 'issued_at', 'issued_by', 'issue_owner' ] as $key ) {
			update_post_meta( $post_id, '_' . $key, filter_input( INPUT_POST, $key ) );
		}

	}

	/**
	 * Get template.
	 *
	 * @param $template
	 * @return void
	 */
	public function override_template( $template ) {
		if ( is_singular( 'delivery-of-goods' ) ) {
			$template = hanmoto_root_dir() . '/template-parts/hanmoto/delivery-of-goods.php';
		}
		return $template;
	}
}
