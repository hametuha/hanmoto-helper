/*!
 * Taxonomy selector with select2
 *
 * @package hanmoto
 * @handle hanmoto-taxonomy-selector
 * @deps select2
 */

const $ = jQuery;

$( document ).ready( function() {
	$( '.hanmoto-select2' ).select2( {
		width: '100%',
		allowClear: true,
	} );
} );

