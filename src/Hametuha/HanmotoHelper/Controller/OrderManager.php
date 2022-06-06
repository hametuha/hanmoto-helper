<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Models\ModelDelivery;
use Hametuha\HanmotoHelper\Models\ModelInventory;
use Hametuha\HanmotoHelper\Models\ModelItem;
use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\UI\ItemsList;

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
		ModelInventory::get_instance();
		ModelDelivery::get_instance();
	}
}
