<?php

namespace Hametuha\HanmotoHelper\Utility;

use Hametuha\HanmotoHelper\Models\ModelOrder;
use Hametuha\HanmotoHelper\Models\ModelOrderFax;

/**
 * Manage the orders which belong to a fax.
 *
 * 1つの注文が複数のFAX送付分に入ることがある（取次変更や再送）。
 * そのため注文側の _order_fax は複数値で、紐付けは組み合わせ単位で足し引きする。
 *
 * @package hanmoto
 */
trait OrderFaxOrders {

	use OrderFaxSlips;

	/**
	 * Get the order IDs of a fax.
	 *
	 * 印刷できない注文（返品・冊数なし）も含めて全部返す。
	 * 編集画面はこれを基準にしないと、紛れ込んだ注文を外せなくなる。
	 *
	 * @param int $fax_id Post ID of fax.
	 * @return int[]
	 */
	public function get_order_ids( $fax_id ) {
		return $this->parse_ids( get_post_meta( $fax_id, ModelOrderFax::META_ORDER ) );
	}

	/**
	 * Get the fax IDs which an order belongs to.
	 *
	 * @param int $order_id Post ID of order.
	 * @return int[]
	 */
	public function get_fax_ids( $order_id ) {
		return $this->parse_ids( get_post_meta( $order_id, ModelOrderFax::META_FAX ) );
	}

	/**
	 * Get the faxes which an order belongs to.
	 *
	 * 公開されている送付分に入っていれば「送付済み」、下書きだけなら「準備中」。
	 * FAXを送ったかどうかは送付分を公開したかどうかで表す。
	 *
	 * @param int $order_id Post ID of order.
	 * @return array[] id, title, status, sent and edit_url of each fax.
	 */
	public function get_faxes_of_order( $order_id ) {
		$faxes = [];
		foreach ( $this->get_fax_ids( $order_id ) as $id ) {
			$fax = get_post( $id );
			if ( ! $fax || ModelOrderFax::POST_TYPE !== $fax->post_type ) {
				// 消えた送付分への参照は無視する。
				continue;
			}
			$faxes[] = [
				'id'       => $id,
				'title'    => get_the_title( $fax ),
				'status'   => $fax->post_status,
				'sent'     => 'publish' === $fax->post_status,
				'edit_url' => (string) get_edit_post_link( $id, 'raw' ),
			];
		}
		return $faxes;
	}

	/**
	 * Get the other faxes which an order belongs to.
	 *
	 * @param int $order_id Post ID of order.
	 * @param int $fax_id   Post ID of fax to exclude.
	 * @return array[]
	 */
	public function get_other_faxes( $order_id, $fax_id ) {
		$others = [];
		foreach ( $this->get_faxes_of_order( $order_id ) as $fax ) {
			if ( (int) $fax_id !== $fax['id'] ) {
				$others[] = $fax;
			}
		}
		return $others;
	}

	/**
	 * Attach orders to a fax.
	 *
	 * 一括操作とちがって、他の送付分に入っている注文でも受け入れる。
	 * 取次が変わって送り直すことがあるため、判断は人間に任せる。
	 *
	 * @param int   $fax_id    Post ID of fax.
	 * @param int[] $order_ids Post IDs of orders.
	 * @return array added and skipped count.
	 */
	public function attach_orders( $fax_id, $order_ids ) {
		$attached = $this->get_order_ids( $fax_id );
		$added    = 0;
		$skipped  = 0;
		foreach ( $this->parse_ids( $order_ids ) as $order_id ) {
			$order = get_post( $order_id );
			if ( ! $order || ModelOrder::post_type() !== $order->post_type ) {
				++$skipped;
				continue;
			}
			if ( in_array( $order_id, $attached, true ) ) {
				++$skipped;
				continue;
			}
			add_post_meta( $fax_id, ModelOrderFax::META_ORDER, $order_id );
			add_post_meta( $order_id, ModelOrderFax::META_FAX, $fax_id );
			$attached[] = $order_id;
			++$added;
		}
		return [
			'added'   => $added,
			'skipped' => $skipped,
		];
	}

	/**
	 * Detach orders from a fax.
	 *
	 * 該当する組み合わせだけ消す。他の送付分に残っていればその注文は送付済みのまま。
	 *
	 * @param int   $fax_id    Post ID of fax.
	 * @param int[] $order_ids Post IDs of orders.
	 * @return int Detached count.
	 */
	public function detach_orders( $fax_id, $order_ids ) {
		$attached = $this->get_order_ids( $fax_id );
		$detached = 0;
		foreach ( $this->parse_ids( $order_ids ) as $order_id ) {
			if ( ! in_array( $order_id, $attached, true ) ) {
				continue;
			}
			delete_post_meta( $fax_id, ModelOrderFax::META_ORDER, $order_id );
			delete_post_meta( $order_id, ModelOrderFax::META_FAX, $fax_id );
			++$detached;
		}
		return $detached;
	}

