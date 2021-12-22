/*!
 * Product helper.
 *
 * @package hanmoto
 * @handle hanmoto-product-helper
 * @deps jquery
 */

const $ = jQuery;

$( document ).ready( function() {

	console.log( $( '#hanmoto-fill-product' )[0] );

	$( '#hanmoto-fill-product' ).click( function( e ) {
		e.preventDefault();
		alert( '同期するぞ！' );
	} );

} );
