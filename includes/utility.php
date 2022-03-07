<?php
/**
 * String utility.
 *
 * @package hanmoto
 */


/**
 * Get publishing date.
 *
 * @param array  $book   Book information via API.
 * @param string $format Default is same as WordPress Date format.
 * @param string $empty  The string if
 * @return string
 */
function hanmoto_publish_date( $book, $format = '', $empty = '-' ) {
	if ( ! $format ) {
		$format = get_option( 'date_format' );
	}
	if ( empty( $book['summary']['pubdate'] ) ) {
		return $empty;
	}
	$date = preg_replace( '/(\d{4})(\d{2})(\d{2})/u', '$1-$2-$3 00:00:00', $book['summary']['pubdate'] );
	return mysql2date( $format, $date );
}

/**
 * Get hanmoto actions.
 *
 * @param array $book Book
 * @return array
 */
function hanmoto_actions( $book ) {
	$links = [];
	// Hanmoto.
	$links[] = [
		'id'        => 'hanmoto',
		'label'     => __( '版元ドットコム', 'hanmoto' ),
		'url'       => sprintf( 'https://www.hanmoto.com/bd/isbn/%s', $book['summary']['isbn'] ),
		'sponsored' => false,
	];
	// Amazon.
	$associate = \Hametuha\HanmotoHelper\Controller\Settings::get_instance()->get_setting( 'associate_id' ) ?: 'hametuha-22';
	$links[]   = [
		'id'        => 'amazon',
		'label'     => __( 'Amazon', 'hanmoto' ),
		'url'       => sprintf( 'https://www.amazon.co.jp/dp/%s?tag=%s&linkCode=ogi&th=1&psc=1&language=ja_JP', hanmoto_isbn10( $book['summary']['isbn'] ), $associate ),
		'sponsored' => true,
	];
	// Rakuten.
	$rakuten_link = hanmoto_rakuten_url( $book['summary']['isbn'] );
	if ( ! is_wp_error( $rakuten_link ) ) {
		$links[] = [
			'id'        => 'rakuten',
			'label'     => __( '楽天ブックス', 'hanmoto' ),
			'url'       => $rakuten_link,
			'sponsored' => true,
		];
	}
	// Original store.
	if ( ! empty( $book['hanmoto']['storelink'] ) ) {
		$links[] = [
			'id'        => 'direct',
			'label'     => __( '直販', 'hanmoto' ),
			'url'       => $book['hanmoto']['storelink'],
			'sponsored' => false,
		];
	}
	return $links;
}

/**
 * Get ISBN10 from ISBN13
 *
 * @param string $isbn13 ISBN code.
 * @return string
 */
function hanmoto_isbn10( $isbn13 ) {
	// Strip country and check digit.
	$isbn9 = preg_replace( '/^\d{3}(\d{9})\d$/u', '$1', $isbn13 );
	$total = 0;
	for ( $i = 0; $i < 9; $i++ ) {
		$letter = substr( $isbn9, $i, 1 );
		$total += $letter * ( 10 - $i );
	}
	$remainder = 11 - ( $total % 11 );
	switch ( $remainder ) {
		case 11:
			$cd = 0;
			break;
		case 10:
			$cd = 'x';
			break;
		default:
			$cd = $remainder;
			break;
	}
	return $isbn9 . $cd;
}

/**
 * Get rakuten search result.
 *
 * @param string $isbn ISBN.
 *
 * @return array|WP_Error
 */
function hanmoto_rakuten_product( $isbn ) {
	$rakuten_app_id = \Hametuha\HanmotoHelper\Controller\Settings::get_instance()->get_setting( 'rakuten_app_id' );
	if ( empty( $rakuten_app_id ) ) {
		return new WP_Error( 'no_app_id', __( '楽天アプリIDが設定されていません。', 'hanmoto' ) );
	}
	$rakuten_affiliate_id = \Hametuha\HanmotoHelper\Controller\Settings::get_instance()->get_setting( 'rakuten_affiliate_id' ) ?: '0e9cde67.8fb388cd.0e9cde68.6632f7db';
	// Check cache exists.
	$cache = wp_cache_get( $isbn, 'hanmotoo_rakuten' );
	if ( false !== $cache ) {
		return $cache;
	}
	// Generate request URL.
	$url = add_query_arg( [
		'format'        => 'json',
		'isbn'          => $isbn,
		'affiliateId'   => $rakuten_affiliate_id,
		'applicationId' => $rakuten_app_id,
	], 'https://app.rakuten.co.jp/services/api/BooksBook/Search/20170404' );
	// Get result.
	$result = wp_remote_get( $url );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$response = json_decode( $result['body'], true );
	if ( empty( $response['Items'] ) ) {
		return new WP_Error( 'no_result', __( '検索結果が見つかりませんでした。', 'hanmoto' ) );
	}
	foreach ( $response['Items'] as $item ) {
		$data = $item['Item'];
		wp_cache_set( $isbn, $data, 'hanmoto_rakuten', 60 * 30 );
		return $data;
	}
}

/**
 * Get rakuten link.
 *
 * @param string $isbn ISBN.
 *
 * @return string|WP_Error
 */
function hanmoto_rakuten_url( $isbn ) {
	$book = hanmoto_rakuten_product( $isbn );
	if ( is_wp_error( $book ) ) {
		return $book;
	}
	return $book['affiliateUrl'];
}
