/*!
 * Product Selector
 *
 * @handle hanmoto-product-selector
 * @deps wp-i18n, wp-components
 */

const { useState, useEffect } = wp.element;
const { __, sprintf } = wp.i18n;
const { SelectControl } = wp.components;
const { apiFetch } = wp;

const ProductSelector = ( props ) => {
	const currentId = parseInt( props.postId );
	const [ products, setProducts ] = useState( [ {
		value: 0,
		label: __( '選択してください', 'hanmoto' ),
	} ] );
	useEffect( () => {
		wp.apiFetch( { path: '/hanmoto/v1/products' } ).then( ( data ) => {
			const options = [ {
				value: 0,
				label: __( '選択してください', 'hanmoto' ),
			} ];
			data.forEach( ( product ) => {
				options.push( {
					value: product.id,
					label: sprintf( '%s（%s円）', product.name, product.price ),
				} );
			} );
			setProducts( options );
		} );
	}, [] );
	return (
		<div>
			<SelectControl label={ __( '商品', 'hanmoto' ) } selected={ currentId } onChange={ ( id ) => props.onChange( id ) } options={ products } />
		</div>
	);
};

if ( ! window.hanmoto ) {
	window.hanmoto = {};
}
hanmoto.ProductSelector = ProductSelector;
