/*!
 * Assign inventory to events in bulk.
 *
 * @handle hanmoto-inventory-bulk-action
 * @deps jquery, wp-api-fetch, wp-i18n
 */

const { __, sprintf } = wp.i18n;
const $ = jQuery;

$( document ).ready( function() {
	$( '#doaction, #doaction2' ).click( function( e ) {
		const $select = $( this ).prevAll( 'select[name="action"]' );
		if ( 'assign-event' !== $select.val() ) {
			return true;
		}
		e.preventDefault();
		const $ids = $( '.wp-list-table input[name="post[]"]:checked' );
		if ( ! $ids.length ) {
			return true;
		}
		const request = [];
		$ids.each( function( index, id ) {
			request.push( $( id ).val() );
		} );
		const event = window.prompt( __( '割り当てる取引イベントのIDを指定してください。', 'hanmoto' ) );
		if ( ! /^\d+$/.test( event ) ) {
			return true;
		}
		wp.apiFetch( {
			path: 'hanmoto/v1/inventories/' + event + '/?ids=' + request.join( ',' ),
			method: 'PUT',
		} ).then( function( response ) {
			// translators: %1$d is success, %2$d is total.
			alert( sprintf( __( '%1$d/%2$d件を登録しました。', 'hanmoto' ), response.updated, response.should ) );
			window.refresh();
		} ).cache( function( response ) {
			alert( response.message );
		} );
	} );
} );

