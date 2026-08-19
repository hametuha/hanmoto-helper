/*!
 * Order fax helper.
 *
 * @handle hanmoto-order-fax-helper
 * @deps jquery,wp-api-fetch
 */

const $ = jQuery;
const { apiFetch } = wp;

$( '#doaction,#doaction2' ).click( function( e ) {
	const action = $( this ).prev( 'select' ).val();
	if ( 'make-order-fax' !== action ) {
		return true;
	}
	e.preventDefault();
	const ids = [];
	$( 'input[name="post[]"]:checked' ).each( function( index, input ) {
		ids.push( parseInt( $( input ).val() ) );
	} );
	if ( ! ids.length ) {
		alert( '注文が選択されていません。' );
		return;
	}
	apiFetch( {
		path: 'hanmoto/v1/order-fax',
		method: 'post',
		data: {
			ids: ids.join( ',' ),
		},
	} ).then( ( res ) => {
		alert( res.message );
		if ( res.edit_url ) {
			window.location.href = res.edit_url;
		}
	} ).catch( ( res ) => {
		alert( res.message );
	} );
} );
