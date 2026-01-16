<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Models\ModelDelivery;
use Hametuha\HanmotoHelper\Models\ModelEvent;
use Hametuha\HanmotoHelper\Models\ModelInventory;
use Hametuha\HanmotoHelper\Models\ModelItem;
use Hametuha\HanmotoHelper\Models\ModelOrder;
use Hametuha\HanmotoHelper\Models\ModelSupplier;
use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Rest\RestBookSearch;
use Hametuha\HanmotoHelper\Rest\RestInventoryStats;
use Hametuha\HanmotoHelper\Rest\RestOrderRegister;
use Hametuha\HanmotoHelper\Rest\RestShopSearch;
use Hametuha\HanmotoHelper\UI\CsvImporter;
use Hametuha\HanmotoHelper\UI\ItemsList;
use Hametuha\HanmotoHelper\UI\StatisticHandler;

/**
 * Order manager.
 *
 * @package hanmoto
 */
class OrderManager extends Singleton {

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		if ( ! get_option( 'hanmoto_use_order_manager' ) ) {
			// Do nothing.
			return;
		}
		// Inventory.
		ModelEvent::get_instance();
		ModelInventory::get_instance();
		ModelDelivery::get_instance();
		ModelSupplier::get_instance();
		ModelOrder::get_instance();
		// Importer
		CsvImporter::get_instance();
		// REST API
		RestInventoryStats::get_instance();
		RestShopSearch::get_instance();
		RestBookSearch::get_instance();
		RestOrderRegister::get_instance();
		// Register Screen
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 20 );
	}

	/**
	 * 注文登録画面を追加する
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page(
		'edit.php?post_type=book-shop-order',
		__( '注文・返品登録', 'hanmoto' ),
			__( '注文・返品登録', 'hanmoto' ),
			'edit_posts',
			'books-shop-order-register',
			[ $this, 'render_menu' ]
		);
	}

	/**
	 * 管理バーに登録する
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar
	 * @return void
	 */
	public function admin_bar( &$wp_admin_bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$wp_admin_bar->add_node( [
			'parent' => 'site-name',
			'id'     => 'book-shop-order',
			'title'  => __( '注文・返品登録', 'woocommerce' ),
			'href'   => admin_url( 'edit.php?post_type=book-shop-order&page=books-shop-order-register' ),
		] );
	}

	/**
	 * 注文登録画面を書く
	 *
	 * @return void
	 */
	public function render_menu() {
		?>
		<div class="wrap">
			<h2><?php esc_html_e( '注文・返品登録', 'hanmoto' ); ?></h2>
			<p style="margin-bottom: 3em;"><?php esc_html_e( '返品の場合は数値を-1にしてください。', 'hanmoto' ); ?></p>
			<div id="shop-order-register-form">
			</div>
		</div>
		<?php
		wp_enqueue_style( 'hanmoto-shop-order-register' );
		wp_enqueue_script( 'hanmoto-shop-order-register' );
		wp_localize_script( 'hanmoto-shop-order-register', 'HanmotoOrderRegister', $this->get_script_data() );
	}

	/**
	 * スクリプトに渡すデータを取得する
	 *
	 * @return array
	 */
	private function get_script_data() {
		// 受注元（source）タクソノミーを取得
		$sources = get_terms( [
			'taxonomy'   => 'source',
			'hide_empty' => false,
		] );
		$source_options = [];
		if ( ! is_wp_error( $sources ) ) {
			foreach ( $sources as $source ) {
				$source_options[] = [
					'value' => $source->name,
					'label' => $source->name,
				];
			}
		}

		return [
			'restBase'    => rest_url( 'hanmoto/v1' ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'sources'     => $source_options,
			'defaultDate' => gmdate( 'Y-m-d' ),
		];
	}
}
