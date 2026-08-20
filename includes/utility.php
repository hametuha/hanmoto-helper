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
	$links     = [];
	$published = $book['summary']['pubdate'] <= date_i18n( 'Ymd' );
	$label     = function ( $brand, $is_published ) {
		// translators: %s is a brand name.
		return sprintf( $is_published ? __( '%sで買う', 'hanmoto' ) : __( '%sで予約', 'hanmoto' ), $brand );
	};
	// Hanmoto.
	$title   = __( '版元ドットコム', 'hanmoto' );
	$links[] = [
		'id'        => 'hanmoto',
		'label'     => $title,
		'title'     => $label( $title, $published ),
		'published' => $published,
		'url'       => sprintf( 'https://www.hanmoto.com/bd/isbn/%s', $book['summary']['isbn'] ),
		'sponsored' => false,
	];
	// Amazon.
	$associate = \Hametuha\HanmotoHelper\Controller\Settings::get_instance()->get_setting( 'associate_id' ) ?: 'hametuha-22';
	$title     = __( 'Amazon', 'hanmoto' );
	$links[]   = [
		'id'        => 'amazon',
		'label'     => $title,
		'title'     => $label( $title, $published ),
		'published' => $published,
		'url'       => sprintf( 'https://www.amazon.co.jp/dp/%s?tag=%s&linkCode=ogi&th=1&psc=1&language=ja_JP', hanmoto_isbn10( $book['summary']['isbn'] ), $associate ),
		'sponsored' => true,
	];
	// Rakuten.
	$rakuten_link = hanmoto_rakuten_url( $book['summary']['isbn'] );
	if ( ! is_wp_error( $rakuten_link ) ) {
		$title   = __( '楽天ブックス', 'hanmoto' );
		$links[] = [
			'id'        => 'rakuten',
			'label'     => $title,
			'title'     => $label( $title, $published ),
			'url'       => $rakuten_link,
			'published' => $published,
			'sponsored' => true,
		];
	}
	// Original store.
	if ( ! empty( $book['hanmoto']['storelink'] ) ) {
		$title   = __( '直販', 'hanmoto' );
		$links[] = [
			'id'        => 'direct',
			'label'     => $title,
			'url'       => $book['hanmoto']['storelink'],
			'title'     => $label( $title, $published ),
			'published' => $published,
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
		$total += (int) $letter * ( 10 - $i );
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
	$settings           = \Hametuha\HanmotoHelper\Controller\Settings::get_instance();
	$rakuten_app_id     = $settings->get_setting( 'rakuten_app_id' );
	$rakuten_access_key = $settings->get_setting( 'rakuten_access_key' );
	if ( empty( $rakuten_app_id ) || empty( $rakuten_access_key ) ) {
		return new WP_Error( 'no_credentials', __( '楽天アプリIDまたはアクセスキーが設定されていません。', 'hanmoto' ) );
	}
	$rakuten_affiliate_id = $settings->get_setting( 'rakuten_affiliate_id' ) ?: '0e9cde67.8fb388cd.0e9cde68.6632f7db';
	// Check cache exists.
	$cache = wp_cache_get( $isbn, 'hanmoto_rakuten' );
	if ( false !== $cache ) {
		return $cache;
	}
	// Generate request URL.
	// NOTE: 2026年の移行でドメインが app.rakuten.co.jp から openapi.rakuten.co.jp へ変更され、
	// accessKey による認証が必須になった。エンドポイントのパスとバージョンは従来通り。
	$url = add_query_arg( [
		'format'        => 'json',
		'isbn'          => $isbn,
		'affiliateId'   => $rakuten_affiliate_id,
		'applicationId' => $rakuten_app_id,
		'accessKey'     => $rakuten_access_key,
	], 'https://openapi.rakuten.co.jp/services/api/BooksBook/Search/20170404' );
	// 2026年の移行後、新APIは Origin / Referer の両ヘッダを要求し、
	// その値はアプリに登録した「リファラ」と一致している必要がある。
	// home_url() を使うことで環境（本番・ローカル）ごとに登録済みドメインを送る。
	$referrer = apply_filters( 'hanmoto_rakuten_referrer', home_url( '/' ) );
	// Get result.
	$result = wp_remote_get( $url, [
		'headers' => [
			'Origin'  => $referrer,
			'Referer' => $referrer,
		],
	] );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$code = (int) wp_remote_retrieve_response_code( $result );
	if ( 200 !== $code ) {
		// 403(リファラ不許可)や429(レート制限)を「結果なし」と誤認しないよう、実エラーを返す。
		return new WP_Error( 'rakuten_http_error', sprintf(
			// translators: %1$d is HTTP status code, %2$s is response body.
			__( '楽天APIがHTTP %1$dを返しました: %2$s', 'hanmoto' ),
			$code,
			wp_remote_retrieve_body( $result )
		) );
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
