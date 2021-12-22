/*!
 * Cart helper.
 *
 * @package hanmoto
 * @handle hanmoto-cart-helper
 * @deps jquery
 */

const $ = jQuery;

$( document ).on( 'click', '#book-shop-coupon', function( e ) {
	e.preventDefault();
	$( '#coupon_code' ).val( $( this ).attr( 'data-coupon' ) );
	$( 'button[name="apply_coupon"]' ).trigger( 'click' );
} );
