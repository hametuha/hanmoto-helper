/*!
 * Book Shop Account
 *
 * @package hanmoto
 * @handle hanmoto-book-shop-account
 * @deps jquery, wp-api-fetch
 */

/* global HanmotoBookShopAccount:false */

const $ = jQuery;

$( document ).ready( function() {
	$( '#hanmoto-is-book-shop' ).click( function( e ) {
		let method;
		let message;
		if ( $( this ).prop( 'checked' ) ) {
			method = 'post';
			message = HanmotoBookShopAccount.confirm_on;
		} else {
			method = 'delete';
			message = HanmotoBookShopAccount.confirm_off;
		}
		if ( ! confirm( message ) ) {
			e.preventDefault();
		} else {
			wp.apiFetch( {
				method,
				path: '/hanmoto/v1/registration/shop',
			} ).then( () => {
				// Do nothing.
			} ).catch( ( res ) => {
				alert( res.message );
				e.target.checked = ! e.target.checked;
			} );
		}
	} );
} );
