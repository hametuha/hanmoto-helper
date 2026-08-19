/*!
 * Order fax editor.
 *
 * 紐付いた注文の一覧を描き、追加と削除を即時に反映する。
 * 行はすべてここで描くので、初期表示と再描画で処理が1つになる。
 *
 * @handle hanmoto-order-fax-editor
 * @deps jquery,wp-api-fetch,wp-i18n
 */

const $ = jQuery;
const { apiFetch } = wp;
const { __, sprintf } = wp.i18n;

const container = $( '#hanmoto-order-fax' );

if ( container.length && window.HanmotoOrderFax ) {
	const faxId = window.HanmotoOrderFax.faxId;
	const path = `hanmoto/v1/order-fax/${ faxId }/orders`;
	const tbody = container.find( '.hanmoto-fax-orders tbody' );
	const stats = container.find( '.hanmoto-fax-stats' );
	const results = container.find( '.hanmoto-fax-results' );
	const query = container.find( '.hanmoto-fax-query' );
	const spinner = container.find( '.hanmoto-fax-spinner' );

	/**
	 * 「他の送付分にも入っている」バッジを作る。
	 *
	 * @param {Array} faxes 他の送付分。
	 * @return {jQuery|null} バッジ要素。
	 */
	const badge = ( faxes ) => {
		if ( ! faxes || ! faxes.length ) {
			return null;
		}
		// translators: %d is the number of other faxes.
		const many = __( '他%d件の送付分にも', 'hanmoto' );
		const label = 1 === faxes.length
			? __( '他の送付分にも', 'hanmoto' )
			: sprintf( many, faxes.length );
		return $( '<a class="hanmoto-fax-badge" />' )
			.attr( 'href', faxes[ 0 ].edit_url )
			.attr( 'title', faxes.map( ( fax ) => fax.title ).join( ', ' ) )
			.text( label );
	};

	/**
	 * セルを作る。文字列は必ずtextで入れる（書店名に&が入る）。
	 *
	 * @param {string} text     中身。
	 * @param {string} cssClass 追加するクラス。
	 * @return {jQuery} td要素。
	 */
	const cell = ( text, cssClass ) => $( '<td />' ).addClass( cssClass || '' ).text( text );

	/**
	 * 紐付いた注文のテーブルを描き直す。
	 *
	 * @param {Array}  orders 注文。
	 * @param {Object} stat   集計。
	 */
	const render = ( orders, stat ) => {
		tbody.empty();
		if ( ! orders.length ) {
			tbody.append(
				$( '<tr />' ).append(
					$( '<td colspan="6" />' ).text(
						__( '注文が入っていません。下の検索から追加してください。', 'hanmoto' )
					)
				)
			);
		}
		orders.forEach( ( order ) => {
			const row = $( '<tr />' );
			if ( ! order.printable ) {
				row.addClass( 'is-not-printable' );
			}
			const link = $( '<a />' ).attr( 'href', order.edit_url ).text( `#${ order.id }` );
			row.append( $( '<td />' ).append( link ) );
			row.append( cell( order.date ) );
			const shop = $( '<td />' ).text( order.shop_name );
			const mark = badge( order.other_faxes );
			if ( mark ) {
				shop.append( mark );
			}
			row.append( shop );
			row.append( cell( order.title ) );
			// translators: %d is the amount of books.
			const excluded = __( '%d（印刷対象外）', 'hanmoto' );
			row.append( cell( order.printable
				? String( order.amount )
				: sprintf( excluded, order.amount ), 'column-amount' ) );
			row.append(
				$( '<td class="column-action" />' ).append(
					$( '<button type="button" class="button button-small" />' )
						.text( __( '外す', 'hanmoto' ) )
						.on( 'click', () => detach( order ) )
				)
			);
			tbody.append( row );
		} );
		stats.text( sprintf(
			// translators: %1$d is orders, %2$d is books, %3$d is pages.
			__( '%1$d件 %2$d冊（%3$dページ）', 'hanmoto' ),
			stat.total,
			stat.books,
			stat.pages
		) );
	};

	/**
	 * 注文を外す。
	 *
	 * @param {Object} order 対象の注文。
	 */
	const detach = ( order ) => {
		const message = order.other_faxes && order.other_faxes.length
			? __( 'この送付分から外します。他の送付分に入っている分はそのまま残ります。', 'hanmoto' )
			: __( 'この送付分から外します。注文は「未送付」に戻ります。', 'hanmoto' );
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( message ) ) {
			return;
		}
		apiFetch( {
			path,
			method: 'DELETE',
			data: { ids: String( order.id ) },
		} ).then( ( res ) => {
			render( res.orders, res.stats );
			// 外した注文が候補に戻るので、検索結果は消しておく。
			results.empty();
		} ).catch( ( res ) => {
			// eslint-disable-next-line no-alert
			window.alert( res.message );
		} );
	};

	/**
	 * 選んだ候補を追加する。
	 */
	const attach = () => {
		const ids = results.find( 'input:checked' ).map( ( index, input ) => $( input ).val() ).get();
		if ( ! ids.length ) {
			// eslint-disable-next-line no-alert
			window.alert( __( '追加する注文が選ばれていません。', 'hanmoto' ) );
			return;
		}
		apiFetch( {
			path,
			method: 'POST',
			data: { ids: ids.join( ',' ) },
		} ).then( ( res ) => {
			render( res.orders, res.stats );
			results.empty();
			query.val( '' );
		} ).catch( ( res ) => {
			// eslint-disable-next-line no-alert
			window.alert( res.message );
		} );
	};

	/**
	 * 候補を検索して並べる。
	 */
	const search = () => {
		const keyword = query.val().trim();
		if ( ! keyword ) {
			return;
		}
		spinner.addClass( 'is-active' );
		results.empty();
		apiFetch( {
			path: `hanmoto/v1/order-fax/${ faxId }/candidates?q=${ encodeURIComponent( keyword ) }`,
		} ).then( ( res ) => {
			if ( ! res.orders.length ) {
				results.append( $( '<p />' ).text( res.message ) );
				return;
			}
			res.orders.forEach( ( order ) => {
				const label = $( '<label />' );
				label.append( $( '<input type="checkbox" />' ).val( order.id ) );
				label.append( $( '<span />' ).text( sprintf(
					' #%1$d %2$s %3$s / %4$s %5$d冊',
					order.id,
					order.date,
					order.shop_name,
					order.title,
					order.amount
				) ) );
				const mark = badge( order.other_faxes );
				if ( mark ) {
					label.append( mark );
				}
				results.append( label );
			} );
			results.append(
				$( '<p />' ).append(
					$( '<button type="button" class="button button-primary" />' )
						.text( __( '選んだ注文を追加', 'hanmoto' ) )
						.on( 'click', attach )
				)
			);
		} ).catch( ( res ) => {
			results.append( $( '<p />' ).text( res.message ) );
		} ).finally( () => {
			spinner.removeClass( 'is-active' );
		} );
	};

	container.find( '.hanmoto-fax-search' ).on( 'click', search );
	query.on( 'keydown', ( e ) => {
		if ( 13 === e.keyCode ) {
			// メタボックス内なので、Enterで投稿が保存されるのを止める。
			e.preventDefault();
			search();
		}
	} );

	render( window.HanmotoOrderFax.orders, window.HanmotoOrderFax.stats );
}
