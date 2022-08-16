/*!
 * Book Selector Components.
 *
 * @handle hanmoto-book-selector
 * @deps wp-components, wp-i18n
 */

const { SelectControl } = wp.components;
const { __ } = wp.i18n;

/* global HanmotoBookSelector:false */

console.log( HanmotoBookSelector.books );

const getOptions = () => {
	const options = [
		{
			value: 0,
			label: __( '未選択', 'hanmoto' ),
		},
	];
	if ( HanmotoBookSelector ) {
		HanmotoBookSelector.books.forEach( ( book ) => {
			options.push( {
				value: book.id,
				label: book.title,
			} );
		} );
	}
	return options;
};

window.hanmoto = window.hanmoto || {};
window.hanmoto.BookSelector = ( { id, onChange } ) => {
	return (
		<SelectControl lable={ __( '対象書籍', 'hanmoto' ) } value={ id } options={ getOptions() } onChange={ ( id ) => onChange( id ) } />
	);
};

