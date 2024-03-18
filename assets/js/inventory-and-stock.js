/*!
 * Inventory and Stock management
 *
 * @handle hanmoto-inventory-stock
 * @deps wp-element, wp-api-fetch, wp-i18n, wp-components
 */

const { Button } = wp.components;
const { __ } = wp.i18n;

const InventoryAndStock = ( props ) => {
	return (
		<Button>{ __( '在庫に反映', 'hanmoto' ) }</Button>
	);
};

if ( ! window.hanmoto ) {
	window.hanmoto = {};
}
hanmoto.InventoryAndStock = InventoryAndStock;
