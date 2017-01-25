<?php

// Enqueue style.
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'isbnb-beautify', isbnb_asset_url( '/assets/css/isbnb-beautify.css' ), [], '1.0.0' );
} );

add_editor_style( isbnb_asset_url( '/assets/css/isbnb-beautify.css' ) );
