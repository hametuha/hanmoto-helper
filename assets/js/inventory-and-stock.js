/*!
 * Inventory and Stock management
 *
 * @handle hanmoto-inventory-stock
 * @deps wp-element, wp-api-fetch, wp-i18n, wp-components
 */

const { useState } = wp.element;
const { Button } = wp.components;
const { __, sprintf } = wp.i18n;

const InventoryAndStock = ( props ) => {
	const { inventory, onChange } = props;
	const [ loading, setLoading ] = useState( false, [] );
	if ( inventory.applied_at ) {
		return (
			<span style={ { color: 'lightgrey' } }>{ sprintf( __( '%sに在庫反映済み', 'hametuha' ), inventory.applied_at ) }</span>
		);
	}
	return (
		<Button isSecondary disabled={ loading } isSmall onClick={ ( e ) => {
			setLoading( true );
			onChange( inventory );
		} }>{ __( '在庫に反映', 'hanmoto' ) }</Button>
	);
};

if ( ! window.hanmoto ) {
	window.hanmoto = {};
}
hanmoto.InventoryAndStock = InventoryAndStock;
