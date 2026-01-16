/*!
 * 書店からの注文を入力する
 *
 * @handle hanmoto-shop-order-register
 * @deps wp-element, wp-i18n, wp-api-fetch
 * @strategy defer
 */

const { useState, useEffect, useRef } = wp.element;
const { __ } = wp.i18n;
const apiFetch = wp.apiFetch;

// Debounce helper
const useDebounce = ( value, delay ) => {
	const [ debouncedValue, setDebouncedValue ] = useState( value );
	useEffect( () => {
		const handler = setTimeout( () => setDebouncedValue( value ), delay );
		return () => clearTimeout( handler );
	}, [ value, delay ] );
	return debouncedValue;
};

// Shop autocomplete with additional fields
const ShopInput = ( { shop, onChange } ) => {
	const [ inputValue, setInputValue ] = useState( shop.name || '' );
	const [ options, setOptions ] = useState( [] );
	const [ isOpen, setIsOpen ] = useState( false );
	const [ loading, setLoading ] = useState( false );
	const wrapperRef = useRef( null );

	const debouncedInput = useDebounce( inputValue, 300 );

	useEffect( () => {
		if ( debouncedInput.length >= 2 ) {
			setLoading( true );
			apiFetch( {
				path: `/hanmoto/v1/shops/search?q=${ encodeURIComponent( debouncedInput ) }`,
			} )
				.then( ( results ) => {
					setOptions( results );
					setIsOpen( true );
				} )
				.finally( () => setLoading( false ) );
		} else {
			setOptions( [] );
			setIsOpen( false );
		}
	}, [ debouncedInput ] );

	useEffect( () => {
		const handleClickOutside = ( event ) => {
			if ( wrapperRef.current && ! wrapperRef.current.contains( event.target ) ) {
				setIsOpen( false );
			}
		};
		document.addEventListener( 'mousedown', handleClickOutside );
		return () => document.removeEventListener( 'mousedown', handleClickOutside );
	}, [] );

	const handleInputChange = ( e ) => {
		const newName = e.target.value;
		setInputValue( newName );
		onChange( { ...shop, name: newName, term_id: null } );
	};

	const handleSelect = ( option ) => {
		setInputValue( option.name );
		onChange( {
			term_id: option.term_id,
			name: option.name,
			wholesaler: option.wholesaler || '',
			lineCode: option.line_code || '',
			shopCode: option.shop_code || '',
		} );
		setIsOpen( false );
	};

	const handleFieldChange = ( field, value ) => {
		onChange( { ...shop, [ field ]: value, term_id: null } );
	};

	return (
		<div className="shop-input-group">
			<div className="shop-name-field" ref={ wrapperRef }>
				<span className="field-label">{ __( '書店名', 'hanmoto' ) }</span>
				<input
					type="text"
					value={ inputValue }
					onChange={ handleInputChange }
					placeholder={ __( '書店名を入力', 'hanmoto' ) }
					className={ shop.term_id ? 'has-value' : '' }
					aria-label={ __( '書店名', 'hanmoto' ) }
				/>
				{ loading && <span className="loading-indicator">…</span> }
				{ isOpen && options.length > 0 && (
					<ul className="autocomplete-options" role="listbox">
						{ options.map( ( option, idx ) => (
							<li
								key={ idx }
								role="option"
								tabIndex={ 0 }
								onClick={ () => handleSelect( option ) }
								onKeyDown={ ( e ) => {
									if ( e.key === 'Enter' || e.key === ' ' ) {
										handleSelect( option );
									}
								} }
							>
								<strong>{ option.name }</strong>
								<small>
									{ option.wholesaler } { option.line_code }-{ option.shop_code }
								</small>
							</li>
						) ) }
					</ul>
				) }
			</div>
			<div className="shop-code-fields">
				<div className="shop-code-field">
					<span className="field-label">{ __( '取次', 'hanmoto' ) }</span>
					<input
						type="text"
						value={ shop.wholesaler || '' }
						onChange={ ( e ) => handleFieldChange( 'wholesaler', e.target.value ) }
						placeholder={ __( '例: トーハン', 'hanmoto' ) }
						aria-label={ __( '取次', 'hanmoto' ) }
					/>
				</div>
				<div className="shop-code-field">
					<span className="field-label">{ __( '番線', 'hanmoto' ) }</span>
					<input
						type="text"
						value={ shop.lineCode || '' }
						onChange={ ( e ) => handleFieldChange( 'lineCode', e.target.value ) }
						placeholder={ __( '例: 12345', 'hanmoto' ) }
						aria-label={ __( '番線', 'hanmoto' ) }
					/>
				</div>
				<div className="shop-code-field">
					<span className="field-label">{ __( '書店コード', 'hanmoto' ) }</span>
					<input
						type="text"
						value={ shop.shopCode || '' }
						onChange={ ( e ) => handleFieldChange( 'shopCode', e.target.value ) }
						placeholder={ __( '例: A1234', 'hanmoto' ) }
						aria-label={ __( '書店コード', 'hanmoto' ) }
					/>
				</div>
			</div>
		</div>
	);
};

