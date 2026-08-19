<?php

namespace Hametuha\HanmotoHelper\Utility;

use Hametuha\HanmotoHelper\Controller\PostType;
use Hametuha\HanmotoHelper\Models\ModelOrder;
use Hametuha\HanmotoHelper\Models\ModelOrderFax;

/**
 * Build the slips(短冊) of a fax.
 *
 * 短冊1枚に刷る値をすべて解決する。印刷テンプレートと編集画面の両方が使う。
 * ID配列の正規化(parse_ids)もここに置いてある。紐付けの追加・削除でも同じ正規化が必要で、
 * 別トレイトに分けると暗黙の依存になるため。
 *
 * @package hanmoto
 */
trait OrderFaxSlips {

	/**
	 * Convert comma separated IDs to the array of order IDs.
	 *
	 * @param string|int[] $ids IDs to parse.
	 * @return int[]
	 */
	protected function parse_ids( $ids ) {
		if ( ! is_array( $ids ) ) {
			$ids = explode( ',', (string) $ids );
		}
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		return $ids;
	}

	/**
	 * Get the slips of a fax post.
	 *
	 * 印刷に必要な値をすべて解決して返す。テンプレートはこれを並べるだけでよい。
	 * 返品などで短冊にできないものは除外する。
	 *
	 * @param int $post_id Post ID of fax.
	 * @return array[]
	 */
	public function get_slips( $post_id ) {
		$ids = $this->parse_ids( get_post_meta( $post_id, ModelOrderFax::META_ORDER ) );
		if ( empty( $ids ) ) {
			return [];
		}
		$orders = get_posts( [
			'post_type'   => ModelOrder::post_type(),
			'post__in'    => $ids,
			'post_status' => 'any',
			'numberposts' => -1,
		] );
		$slips  = [];
		foreach ( $orders as $order ) {
			$amount = (int) get_post_meta( $order->ID, '_amount', true );
			if ( 0 >= $amount ) {
				continue;
			}
			$slips[] = $this->build_slip( $order, $amount );
		}
		// 受注日順。同じ日は書店ごと・書名ごとにまとめると仕分けやすい。
		usort( $slips, function ( $a, $b ) {
			foreach ( [ 'date', 'shop_name', 'title' ] as $key ) {
				$compared = strcmp( $a[ $key ], $b[ $key ] );
				if ( 0 !== $compared ) {
					return $compared;
				}
			}
			return $a['order']->ID - $b['order']->ID;
		} );
		return $slips;
	}

	/**
	 * Resolve everything printed on a slip.
	 *
	 * @param \WP_Post $order  Order post.
	 * @param int      $amount Amount of books.
	 * @return array
	 */
	protected function build_slip( $order, $amount ) {
		$shop    = $this->get_bookshop_term( $order->ID );
		$sources = get_the_terms( $order, 'source' );
		$product = $order->post_parent ? get_post( $order->post_parent ) : null;
		$price   = $product ? get_post_meta( $product->ID, '_regular_price', true ) : '';
		return [
			'order'      => $order,
			'amount'     => $amount,
			'date'       => mysql2date( 'Y-m-d', $order->post_date ),
			'source'     => ( ! empty( $sources ) && ! is_wp_error( $sources ) ) ? $sources[0]->name : '',
			'in_charge'  => (string) get_post_meta( $order->ID, '_in_charge_of', true ),
			'note'       => (string) $order->post_excerpt,
			'shop_name'  => $shop ? $this->shop_display_name( $shop->name ) : '',
			'wholesaler' => $shop ? (string) get_term_meta( $shop->term_id, 'wholesaler', true ) : '',
			'line_code'  => $shop ? (string) get_term_meta( $shop->term_id, 'line_code', true ) : '',
			'shop_code'  => $shop ? (string) get_term_meta( $shop->term_id, 'shop_code', true ) : '',
			'title'      => $product ? get_the_title( $product ) : '',
			'authors'    => $product ? (string) get_post_meta( $product->ID, 'hanmoto_authors', true ) : '',
			'publisher'  => $product ? (string) get_post_meta( $product->ID, 'hanmoto_publisher', true ) : '',
			'isbn'       => $product ? (string) get_post_meta( $product->ID, PostType::META_KEY_ISBN, true ) : '',
			'price'      => is_numeric( $price ) ? (int) $price : 0,
		];
	}

	/**
	 * Normalize a bookshop name for printing.
	 *
	 * 全角英数のままだとブラウザがCJKとして1文字ずつ折り返すので、
	 * 「ＴＳＵＴＡＹＡ　ＢＯＯＫＳＴＯＲＥ」が単語の途中で切れてしまう。
	 * 半角に直せばスペースで折り返せる。書店名の登録は変えない。
	 *
	 * @param string $name Registered name of bookshop.
	 * @return string
	 */
	public function shop_display_name( $name ) {
		return mb_convert_kana( (string) $name, 'as' );
	}

	/**
	 * Should the bookshop name be shrunk to fit in a slip?
	 *
	 * 短冊の書店欄はおよそ半角40文字分。それを超えると3行になって窮屈になる。
	 *
	 * @param string $name Name of bookshop.
	 * @return bool
	 */
	public function is_long_shop_name( $name ) {
		return 40 < mb_strwidth( (string) $name );
	}

	/**
	 * Get the bookshop term of an order.
	 *
	 * @param int $order_id Post ID of order.
	 * @return \WP_Term|null
	 */
	protected function get_bookshop_term( $order_id ) {
		$terms = get_the_terms( $order_id, 'supplier' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}
		return $terms[0];
	}

	/**
	 * Count total books of slips.
	 *
	 * @param array[] $slips Slips.
	 * @return int
	 */
	public function count_books( $slips ) {
		$total = 0;
		foreach ( $slips as $slip ) {
			$total += $slip['amount'];
		}
		return $total;
	}

	/**
	 * Get publishers of slips.
	 *
	 * @param array[] $slips Slips.
	 * @return string
	 */
	public function get_publishers( $slips ) {
		$publishers = [];
		foreach ( $slips as $slip ) {
			if ( '' !== $slip['publisher'] && ! in_array( $slip['publisher'], $publishers, true ) ) {
				$publishers[] = $slip['publisher'];
			}
		}
		return implode( '・', $publishers );
	}

	/**
	 * Get order number printed on a slip.
	 *
	 * 旧システムはCSVの連番に経路を埋め込んでいたが、いまは注文経路がタクソノミーにある。
	 *
	 * @param array $slip Slip.
	 * @return string
	 */
	public function get_order_number( $slip ) {
		if ( $slip['source'] ) {
			// translators: %1$s is order source, %2$d is order ID.
			return sprintf( __( '%1$sNo.%2$d', 'hanmoto' ), $slip['source'], $slip['order']->ID );
		}
		// translators: %d is order ID.
		return sprintf( __( 'No.%d', 'hanmoto' ), $slip['order']->ID );
	}
}
