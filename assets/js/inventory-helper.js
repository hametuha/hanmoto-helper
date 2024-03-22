/*!
 * Inventory helper
 *
 * @handle hanmoto-inventory-helper
 * @deps wp-i18n, wp-element, wp-api-fetch, wp-components, wp-api-fetch, hanmoto-product-selector, hanmoto-transaction-type-selector, hanmoto-inventory-stock
 */

const { render, createRoot, useState, useEffect } = wp.element;
const { Button, TextControl, SelectControl, Flex, FlexItem } = wp.components;
const { ProductSelector, TransactionSelector, InventoryAndStock } = hanmoto;
const { __ } = wp.i18n;

const div = document.getElementById( 'hanmoto-inventories' );

const InventoryApply = ( { postId } ) => {
	const [] = useState( [] );
};

const InventoryForm = ( props ) => {
	const [ inventory, setInventory ] = useState( {
		id: 0,
		price: 0,
		amount: 0,
		margin: 63,
		tax: 10,
		paid_at: 0,
		transaction_type: 0,
	} );
	return (
		<div className="inventory-form">
			<ProductSelector post-id={ inventory.id } onChange={ ( id ) => setInventory( { ...inventory, id: parseInt( id ) } ) } />
			<Flex align="start">
				<FlexItem>
					<TextControl type="number" label={ __( '単価', 'hanmoto' ) } value={ inventory.price } onChange={ ( price ) => setInventory( { ...inventory, price: parseInt( price ) } )} />
				</FlexItem>
				<FlexItem>
					<TextControl type="number" label={ __( '数量', 'hanmoto' ) } value={ inventory.amount } onChange={ ( amount ) => setInventory( { ...inventory, amount: parseInt( amount ) } )} />
				</FlexItem>
				<FlexItem>
					<TextControl type="number" label={ __( '料率', 'hanmoto' ) } value={ inventory.margin } onChange={ ( margin ) => setInventory( { ...inventory, margin: parseInt( margin ) } )}
						placeholder={ __( 'e.g. 63%', 'hanmoto' ) } help={ __( '単位は%です。', 'hanmoto' ) } />
				</FlexItem>
				<FlexItem>
					<TextControl type="number" label={ __( '消費税', 'hanmoto' ) } value={ inventory.tax } onChange={ ( tax ) => setInventory( { ...inventory, tax: parseInt( tax ) } )}
						placeholder={ __( 'e.g. 10%', 'hanmoto' ) } help={ __( '単位は%です。', 'hanmoto' ) } />
				</FlexItem>
				<FlexItem>
					<TransactionSelector term-id={ inventory.transaction_type } onChange={ ( id ) => setInventory( { ...inventory, transaction_type: parseInt( id ) } ) } />
				</FlexItem>
				<FlexItem>
					<SelectControl label={ __( '清算日', 'hanmoto' ) } value={ inventory.paid_at } onChange={ ( paid_at ) => setInventory( { ...inventory, paid_at: parseInt( paid_at ) } )}
						options={ [
							{
								value: 0,
								label: __( '当日', 'hanmoto' ),
							},
							{
								value: 1,
								label: __( '翌月末日', 'hanmoto' ),
							},
							{
								value: 6,
								label: __( '六ヶ月後末日', 'hanmoto' ),
							},
							{
								value: 12,
								label: __( '一年後月末日', 'hanmoto' ),
							},
						] }
					/>
				</FlexItem>
			</Flex>

			<Button isSecondary onClick={ () => props.onChange( inventory ) }>
				{ __( '追加', 'hanmoto' ) }
			</Button>
		</div>

	);
};

const InventoryContainer = ( { post } ) => {
	const [ inventories, setInventories ] = useState( [] );
	useEffect( () => {
		wp.apiFetch( {
			path: 'hanmoto/v1/inventories/' + post + '/',
		} ).then( ( data ) => {
			setInventories( data );
		} ).catch( ( error ) => {
			alert( error.message );
		} );
	}, [] );
	const getPrice = ( inventory ) => {
		return Math.floor( inventory.unit_price * inventory.amount * -1 * inventory.margin / 100 *  ( 100 + inventory.vat ) / 100 );
	};
	let total = 0;
	inventories.forEach( ( inventory ) => {
		total += getPrice( inventory );
	} );
	return (
		<div>
			{ ( 0 < inventories.length ) ? (
				<>
					<strong style={ { color: ( total > 0 ? 'green' : 'red' ) } }>{ total }円</strong>
					<ol>
						{ inventories.map( ( inventory ) => {
							const subtotal = getPrice( inventory );
							return (
								<li key={ inventory.id }>
									<strong style={ { marginRight: '1.5em' } }> { inventory.product }</strong>
									<small>{ inventory.unit_price }円
										× { inventory.amount }冊（料率{ inventory.margin }%）</small>
									＝<span style={ { color: ( subtotal > 0 ? 'green' : 'red' ) } }>&yen; { subtotal }</span>
									<span>（{ inventory.transaction_type_label } @ {inventory.capture_at}）</span>
									<InventoryAndStock inventory={ inventory } onChange={ ( inventoryToUpdate ) => {
										wp.apiFetch( {
											path: '/hanmoto/v1/inventory/' + inventoryToUpdate.id + '/',
											method: 'POST',
										} ).then( ( data ) => {
											const index = inventories.findIndex( ( i ) => i.id === inventoryToUpdate.id );
											inventories[ index ].applied_at = data.updated;
											console.log( inventories[ index ] );
											setInventories( inventories );
											console.log( data );
										} ).catch( ( error ) => {
											alert( error.message );
										} );
									} } />
								</li>
							);
						} ) }
					</ol>
				</>
			) : (
				<p>{ __( '在庫情報がありません。', 'hanmoto' ) }</p>
			) }
			<hr />
			<h3>{ __( '在庫変動を追加', 'hanmoto' ) }</h3>
			<InventoryForm onChange={ ( inventory ) => {
				wp.apiFetch( {
					path: '/hanmoto/v1/inventories/' + post + '/',
					method: 'POST',
					data: inventory,
				} ).then( ( data ) => {
					inventories.push( data );
					setInventories( inventories );
				} ).catch( ( error ) => {
					alert( error.message );
				} );
			} } />
		</div>
	);
};

if ( div ) {
	const id = parseInt( div.dataset.postId );
	if ( createRoot ) {
		// React >= 18
		const root = createRoot( div );
		root.render( <InventoryContainer post={ id } /> );
	} else {
		render( <InventoryContainer post={ id } />, div );
	}
}
