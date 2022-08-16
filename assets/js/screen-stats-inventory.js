/*!
 * Inventory stats.
 *
 * @handle hanmoto-screen-stats-inventory
 * @deps wp-element, wp-components, wp-i18n, hanmoto-book-selector, hanmoto-date-range
 */

const { render, Component } = wp.element;
const { sprintf, __ } = wp.i18n;
const { Button } = wp.components;
const { DateRange, BookSelector } = hanmoto;


class ScreenStatsInventory extends Component {

	constructor( props ) {
		super( props );
		const today = new Date();
		const start = new Date();
		start.setDate( start.getDate() - 30 );
		this.state = {
			start: sprintf( '%04d-%02d-%02d', start.getFullYear().toString(), ( start.getMonth() + 1 ).toString(), start.getDate().toString() ),
			end: sprintf( '%04d-%02d-%02d', today.getFullYear().toString(), ( today.getMonth() + 1 ).toString(), today.getDate().toString() ),
			id: 0,
		};
	}

	render() {
		console.log( this.state );
		return (
			<div>
				<BookSelector id={ this.state.id } onChange={ ( id ) => {
					this.setState( { id } );
				} } />
				<DateRange start={ this.state.start } end={ this.state.end } onChange={ ( range ) => {
					this.setState( range );
				} } />
				<p>
					<Button isPrimary onClick={ () => this.refresh() }>
						{ __( '表示', 'hanmoto' ) }
					</Button>
				</p>
			</div>
		);
	}

	refresh() {
		const { start, end, id } = this.state;
		// TODO: fetch API and get stats result.
	}
}

render( <ScreenStatsInventory />, document.getElementById( 'hanmoto-screen-stats-inventory' ) );