	/**
	 * Search the orders which can be added to a fax.
	 *
	 * 書店名・書名・注文IDで引く。すでにこの送付分に入っている注文と、
	 * 短冊にできない注文（返品・冊数なし）は候補から外す。
	 * 他の送付分に入っている注文は外さず、警告付きで返す。
	 *
	 * @param string $query  Search query.
	 * @param int    $fax_id Post ID of fax.
	 * @return array[]
	 */
	public function search_candidates( $query, $fax_id ) {
		$query = trim( (string) $query );
		if ( '' === $query ) {
			return [];
		}
		$found = [];
		// 注文IDそのもの。
		if ( preg_match( '/\A[0-9]+\z/', $query ) ) {
			$found[] = (int) $query;
		}
		// 書店名から。
		$term_ids = get_terms( [
			'taxonomy'   => 'supplier',
			'name__like' => $query,
			'hide_empty' => false,
			'fields'     => 'ids',
		] );
		if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) {
			$found = array_merge( $found, get_posts( [
				'post_type'   => ModelOrder::post_type(),
				'post_status' => 'any',
				'numberposts' => 100,
				'fields'      => 'ids',
				'tax_query'   => [
					[
						'taxonomy' => 'supplier',
						'terms'    => $term_ids,
					],
				],
			] ) );
		}
		// 書名から。tax_query と post_parent は OR にできないので別に引く。
		$product_ids = get_posts( [
			'post_type'   => 'product',
			'post_status' => 'any',
			'numberposts' => 20,
			'fields'      => 'ids',
			's'           => $query,
		] );
		if ( ! empty( $product_ids ) ) {
			$found = array_merge( $found, get_posts( [
				'post_type'       => ModelOrder::post_type(),
				'post_status'     => 'any',
				'numberposts'     => 100,
				'fields'          => 'ids',
				'post_parent__in' => $product_ids,
			] ) );
		}
		$found = array_diff( $this->parse_ids( $found ), $this->get_order_ids( $fax_id ) );
		if ( empty( $found ) ) {
			return [];
		}
		$orders  = get_posts( [
			'post_type'   => ModelOrder::post_type(),
			'post_status' => 'any',
			'numberposts' => 20,
			'post__in'    => array_values( $found ),
			'orderby'     => 'date',
			'order'       => 'DESC',
		] );
		$results = [];
		foreach ( $orders as $order ) {
			$amount = (int) get_post_meta( $order->ID, '_amount', true );
			if ( 0 >= $amount ) {
				// 返品は発注の短冊に混ぜられない。
				continue;
			}
			$results[] = $this->order_summary( $order, $amount, $fax_id );
		}
		return $results;
	}

	/**
	 * Summarize an order for the editor screen.
	 *
	 * @param \WP_Post $order  Order post.
	 * @param int      $amount Amount of books.
	 * @param int      $fax_id Post ID of fax.
	 * @return array
	 */
	public function order_summary( $order, $amount, $fax_id ) {
		$shop = $this->get_bookshop_term( $order->ID );
		return [
			'id'          => $order->ID,
			'date'        => mysql2date( 'Y-m-d', $order->post_date ),
			'shop_name'   => $shop ? $this->shop_display_name( $shop->name ) : '',
			'title'       => $order->post_parent ? get_the_title( $order->post_parent ) : '',
			'amount'      => $amount,
			'printable'   => 0 < $amount,
			'edit_url'    => (string) get_edit_post_link( $order->ID, 'raw' ),
			'other_faxes' => $this->get_other_faxes( $order->ID, $fax_id ),
		];
	}

	/**
	 * Summarize all the orders of a fax.
	 *
	 * @param int $fax_id Post ID of fax.
	 * @return array[]
	 */
	public function get_order_summaries( $fax_id ) {
		$ids = $this->get_order_ids( $fax_id );
		if ( empty( $ids ) ) {
			return [];
		}
		$orders  = get_posts( [
			'post_type'   => ModelOrder::post_type(),
			'post_status' => 'any',
			'numberposts' => -1,
			'post__in'    => $ids,
			'orderby'     => 'date',
			'order'       => 'ASC',
		] );
		$results = [];
		foreach ( $orders as $order ) {
			$amount    = (int) get_post_meta( $order->ID, '_amount', true );
			$results[] = $this->order_summary( $order, $amount, $fax_id );
		}
		return $results;
	}

	/**
	 * Summarize the printing stats of a fax.
	 *
	 * @param int $fax_id Post ID of fax.
	 * @return array total, books and pages.
	 */
	public function get_stats( $fax_id ) {
		$slips = $this->get_slips( $fax_id );
		return [
			'total' => count( $slips ),
			'books' => $this->count_books( $slips ),
			'pages' => (int) ceil( count( $slips ) / ModelOrderFax::PER_PAGE ),
		];
	}
}
