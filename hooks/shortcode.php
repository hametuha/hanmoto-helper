<?php

// Register shortcode.
add_shortcode( 'book', function( $atts = [], $content = '' ) {
	$atts = shortcode_atts( [
		'isbn' => '',
	], $atts, 'isbn' );
	if ( ! $atts['isbn'] ) {
		return '';
	}
	$markup = isbnb_display( $atts['isbn'] );
	if ( is_wp_error( $markup ) ) {
		return sprintf(
			'<div class="isbnb-item-error"><p>%s</p></div>',
			esc_html( $markup->get_error_message() )
		);
	}
	return $markup;
} );

// Register UI for Shortcake
add_action( 'register_shortcode_ui', function () {
	// Interviews.
	shortcode_ui_register_for_shortcode( 'book', [
		'label'         => __( 'Book Information', 'isbn-beautify' ),
		'post_type'     => [ 'post' ],
		'listItemImage' => 'dashicons-book-alt',
		'attrs'         => [
			[
				'label' => 'ISBN',
				'attr'  => 'isbn',
				'type'  => 'text',
			],
		],
	] );
} );

/**
 * List of book shortcode
 *
 * @param array  $atts    Attributes.
 * @param string $content Contents.
 * @return string
 */
add_shortcode( 'books', function( $atts = [], $content = '' ) {
	$atts = shortcode_atts( [
		'limit' => 12,
	], $atts, 'books' );
	return sprintf(
		'<div class="wp-block-hanmoto-list hanmoto-list-block">%s</div>',
		\Hametuha\HanmotoHelper\Controller\TemplateTags::render_list( $atts['limit'] )
	);
} );
