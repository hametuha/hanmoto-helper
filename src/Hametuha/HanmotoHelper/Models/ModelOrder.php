<?php

namespace Hametuha\HanmotoHelper\Models;


use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\BookSelector;

/**
 * Get order manager.
 *
 * @package hanmoto
 */
class ModelOrder extends Singleton {

	use BookSelector;

	/**
	 * Post type name.
	 *
	 * @return string
	 */
	public static function post_type() {
		return 'book-shop-order';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_action( 'init', [ $this, 'register_posts' ] );
		add_action( 'add_meta_boxes', [ $this, 'order_meta_box' ] );
		add_action( 'edit_form_after_title', [ $this, 'editor_title' ] );
		add_action( 'save_post_' . self::post_type(), [ $this, 'save_shop_order' ], 10, 2 );
		$this->admin_columns( self::post_type() );
	}

	/**
	 * Register post type.
	 */
	public function register_posts() {
		// Register post type.
		register_post_type( self::post_type(), [
			'label'             => __( '書店注文', 'hanmoto' ),
			'public'            => false,
			'show_ui'           => true,
			'show_in_nav_menu'  => false,
			'show_in_admin_bar' => false,
			'menu_icon'         => 'dashicons-calculator',
			'menu_position'     => 81,
			'supports'          => [ 'author', 'excerpt' ],
		] );
		// Register order source.
		register_taxonomy( 'source', [ self::post_type() ], [
			'public'            => false,
			'show_ui'           => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'label'             => __( '注文経路', 'hanmoto' ),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'meta_box_cb'       => $this->hierarchical_radio( 'source', __( '注文経路', 'hanmoto' ) ),
		] );

	}

	/**
	 * Render editor title.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function editor_title( $post ) {
		if ( self::post_type() !== $post->post_type ) {
			return;
		}
		printf(
			'<h1>%s <code style="font-size: inherit">#%d</code></h1>',
			esc_html__( '書店注文', 'hanmoto' ),
			esc_html( $post->ID )
		);
	}

	/**
	 * Register meta box.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function order_meta_box( $post_type ) {
		if ( self::post_type() !== $post_type ) {
			return;
		}
		add_meta_box( 'shop-order-meta-box', __( '書店注文詳細', 'hanmoto' ), [ $this, 'render_meta_box' ], $post_type, 'normal', 'high' );
	}

	/**
	 * Save post object.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function save_shop_order( $post_id, $post ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hanmotoordernonce' ), 'update_book_shop_order' ) ) {
			return;
		}
		foreach ( [ 'amount', 'in_charge_of', 'shipped_at' ] as $key ) {
			update_post_meta( $post_id, '_' . $key, filter_input( INPUT_POST, $key ) );
		}
	}

	/**
	 * Render meta box content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'update_book_shop_order', '_hanmotoordernonce', false );
		?>
		<p>
			<label style="margin-right: 10px; display: inline-block;">
				<?php esc_html_e( '商品', 'hanmoto' ); ?>
				<br />
				<?php $this->book_select_pull_down( $post->post_parent ); ?>
			</label>

			<label style="display: inline-block;">
				<?php esc_html_e( '数量', 'hanmoto' ); ?>
				<br />
				<input type="number" name="amount" value="<?php echo esc_attr( get_post_meta( $post->ID, '_amount', true ) ); ?>" />
			</label>
		</p>
		<p>
			<label style="display: inline-block;">
				<?php esc_html_e( '搬入日', 'hanmoto' ); ?>
				<br />
				<input type="date" name="shipped_at" value="<?php echo esc_attr( get_post_meta( $post->ID, '_shipped_at', true ) ); ?>" />
			</label>
		</p>
		<p>
			<label>
				<?php esc_html_e( '担当者', 'hanmoto' ); ?>
				<br />
				<input type="text" name="in_charge_of" value="<?php echo esc_attr( get_post_meta( $post->ID, '_in_charge_of', true ) ); ?>"
					placeholder="<?php esc_attr_e( '例・破滅太郎様', 'hanmoto' ); ?>" />
			</label>
		</p>
		<?php
	}

}
