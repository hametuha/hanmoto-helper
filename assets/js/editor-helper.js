/*!
 * Book editor helper.
 *
 * @package hanmoto
 * @handle hanmoto-editor-helper
 * @deps wp-api-fetch, wp-plugins, wp-edit-post, wp-components, wp-core-data, wp-data, wp-api-fetch, wp-i18n, wp-compose, wp-element
 */

const { registerPlugin } = wp.plugins;
const { PluginDocumentSettingPanel } = wp.editPost;
const { TextControl, Spinner } = wp.components;
const { withState } = wp.compose;
const { useEffect } = wp.element;
const { useEntityProp } = wp.coreData;
const { select } = wp.data;
const { apiFetch } = wp;
const { __, sprintf } = wp.i18n;

/* global HanmotoEditorHelper:false */

let initialFetched = false;


const HanmotoIsbnBox = withState( {
	loading: false,
	bookData: null,
} )( ( { loading, setState, bookData } ) => {
	const postType = select( 'core/editor' ).getCurrentPostType();
	if ( HanmotoEditorHelper.postType !== postType ) {
		// This post type is not supported.
		return null;
	}
	// isbn, last synced.
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
	const isbnFetch = () => {
		setState( {
			loading: true,
		}, () => {
			apiFetch( {
				path: sprintf( 'hanmoto/v1/book/%d?isbn=%s', select( 'core/editor' ).getCurrentPostId(), meta.hanmoto_isbn ),
			} ).then( ( res ) => {
				setState( {
					loading: false,
					bookData: res.length ? res[0] : null,
				} );
			} ).catch( () => {
				setState( { loading: false } );
			} );
		} );
	};
	// Fetch if exists.
	useEffect( () => {
		if ( initialFetched ) {
			return;
		}
		initialFetched = true;
		if ( ! /\d{13}/.test( meta.hanmoto_isbn ) ) {
			return;
		}
		// ISBN exists.
		isbnFetch();
	} );
	return (
		<PluginDocumentSettingPanel
			name="hanmoto=isbn-box"
			title={ __( 'ISBN Setting', 'hanmoto' ) }>
			<TextControl
				className="hanmoto-isbn"
				icon="book"
				label={ __( 'ISBN of this book', 'hanmoto' ) }
				value={ meta.hanmoto_isbn }
				onChange={ ( newIsbn ) => {
					setMeta( {
						...meta,
						hanmoto_isbn: newIsbn,
					} );
				} }
			/>
			<p>
				<strong>{ __( 'Last Synced', 'hanmoto' ) }</strong><br />
				<code>{ meta.hanmoto_last_synced ? meta.hanmoto_last_synced : __( 'Not Synced', 'hanmoto' ) }</code>
			</p>
			<hr />
			<p><strong>{ __( 'OpenBD Data', 'hanmoto' ) }</strong></p>
			{ loading && (
				<p style={ { position: 'absolute', top: 0, right: 0 } }>
					<Spinner />
				</p>
			) }
			{ bookData ? (
				<div className="hanmoto-card">
					<img src={ bookData.summary.cover } alt="" className="hanmoto-card-cover" />
					<p className="hanmoto-card-meta">
						<strong className="hanmoto-card-title">{ bookData.summary.title }</strong><br />
						<small className="hanmoto-card-author">{ bookData.summary.author }</small>
					</p>
					<p className="hanmoto-card-links">
						<a href={ `https://www.hanmoto.com/bd/isbn/${bookData.summary.isbn}` }
							className="hanmoto-card-link hanmoto-com" rel="noopener noreferrer" target="_blank">
							{ __( 'View in Hanmoto.com', 'hanmoto' ) }
						</a>
					</p>
				</div>
			) : (
				<p className="description">{ __( 'No Data Found.', 'hanmoto' ) }</p>
			) }
		</PluginDocumentSettingPanel>
	);
} );

registerPlugin( 'hanmoto-isbn-box', { render: HanmotoIsbnBox } );

