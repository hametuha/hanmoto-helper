<?php

/**
 * Get asset URL
 *
 * @param string $path
 *
 * @return string
 */
function isbnb_asset_url( $path ) {
	$path = ltrim( $path, '/' );
	return plugin_dir_url( __DIR__ ) . $path;
}
