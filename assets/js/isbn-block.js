/*!
 * ISBN block
 *
 * @package hanmoto
 * @handle hanmoto-isbn-block
 * @deps wp-i18n, wp-server-side-render, wp-i18n, wp-blocks, wp-block-editor, wp-data, wp-components
 */

/* global HanmotoIsbnBlockVars: false */

const { serverSideRender: ServerSideRender } = wp;
const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, SelectControl, TextareaControl } = wp.components;

//
// Register Block.
//
registerBlockType( HanmotoIsbnBlockVars.name, {
	title: HanmotoIsbnBlockVars.label,
	description: __( '書籍の一覧を表示します。', 'hanmoto' ),
	icon: 'book',
	category: 'widgets',
	attributes: HanmotoIsbnBlockVars.attributes,
	example: {
		isbn: '9784905197027',
	},
	edit( { attributes, setAttributes } ) {
		const styleOptions = [
			{
				value: 'card',
				label: __( 'リンクカード（1冊程度）', 'hanmoto' ),
			},
			{
				value: 'tile',
				label: __( 'タイル（複数冊）', 'hanmoto' ),
			},
		];
		const isbns = attributes.isbn.split( /(\r\n|\n|\r)/gm ).map( ( isbn ) => isbn.trim() ).filter( ( isbn ) => {
			return /[0-9]{13}/.test( isbn );
		} );
		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( '書誌情報設定', 'hanmoto' ) }>
						<TextareaControl label={ __( 'ISBN', 'hanmoto' ) } value={ attributes.number } onChange={ ( isbn ) => setAttributes( { isbn } ) }
							help={ __( '各行にISBNを入れてください。', 'hanmoto' ) } />
						<SelectControl label={ __( 'スタイル', 'hanmoto' ) } value={ attributes.style } onChange={ ( style ) => setAttributes( { style } ) }
							options={ styleOptions } />
					</PanelBody>
				</InspectorControls>
				<div className="wp-block-hanmoto-block hanmoto-block-wrapper">
					{ isbns.length > 0 ? (
						<ServerSideRender block={ HanmotoIsbnBlockVars.name } attributes={ attributes } />
					) : (
						<p className="hanmoto-block-empty">{ __( 'ISBNが設定されていません。入力してください。', 'hanmoto' ) }</p>
					) }
				</div>
			</>
		);
	},
	save: () => null,
} );
