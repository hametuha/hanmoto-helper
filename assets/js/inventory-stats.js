/*!
 * Inventory stats.
 *
 * @handle hanmoto-inventory-stats
 * @deps chart-js, wp-api-fetch, wp-i18n, chart-js-adapter-moment
 */

/* global HanmotoStats:false */

const { __ } = wp.i18n;
const endpoint = `/hanmoto/v1/stats/inventory/${ HanmotoStats.post }/?password=${ HanmotoStats.password }`;



// Canvas
wp.apiFetch( {
	path: endpoint,
} ).then( ( res ) => {
	if ( ! res.length ) {
		throw new Error( __( 'データがありませんでした。', 'hanmoto' ) );
	}
	const ctx = document.getElementById( 'hanmoto-stats-chart' );

	const data = {
		labels: [ '日付', '残部数' ],
		datasets: [
			{
				label: HanmotoStats.title,
				data: res.map( ( item ) => {
					return {
						x: item.date,
						y: item.subtotal,
					};
				} ),
			}
		],
	};
	new Chart( ctx, {
		type: 'line',
		data: data,
		options: {
			scales: {
				x: {
					type: 'time',
					min: res[0].date,
					time: {
						unit: 'day',
					}
				}
			}
		}
	} );
} ).catch( ( res ) => {
	alert( res.message );
} ).finally( () => {
	// Remove loading.
	document.getElementsByClassName( 'hanmoto-stats-main' )[0].classList.remove( 'loading' );
} );
