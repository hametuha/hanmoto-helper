/*!
 * Delivery helper.
 *
 * @handle hanmoto-delivery-helper
 * @deps jquery,wp-api-fetch
 */

const $ = jQuery;
const { apiFetch } = wp;

$( '#doaction,#doaction2' ).click( function( e ) {
	const action = $( this ).prev( 'select' ).val();
	if ( 'make-delivery-of-goods' !== action ) {
		return true;
	}
	e.preventDefault();
	const ids = [];
	$( 'input[name="post[]"]:checked' ).each( function( index, input ) {
		ids.push( parseInt( $( input ).val() ) );
	} );
	if ( ids.length ) {
		apiFetch( {
			path: 'hanmoto/v1/delivery-of-goods',
			method: 'post',
			data: {
				ids: ids.join( ',' ),
			},
		} ).then( ( res ) => {
			alert( res.message );
		} ).catch( ( res ) => {
			alert( res.message );
		} );
	} else {
		alert( '在庫増減が選択されていません。' );
	}
} );
