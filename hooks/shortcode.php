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
				'label'    => 'ISBN',
				'attr'     => 'isbn',
				'type'     => 'text',
			],
		],
	] );
} );
