/*!
 * Transaction Type selector
 *
 * @handle hanmoto-transaction-type-selector
 * @deps wp-i18n, wp-components
 */

const { useState, useEffect } = wp.element;
const { __ } = wp.i18n;
const { SelectControl } = wp.components;

const TransactionSelector = ( props ) => {
	const termId = parseInt( props.termId );
	const [ transactionTypes, setTransactionType ] = useState( [ {
		value: 0,
		label: __( '選択してください', 'hanmoto' ),
	} ] );

	useEffect( () => {
		wp.apiFetch( { path: '/wp/v2/transaction_type?per_page=100' } ).then( ( data ) => {
			const options = [ {
				value: 0,
				label: __( '選択してください', 'hanmoto' ),
			} ];
			data.forEach( ( transactionType ) => {
				options.push( {
					value: transactionType.term_id,
					label: transactionType.name,
				} );
			} );
			setTransactionType( options );
		} );
	}, [] );
	return (
		<div>
			<SelectControl label={ __( '取引種別', 'hanmoto' ) } selected={ termId } onChange={ ( id ) => props.onChange( id ) } options={ transactionTypes } />
		</div>
	);
};

if ( ! window.hanmoto ) {
	window.hanmoto = {};
}
hanmoto.TransactionSelector = TransactionSelector;