// Book autocomplete
const BookInput = ( { book, onChange } ) => {
	const [ inputValue, setInputValue ] = useState( book?.title || '' );
	const [ options, setOptions ] = useState( [] );
	const [ isOpen, setIsOpen ] = useState( false );
	const [ loading, setLoading ] = useState( false );
	const wrapperRef = useRef( null );

	const debouncedInput = useDebounce( inputValue, 300 );

	useEffect( () => {
		if ( debouncedInput.length >= 2 && ! book?.id ) {
			setLoading( true );
			apiFetch( {
				path: `/hanmoto/v1/books/search?q=${ encodeURIComponent( debouncedInput ) }`,
			} )
				.then( ( results ) => {
					setOptions( results );
					setIsOpen( true );
				} )
				.finally( () => setLoading( false ) );
		} else {
			setOptions( [] );
			setIsOpen( false );
		}
	}, [ debouncedInput, book?.id ] );

	useEffect( () => {
		const handleClickOutside = ( event ) => {
			if ( wrapperRef.current && ! wrapperRef.current.contains( event.target ) ) {
				setIsOpen( false );
			}
		};
		document.addEventListener( 'mousedown', handleClickOutside );
		return () => document.removeEventListener( 'mousedown', handleClickOutside );
	}, [] );

	const handleInputChange = ( e ) => {
		setInputValue( e.target.value );
		if ( book?.id ) {
			onChange( null );
		}
	};

	const handleSelect = ( option ) => {
		setInputValue( option.title );
		onChange( option );
		setIsOpen( false );
	};

	const handleClear = () => {
		onChange( null );
		setInputValue( '' );
	};

	return (
		<div className="hanmoto-autocomplete" ref={ wrapperRef }>
			<input
				type="text"
				value={ inputValue }
				onChange={ handleInputChange }
				placeholder={ __( 'ISBN/書名', 'hanmoto' ) }
				className={ book?.id ? 'has-value' : '' }
			/>
			{ book?.id && (
				<button type="button" className="clear-btn" onClick={ handleClear }>
					&times;
				</button>
			) }
			{ loading && <span className="loading-indicator">…</span> }
			{ isOpen && options.length > 0 && (
				<ul className="autocomplete-options" role="listbox">
					{ options.map( ( option, idx ) => (
						<li
							key={ idx }
							role="option"
							tabIndex={ 0 }
							onClick={ () => handleSelect( option ) }
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' || e.key === ' ' ) {
									handleSelect( option );
								}
							} }
						>
							{ option.title }
							<small>{ option.isbn }</small>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
};

// Single order row (2-line layout)
const OrderRow = ( { order, index, onUpdate, onRemove, onDuplicate, sources } ) => {
	const updateField = ( field, value ) => {
		onUpdate( index, { ...order, [ field ]: value } );
	};

	return (
		<div className="order-row">
			<div className="order-row-header">
				<span className="row-number">{ index + 1 }</span>
				<div className="order-row-actions">
					<button
						type="button"
						className="button-link"
						onClick={ () => onDuplicate( index ) }
					>
						{ __( '複製', 'hanmoto' ) }
					</button>
					<button
						type="button"
						className="button-link-delete"
						onClick={ () => onRemove( index ) }
					>
						{ __( '削除', 'hanmoto' ) }
					</button>
				</div>
			</div>
			<div className="order-row-line1">
				<ShopInput
					shop={ order.shop }
					onChange={ ( shop ) => updateField( 'shop', shop ) }
				/>
			</div>
			<div className="order-row-line2">
				<div className="field-book">
					<span className="field-label">{ __( '書籍', 'hanmoto' ) }</span>
					<BookInput
						book={ order.book }
						onChange={ ( book ) => updateField( 'book', book ) }
					/>
				</div>
				<div className="field-amount">
					<span className="field-label">{ __( '部数', 'hanmoto' ) }</span>
					<input
						type="number"
						value={ order.amount }
						onChange={ ( e ) => updateField( 'amount', e.target.value ) }
						min="-999"
						max="999"
						aria-label={ __( '部数', 'hanmoto' ) }
					/>
				</div>
				<div className="field-in-charge">
					<span className="field-label">{ __( '担当', 'hanmoto' ) }</span>
					<input
						type="text"
						value={ order.inCharge }
						onChange={ ( e ) => updateField( 'inCharge', e.target.value ) }
						placeholder={ __( '担当者名', 'hanmoto' ) }
						aria-label={ __( '担当', 'hanmoto' ) }
					/>
				</div>
				<div className="field-note">
					<span className="field-label">{ __( '備考', 'hanmoto' ) }</span>
					<input
						type="text"
						value={ order.note }
						onChange={ ( e ) => updateField( 'note', e.target.value ) }
						aria-label={ __( '備考', 'hanmoto' ) }
					/>
				</div>
				<div className="field-source">
					<span className="field-label">{ __( '受注元', 'hanmoto' ) }</span>
					<select
						value={ order.source }
						onChange={ ( e ) => updateField( 'source', e.target.value ) }
						aria-label={ __( '受注元', 'hanmoto' ) }
					>
						<option value="">{ __( '選択', 'hanmoto' ) }</option>
						{ sources.map( ( src ) => (
							<option key={ src.value } value={ src.value }>
								{ src.label }
							</option>
						) ) }
					</select>
				</div>
				<div className="field-date">
					<span className="field-label">{ __( '受注日', 'hanmoto' ) }</span>
					<input
						type="date"
						value={ order.orderDate }
						onChange={ ( e ) => updateField( 'orderDate', e.target.value ) }
						aria-label={ __( '受注日', 'hanmoto' ) }
					/>
				</div>
			</div>
		</div>
	);
};

// Empty order template
const createEmptyOrder = ( defaultDate ) => ( {
	shop: {
		term_id: null,
		name: '',
		wholesaler: '',
		lineCode: '',
		shopCode: '',
	},
	book: null,
	amount: -1,
	inCharge: '',
	note: '',
	source: '',
	orderDate: defaultDate,
} );

// Main component
const ShopOrderRegister = () => {
	const { sources, defaultDate } = window.HanmotoOrderRegister || { sources: [], defaultDate: '' };
	const [ orders, setOrders ] = useState( [ createEmptyOrder( defaultDate ) ] );
	const [ submitting, setSubmitting ] = useState( false );
	const [ result, setResult ] = useState( null );

	const addRow = () => {
		setOrders( [ ...orders, createEmptyOrder( defaultDate ) ] );
	};

	const updateOrder = ( index, updatedOrder ) => {
		const newOrders = [ ...orders ];
		newOrders[ index ] = updatedOrder;
		setOrders( newOrders );
	};

	const removeOrder = ( index ) => {
		if ( orders.length > 1 ) {
			setOrders( orders.filter( ( _, i ) => i !== index ) );
		}
	};

	const duplicateOrder = ( index ) => {
		const source = orders[ index ];
		const newOrder = {
			// Copy shop info
			shop: { ...source.shop },
			// Reset book and note
			book: null,
			amount: -1,
			note: '',
			// Copy these fields
			inCharge: source.inCharge,
			source: source.source,
			orderDate: source.orderDate,
		};
		const newOrders = [ ...orders ];
		newOrders.splice( index + 1, 0, newOrder );
		setOrders( newOrders );
	};

	const validateOrders = () => {
		const errors = [];
		orders.forEach( ( order, index ) => {
			const rowNum = index + 1;
			if ( ! order.shop.name ) {
				errors.push( `${ __( '行', 'hanmoto' ) } ${ rowNum }: ${ __( '書店名を入力してください', 'hanmoto' ) }` );
			}
			if ( ! order.book?.id ) {
				errors.push( `${ __( '行', 'hanmoto' ) } ${ rowNum }: ${ __( '書籍を選択してください', 'hanmoto' ) }` );
			}
			if ( ! order.amount || order.amount === 0 ) {
				errors.push( `${ __( '行', 'hanmoto' ) } ${ rowNum }: ${ __( '部数を入力してください', 'hanmoto' ) }` );
			}
			if ( ! order.source ) {
				errors.push( `${ __( '行', 'hanmoto' ) } ${ rowNum }: ${ __( '受注元を選択してください', 'hanmoto' ) }` );
			}
			if ( ! order.orderDate ) {
				errors.push( `${ __( '行', 'hanmoto' ) } ${ rowNum }: ${ __( '受注日を入力してください', 'hanmoto' ) }` );
			}
		} );
		return errors;
	};

	const handleSubmit = async () => {
		const errors = validateOrders();
		if ( errors.length > 0 ) {
			setResult( { type: 'error', message: errors.join( '\n' ) } );
			return;
		}

		setSubmitting( true );
		setResult( null );

		try {
			const payload = orders.map( ( order ) => ( {
				shop_id: order.shop.term_id,
				shop_name: order.shop.name,
				wholesaler: order.shop.wholesaler,
				line_code: order.shop.lineCode,
				shop_code: order.shop.shopCode,
				book_id: order.book.id,
				amount: parseInt( order.amount, 10 ),
				in_charge: order.inCharge,
				note: order.note,
				source: order.source,
				order_date: order.orderDate,
			} ) );

			const response = await apiFetch( {
				path: '/hanmoto/v1/orders/bulk',
				method: 'POST',
				data: { orders: payload },
			} );

			if ( response.failed > 0 ) {
				const errorMessages = response.errors.map( ( e ) => e.message ).join( '\n' );
				setResult( {
					type: 'warning',
					message: `${ response.success }${ __( '件登録、', 'hanmoto' ) }${ response.failed }${ __( '件失敗', 'hanmoto' ) }\n${ errorMessages }`,
				} );
			} else {
				setResult( {
					type: 'success',
					message: `${ response.success }${ __( '件の注文を登録しました', 'hanmoto' ) }`,
				} );
				// Reset form
				setOrders( [ createEmptyOrder( defaultDate ) ] );
			}
		} catch ( error ) {
			setResult( {
				type: 'error',
				message: error.message || __( '登録に失敗しました', 'hanmoto' ),
			} );
		} finally {
			setSubmitting( false );
		}
	};

	return (
		<div className="hanmoto-order-register">
			{ result && (
				<div className={ `notice notice-${ result.type }` }>
					<pre>{ result.message }</pre>
				</div>
			) }
			<div className="order-list">
				{ orders.map( ( order, index ) => (
					<OrderRow
						key={ index }
						order={ order }
						index={ index }
						onUpdate={ updateOrder }
						onRemove={ removeOrder }
						onDuplicate={ duplicateOrder }
						sources={ sources }
					/>
				) ) }
			</div>
			<p className="submit">
				<button type="button" className="button" onClick={ addRow }>
					{ __( '+ 行を追加', 'hanmoto' ) }
				</button>
				&nbsp;
				<button
					type="button"
					className="button button-primary"
					onClick={ handleSubmit }
					disabled={ submitting }
				>
					{ submitting ? __( '登録中…', 'hanmoto' ) : __( '一括登録', 'hanmoto' ) }
				</button>
			</p>
		</div>
	);
};

// Mount
const container = document.getElementById( 'shop-order-register-form' );
if ( container ) {
	wp.element.createRoot( container ).render( <ShopOrderRegister /> );
}
