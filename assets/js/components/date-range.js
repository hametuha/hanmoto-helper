/*!
 * Date range selector.
 *
 * @handle hanmoto-date-range
 * @deps wp-element
 */

window.hanmoto = window.hanmoto || {};
window.hanmoto.DateRange = ( props ) => {
	console.log( props );
	const { start, end, onChange } = props;
	console.log( 'DateRange compoennt: ', props );
	return (
		<div className="hanmoto-ui-date-range">
			<input type="date" value={ start } onChange={ ( e ) => onChange( { start: e.target.value, end } ) } />
			<input type="date" value={ end } onChange={ ( e ) => onChange( { start, end: e.target.value } ) } />
		</div>
	);
};
