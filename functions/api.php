<?php
/**
 * API contact methods
 *
 * @package isbn-beautify
 */

/**
 * Make request to OpenBD
 *
 * @param string $endpoint Endpoint name.
 * @param string $method GET or POST.
 * @param array $params Query parameter.
 *
 * @return object|array|WP_Error
 */
function isbnb_request( $endpoint, $method = 'GET', $params = [] ) {
	$endpoint = untrailingslashit( 'https://api.openbd.jp/v1/' . ltrim( $endpoint, '/' ) );
	$method   = strtoupper( $method );
	$params   = (array) $params;
	$curl_opt = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_TIMEOUT        => 5,
	];
	switch ( $method ) {
		case 'GET':
			$endpoint = add_query_arg( $params, $endpoint );
			break;
		case 'POST':
			$curl_opt[ CURLOPT_POST ] = true;
			$params_escaped           = [];
			foreach ( $params as $key => $value ) {
				$params_escaped[ rawurlencode( $key ) ] = rawurlencode( $value );
			}
			$curl_opt[ CURLOPT_POSTFIELDS ] = $params_escaped;
			break;
		default:
			return new WP_Error( 400, sprintf( __( 'Method %s is not allowed.', 'isbn-beautify' ), $method ) );
			break;
	}
	$curl_opt[ CURLOPT_URL ] = $endpoint;
	$ch                      = curl_init();
	curl_setopt_array( $ch, $curl_opt );
	$result = curl_exec( $ch );
	if ( ! $result ) {
		$err = curl_error( $ch );
		$no  = curl_errno( $ch );
		curl_close( $ch );

		return new WP_Error( 500, sprintf( 'OpenBD API returns error: %s %s', $no, $err ) );
	}
	curl_close( $ch );
	$response = json_decode( $result );
	if ( ! $response ) {
		return new WP_Error( 500, __( 'Failed to parse response. something might be wrong.', 'isbn-beautify' ) );
	}

	return $response;
}

/**
 * Get detail text per label.
 *
 * @param stdClass $data JSON object from API.
 * @param string $type Text type.
 *
 * @return string
 */
function isbnb_grab_detail( $data, $type = '02' ) {
	$type = sprintf( '%02d', $type );
	$text = '';
	foreach ( $data->onix->CollateralDetail->TextContent as $content ) {
		if ( $type === $content->TextType ) {
			$text = $content->Text;
			break;
		}
	}

	return $text;
}

/**
 * Get isbn data.
 *
 * @param string|array $isbn Single ISBN or CSV ISBN, array of ISBN.
 * @param int $life_time Cache time. If set 0, no cache.
 *
 * @return array|WP_Error
 */
function isbnb_get_data( $isbn, $life_time = 3600 ) {
	$isbn  = (array) $isbn;
	$isbn  = implode( ',', array_map( 'trim', $isbn ) );
	$key   = 'isbn_' . str_replace( ',', '_', $isbn );
	$cache = get_transient( $key );
	if ( ( false === $cache ) || ( 0 === $life_time ) ) {
		$param = [
			'isbn' => $isbn,
		];
		$cache = isbnb_request( 'get', 'GET', $param );
		if ( $life_time && ! is_wp_error( $cache ) ) {
			set_transient( $key, $cache, $life_time );
		}
	}

	return $cache;
}

/**
 * Get markup
 *
 * @param string|array $isbn Single ISBN, list of ISBN(CSV or array).
 *
 * @return string|WP_Error
 */
function isbnb_display( $isbn ) {
	$isbns = isbnb_get_data( $isbn );
	if ( is_wp_error( $isbns ) ) {
		return $isbns;
	}
	$html = [];
	foreach ( $isbns as $data ) {
		ob_start();
		?>
        <div class="isbnb-item">
			<?php if ( $data->summary->cover ) : ?>
                <div class="isbnb-item-image">
                    <img src="<?= esc_attr( $data->summary->cover ) ?>">
                </div>
			<?php endif; ?>
            <div class="isbnb-item-content">
                <div class="isbnb-item-title">
					<?= esc_html( $data->summary->title ) ?>
                </div>
                <div class="isbnb-item-meta">
                    <table class="isbnb-item-meta-list">
                        <tr>
                            <th><?php _e( 'Author', 'isbn-beautify' ) ?></th>
                            <td><?= esc_html( $data->summary->author ) ?></td>
                        </tr>
                        <tr>
                            <th><?php _e( 'Publisher', 'isbn-beautify' ) ?></th>
                            <td><?= esc_html( $data->summary->publisher ) ?></td>
                        </tr>
                        <tr>
                            <th><?php _e( 'ISBN', 'isbn-beautify' ) ?></th>
                            <td><?= esc_html( $data->summary->isbn ) ?></td>
                        </tr>
                        <tr>
                            <th><?php _e( 'Publish Date', 'isbn-beautify' ) ?></th>
                            <td><?= mysql2date( get_option( 'date_format' ), preg_replace( '#(\d{4})(\d{2})(\d{2})#', '$1-$2-$e', $data->summary->pubdate ) ) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
			<?php if ( $detail = isbnb_grab_detail( $data ) ) : ?>
                <div class="isbnb-item-desc">
					<?= wpautop( $detail ) ?>
                </div>
			<?php endif; ?>
        </div>
		<?php
		$markup = ob_get_contents();
		ob_end_clean();
		/**
		 * isbn_beautify_item_markup
		 *
		 * Markup to display ISBN data.
		 *
		 * @since 1.0.0
		 * @package isbn-beautify
		 *
		 * @param string $markup
		 * @param object $data
		 */
		$html[] = apply_filters( 'isbn_beautify_item_markup', $markup, $data );
	}
	if ( empty( $html ) ) {
		return '';
	}
	array_unshift( $html, '<div class="isbnb-collection">' );
	$html[] = '</div>';
	/**
	 * isbn_beautify_collection_markup
	 *
	 * Markup for ISBN list.
	 *
	 * @param array $html List of markup.
	 * @param array $isbns List of ISBN data.
	 *
	 * @return array
	 */
	$html = apply_filters( 'isbn_beautify_collection_markup', $html, $isbns );

	return implode( "\n", $html );
}
