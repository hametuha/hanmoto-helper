<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Services\WooCommerceOrder;
use Hametuha\HanmotoHelper\Services\WoocommerceSetting;
use Hametuha\HanmotoHelper\Services\WooCommerceTemplate;
use Hametuha\HanmotoHelper\Utility\OpenDbApi;

/**
 * WooCommerce Helper.
 *
 * @package hanmoto
 */
class WooCommerceHelper extends Singleton {

	use OpenDbApi;

	const META_KEY_RESALE_RATE = 'hanmoto_retail_rate';

	/**
	 * @inheritDoc
	 */
	protected function init() {
		// Register hooks for meta box.
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'render_product_tab' ] );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_meta' ] );
		add_action( 'admin_enqueue_scripts', function() {
			wp_enqueue_style( 'hanmoto-woocommmerce-admin' );
		} );
		// Setting fields.
		WooCommerceSetting::get_instance();
		// Template hooks.
		WooCommerceTemplate::get_instance();
		// Order page.
		WooCommerceOrder::get_instance();
	}

	/**
	 * Get global ratio.
	 *
	 * @return float
	 */
	public function get_global_rate() {
		return (int) get_option( 'hanmoto_global_retail_rate', 70 );
	}

	/**
	 * Get product rate.
	 *
	 * @param null|int|\WC_Product $product Product.
	 * @return float
	 */
	public function get_product_rate( $product = null ) {
		$product = wc_get_product( $product );
		$meta    = get_post_meta( $product->id, self::META_KEY_RESALE_RATE, true );
		$rate    = $this->get_global_rate();
		if ( $meta && is_numeric( $meta ) ) {
			$rate = (float) $meta;
		}
		return min( $rate, 100 );
	}

	/**
	 * Is product can order?
	 *
	 * @param int|\WC_Product|null $product Product
	 * @return bool
	 */
	public function product_can_order( $product = null ) {
		$product = wc_get_product( $product );
		return 'yes' === get_post_meta( $product->id, 'hanmoto_book_shop_can_order', true );
	}

	/**
	 * Get product tabs.
	 *
	 * @param array $tabs
	 * @return array
	 */
	public function product_tab( $tabs ) {
		$tabs['bibliography'] = [
			'label'    => __( '書誌情報', 'hanmoto' ),
			'target'   => 'bibliography_product_data',
			'priority' => 30,
		];
		return $tabs;
	}

	/**
	 * Save meta data.
	 *
	 * @param \WC_Product $product
	 */
	public function save_meta( $product ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hanmotowcnonce' ), 'hanmoto_product_meta' ) ) {
			return;
		}
		foreach ( [
			PostType::META_KEY_ISBN,
			'hanmoto_authors',
			'hanmoto_published_at',
			'hanmoto_publisher',
			'hanmoto_pages',
			'hanmoto_sync_with_openbd',
			'hanmoto_book_shop_can_order',
			self::META_KEY_RESALE_RATE,
			'hanmoto_order_site',
			'hanmoto_order_url',
			'hanmoto_order_sheet',
			'hanmoto_promotion_tools',
		] as $key ) {
			$value = filter_input( INPUT_POST, $key );
			switch ( $key ) {
				case 'hanmoto_sync_with_openbd':
				case 'hanmoto_book_shop_can_order':
					if ( 'yes' === $value ) {
						update_post_meta( $product->id, $key, $value );
					} else {
						delete_post_meta( $product->id, $key );
					}
					break;
				default:
					update_post_meta( $product->id, $key, $value );
					break;
			}
		}
	}

	/**
	 * Render product object.
	 *
	 * @global \WC_Product $product_object
	 * @return void
	 */
	public function render_product_tab() {
		global $product_object;
		wp_enqueue_script( 'hanmoto-product-helper' );
		wp_enqueue_style( 'hanmoto-product' );
		wp_nonce_field( 'hanmoto_product_meta', '_hanmotowcnonce', false );
		?>
		<div id="bibliography_product_data" class="panel woocommerce_options_panel">

			<div class="options_group">
				<?php
				woocommerce_wp_text_input( [
					'id'          => PostType::META_KEY_ISBN,
					'value'       => get_post_meta( $product_object->id, PostType::META_KEY_ISBN, true ),
					'label'       => __( 'ISBN', 'hanmoto' ),
					'type'        => 'text',
					'placeholder' => 'e.g. 9784905197027',
				] );

				woocommerce_wp_text_input( [
					'id'          => 'hanmoto_authors',
					'value'       => get_post_meta( $product_object->id, 'hanmoto_authors', true ),
					'label'       => __( '著者', 'hanmoto' ),
					'type'        => 'text',
					'placeholder' => 'e.g. 高橋文樹',
				] );

				woocommerce_wp_text_input( [
					'id'          => 'hanmoto_publisher',
					'value'       => get_post_meta( $product_object->id, 'hanmoto_publisher', true ),
					'label'       => __( '出版者', 'hanmoto' ),
					'type'        => 'text',
					'placeholder' => 'e.g. 破滅派',
				] );

				woocommerce_wp_text_input( [
					'id'    => 'hanmoto_published_at',
					'value' => get_post_meta( $product_object->id, 'hanmoto_published_at', true ),
					'label' => __( '発行日', 'hanmoto' ),
					'type'  => 'date',
				] );

				woocommerce_wp_text_input( [
					'id'          => 'hanmoto_pages',
					'value'       => get_post_meta( $product_object->id, 'hanmoto_pages', true ),
					'type'        => 'number',
					'label'       => __( 'ページ数', 'hanmoto' ),
					'placeholder' => 'e.g. 304',
				] );

				woocommerce_wp_checkbox( [
					'id'          => 'hanmoto_sync_with_openbd',
					'label'       => __( 'OpenBD同期', 'hanmoto' ),
					'description' => __( '書誌情報とコンテンツをOpenBDから同期する', 'hanmoto' ),
				] );
				?>
				<div class="clear">
					<p>
						<button id="hanmoto-fill-product" class="button">
							<?php esc_html_e( 'ISBNを元に自動入力', 'hanmoto' ); ?>
						</button>
					</p>
				</div>
			</div>

			<div class="options_group">
				<?php

				woocommerce_wp_checkbox( [
					'id'          => 'hanmoto_book_shop_can_order',
					'label'       => __( '書店注文', 'hanmoto' ),
					'description' => __( '書店注文を受け付ける', 'hanmoto' ),
				] );

				woocommerce_wp_text_input( [
					'id'          => 'hanmoto_order_sheet',
					'value'       => get_post_meta( $product_object->id, 'hanmoto_order_sheet', true ),
					'type'        => 'url',
					'label'       => __( '注文書URL', 'hanmoto' ),
					'placeholder' => 'e.g. https://example.com/fax.pdf',
				] );

				woocommerce_wp_textarea_input( [
					'id'    => 'hanmoto_promotion_tools',
					'value' => get_post_meta( $product_object->id, 'hanmoto_promotion_tools', true ),
					'label' => __( '販促ツールなど', 'hanmoto' ),
				] );

				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get product data.
	 *
	 * @return array|null
	 */
	public function get_product_book( $product = null ) {
		$product = wc_get_product( $product );
		$isbn    = get_post_meta( $product->id, PostType::META_KEY_ISBN, true );
		if ( ! $isbn ) {
			return null;
		}
		$books = $this->openbd_get( $isbn );
		if ( empty( $books ) || is_wp_error( $books ) ) {
			return null;
		}
		return $books[0];
	}

	/**
	 * Get capture date.
	 *
	 * @return int
	 */
	public function get_capture_date() {
		return (int) get_option( 'hanmoto_capture_date', 30 );
	}
}
