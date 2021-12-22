<?php

namespace Hametuha\HanmotoHelper\Services\Utilities;


use Hametuha\HanmotoHelper\Controller\WooCommerceHelper;
use Hametuha\HanmotoHelper\Services\WooCommerceOrder;

/**
 * Access helper.
 *
 * @package hanmoto
 *
 * @property-read WooCommerceHelper $helper
 * @property-read WooCommerceOrder  $order
 */
trait Accessor {

	/**
	 * Getter.
	 *
	 * @param string $name Name pof property.
	 * @return mixed
	 */
	public function __get( $name ) {
		switch( $name ) {
			case 'helper':
				return WooCommerceHelper::get_instance();
			case 'order':
				return WooCommerceOrder::get_instance();
			default:
				return null;
		}
	}
}
