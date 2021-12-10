<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * WooCommerce Helper.
 *
 * @package hanmoto
 */
class WooCommerceHelper extends Singleton {

	/**
	 * @inheritDoc
	 */
	protected function init() {
		// Register hooks.
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_tab' ] );
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
}
