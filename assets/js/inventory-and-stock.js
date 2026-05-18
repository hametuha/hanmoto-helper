/*!
 * Inventory and Stock management
 *
 * @handle hanmoto-inventory-stock
 * @deps wp-element, wp-api-fetch, wp-i18n, wp-components
 */

const { useState, useEffect } = wp.element;
const { Button, SelectControl, Flex, FlexItem } = wp.components;
const { __, sprintf } = wp.i18n;

const InventoryAndStock = ( props ) => {
	const { inventory, onChange } = props;
	const [ loading, setLoading ] = useState( false );
	const [ appliedAt, setAppliedAt ] = useState( inventory.applied_at );
	const [ variations, setVariations ] = useState( null );
	const [ selectedVariationId, setSelectedVariationId ] = useState( 0 );

	const isVariable = 'variable' === inventory.product_type;

	useEffect( () => {
		if ( appliedAt || ! isVariable || ! inventory.parent_id ) {
			return;
		}
		wp.apiFetch( {
			path: '/hanmoto/v1/products/' + inventory.parent_id + '/variations',
		} ).then( setVariations ).catch( () => {
			setVariations( [] );
		} );
	}, [ isVariable, inventory.parent_id, appliedAt ] );

	if ( appliedAt ) {
		return (
			// translators: %s is the date when the inventory was applied.
			<span style={ { color: 'lightgrey' } }>{ sprintf( __( '%sに在庫反映済み', 'hametuha' ), appliedAt ) }</span>
		);
	}

	const applyStock = ( variationId ) => {
		setLoading( true );
		wp.apiFetch( {
			path: '/hanmoto/v1/inventory/' + inventory.id + '/',
			method: 'POST',
			data: variationId ? { variation_id: variationId } : {},
		} ).then( ( data ) => {
			setAppliedAt( data.updated );
			onChange( { ...inventory, applied_at: data.updated } );
		} ).catch( ( error ) => {
			alert( error.message );
		} ).finally( () => {
			setLoading( false );
		} );
	};

	if ( isVariable ) {
		if ( null === variations ) {
			return (
				<span style={ { color: 'lightgrey' } }>{ __( 'バリエーション取得中…', 'hanmoto' ) }</span>
			);
		}
		if ( 0 === variations.length ) {
			return (
				<span style={ { color: 'lightgrey' } }>{ __( 'バリエーションがありません', 'hanmoto' ) }</span>
			);
		}
		const options = [
			{ value: 0, label: __( '反映先バリエーション', 'hanmoto' ) },
			...variations.map( ( v ) => {
				let label;
				if ( v.managing_stock ) {
					label = sprintf(
						// translators: %1$s is variation name, %2$d is current stock.
						__( '%1$s (在庫: %2$d)', 'hanmoto' ),
						v.name,
						v.stock || 0
					);
				} else {
					label = sprintf(
						// translators: %s is variation name.
						__( '%s (在庫管理なし)', 'hanmoto' ),
						v.name
					);
				}
				return { value: v.id, label };
			} ),
		];
		return (
			<Flex align="center" justify="flex-start" gap={ 2 }>
				<FlexItem>
					<SelectControl
						value={ selectedVariationId }
						options={ options }
						onChange={ ( id ) => setSelectedVariationId( parseInt( id ) ) }
					/>
				</FlexItem>
				<FlexItem>
					<Button
						isSecondary
						isSmall
						disabled={ loading || ! selectedVariationId }
						onClick={ () => applyStock( selectedVariationId ) }
					>
						{ __( '在庫に反映', 'hanmoto' ) }
					</Button>
				</FlexItem>
			</Flex>
		);
	}

	return (
		<Button isSecondary disabled={ loading } isSmall onClick={ () => applyStock( 0 ) }>
			{ __( '在庫に反映', 'hanmoto' ) }
		</Button>
	);
};

if ( ! window.hanmoto ) {
	window.hanmoto = {};
}
hanmoto.InventoryAndStock = InventoryAndStock;
