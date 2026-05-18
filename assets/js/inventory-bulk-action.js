/*!
 * Assign inventory to events in bulk.
 *
 * @handle hanmoto-inventory-bulk-action
 * @deps jquery, wp-api-fetch, wp-i18n
 */

const { __, sprintf } = wp.i18n;
const $ = jQuery;

/**
 * Show a native <dialog> asking for the realized-at date.
 *
 * @param {number} count Number of selected inventories.
 * @returns {Promise<string|null>} Resolves to selected Y-m-d string, or null if cancelled.
 */
function askRealizedAt( count ) {
	return new Promise( ( resolve ) => {
		const today = new Date().toISOString().slice( 0, 10 );
		// translators: %d is the number of selected inventories.
		const description = sprintf( __( '選択された %d 件の取引に実現日を設定します。', 'hanmoto' ), count );
		const titleText = __( '実現日を一括設定', 'hanmoto' );
		const labelText = __( '実現日', 'hanmoto' );
		const cancelText = __( 'キャンセル', 'hanmoto' );
		const confirmText = __( '設定する', 'hanmoto' );
		const dialog = document.createElement( 'dialog' );
		dialog.style.padding = '20px';
		dialog.style.minWidth = '320px';
		dialog.style.border = '1px solid #c3c4c7';
		dialog.style.borderRadius = '4px';
		dialog.innerHTML = `
			<form method="dialog" style="display: flex; flex-direction: column; gap: 12px; margin: 0;">
				<h2 style="margin: 0; font-size: 1.2em;">${ titleText }</h2>
				<p style="margin: 0;">${ description }</p>
				<p style="margin: 0;">
					<label>${ labelText }:
						<input type="date" name="realized_at" value="${ today }" required style="margin-left: 6px;" />
					</label>
				</p>
				<p style="margin: 0; text-align: right;">
					<button type="button" data-action="cancel" class="button" style="margin-right: 6px;">${ cancelText }</button>
					<button type="submit" value="confirm" class="button button-primary">${ confirmText }</button>
				</p>
			</form>
		`;
		document.body.appendChild( dialog );
		dialog.querySelector( '[data-action="cancel"]' ).addEventListener( 'click', () => {
			dialog.close( 'cancel' );
		} );
		dialog.addEventListener( 'close', () => {
			const value = dialog.querySelector( 'input[name="realized_at"]' ).value;
			const result = dialog.returnValue;
			document.body.removeChild( dialog );
			resolve( 'confirm' === result ? value : null );
		} );
		dialog.showModal();
	} );
}

$( document ).ready( function() {
	$( '#doaction, #doaction2' ).click( function( e ) {
		const $select = $( this ).prevAll( 'select[name^="action"]' );
		const action = $select.val();
		const $ids = $( '.wp-list-table input[name="post[]"]:checked' );
		if ( 'assign-event' === action ) {
			e.preventDefault();
			if ( ! $ids.length ) {
				return;
			}
			const request = [];
			$ids.each( function( index, id ) {
				request.push( $( id ).val() );
			} );
			const event = window.prompt( __( '割り当てる取引イベントのIDを指定してください。', 'hanmoto' ) );
			if ( ! /^\d+$/.test( event ) ) {
				return;
			}
			wp.apiFetch( {
				path: 'hanmoto/v1/inventories/' + event + '/?ids=' + request.join( ',' ),
				method: 'PUT',
			} ).then( function( response ) {
				// translators: %1$d is success, %2$d is total.
				alert( sprintf( __( '%1$d/%2$d件を登録しました。', 'hanmoto' ), response.updated, response.should ) );
				window.location.reload();
			} ).catch( function( response ) {
				alert( response.message );
			} );
			return;
		}
		if ( 'set_realized_at' === action ) {
			e.preventDefault();
			if ( ! $ids.length ) {
				return;
			}
			const ids = $ids.map( function( index, el ) {
				return $( el ).val();
			} ).get();
			askRealizedAt( ids.length ).then( ( realizedAt ) => {
				if ( null === realizedAt ) {
					return;
				}
				wp.apiFetch( {
					path: '/hanmoto/v1/inventories/bulk-realize',
					method: 'POST',
					data: {
						ids: ids.join( ',' ),
						realized_at: realizedAt,
					},
				} ).then( ( response ) => {
					// translators: %1$d is success count, %2$d is total count.
					alert( sprintf( __( '%1$d/%2$d件の実現日を設定しました。', 'hanmoto' ), response.updated, response.should ) );
					window.location.reload();
				} ).catch( ( err ) => {
					alert( err.message );
				} );
			} );
		}
	} );
} );
