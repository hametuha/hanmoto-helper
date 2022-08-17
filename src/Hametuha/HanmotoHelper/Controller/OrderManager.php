<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Models\ModelDelivery;
use Hametuha\HanmotoHelper\Models\ModelInventory;
use Hametuha\HanmotoHelper\Models\ModelItem;
use Hametuha\HanmotoHelper\Models\ModelOrder;
use Hametuha\HanmotoHelper\Models\ModelSupplier;
use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Rest\RestInventoryStats;
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
		ModelInventory::get_instance();
		ModelDelivery::get_instance();
		ModelSupplier::get_instance();
		ModelOrder::get_instance();
		// Importer
		CsvImporter::get_instance();
		// REST API
		RestInventoryStats::get_instance();
	}
}
