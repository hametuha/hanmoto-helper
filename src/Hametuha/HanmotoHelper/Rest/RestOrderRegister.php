<?php

namespace Hametuha\HanmotoHelper\Rest;

use Hametuha\HanmotoHelper\Models\ModelOrder;
use Hametuha\HanmotoHelper\Pattern\RestApiPattern;
use Hametuha\HanmotoHelper\Utility\BookSelector;

/**
 * Order bulk register REST API.
 *
 * @package hanmoto
 */
class RestOrderRegister extends RestApiPattern {

	use BookSelector;

	/**
	 * {@inheritdoc}
	 */
	protected function route() {
		return 'orders/bulk';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function methods() {
		return [ 'POST' ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_arguments( $method ) {
		return [
			'orders' => [
				'required'    => true,
				'type'        => 'array',
				'description' => 'Array of order data',
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function callback( $request ) {
		$orders  = $request->get_param( 'orders' );
		$success = 0;
		$failed  = 0;
		$errors  = [];

		foreach ( $orders as $index => $order ) {
			$result = $this->create_order( $order, $index );
			if ( is_wp_error( $result ) ) {
				++$failed;
				$errors[] = [
					'index'   => $index,
					'message' => $result->get_error_message(),
				];
			} else {
				++$success;
			}
		}

		return new \WP_REST_Response( [
			'success' => $success,
			'failed'  => $failed,
			'errors'  => $errors,
		], 200 );
	}

	/**
	 * Create a single order.
	 *
	 * @param array $order Order data.
	 * @param int   $index Row index for error reporting.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	private function create_order( $order, $index ) {
		$row_num = $index + 1;

		// Get or create shop.
		$shop = $this->resolve_shop( $order, $row_num );
		if ( is_wp_error( $shop ) ) {
			return $shop;
		}

		// Validate book.
		$book = get_post( $order['book_id'] ?? 0 );
		if ( ! $book || 'product' !== $book->post_type ) {
			// translators: %d is row number.
			return new \WP_Error( 'invalid_book', sprintf( __( '行 %d: 書籍が見つかりません', 'hanmoto' ), $row_num ) );
		}

		// Validate date.
		$date = $this->parse_date( $order['order_date'] ?? '' );
		if ( ! $date ) {
			// translators: %d is row number.
			return new \WP_Error( 'invalid_date', sprintf( __( '行 %d: 日付形式が不正です', 'hanmoto' ), $row_num ) );
		}

		// Create order post.
		$order_id = wp_insert_post( [
			'post_type'    => ModelOrder::post_type(),
			'post_date'    => $date,
			'post_status'  => 'publish',
			'post_title'   => '',
			'post_content' => '',
			'post_excerpt' => sanitize_textarea_field( $order['note'] ?? '' ),
			'post_parent'  => $book->ID,
		] );

		if ( ! $order_id || is_wp_error( $order_id ) ) {
			// translators: %d is row number.
			return new \WP_Error( 'insert_failed', sprintf( __( '行 %d: 登録に失敗しました', 'hanmoto' ), $row_num ) );
		}

		// Save meta.
		update_post_meta( $order_id, '_amount', intval( $order['amount'] ?? 0 ) );
		update_post_meta( $order_id, '_in_charge_of', sanitize_text_field( $order['in_charge'] ?? '' ) );

		// Assign shop taxonomy.
		wp_set_object_terms( $order_id, $shop->term_id, 'supplier' );

		// Assign source taxonomy.
		if ( ! empty( $order['source'] ) ) {
			wp_set_object_terms( $order_id, [ $order['source'] ], 'source' );
		}

		return $order_id;
	}

	/**
	 * Resolve shop from order data.
	 *
	 * @param array $order   Order data.
	 * @param int   $row_num Row number for error messages.
	 * @return \WP_Term|\WP_Error Term on success, WP_Error on failure.
	 */
	private function resolve_shop( $order, $row_num ) {
		$shop_id    = $order['shop_id'] ?? null;
		$shop_name  = trim( $order['shop_name'] ?? '' );
		$wholesaler = trim( $order['wholesaler'] ?? '' );
		$line_code  = trim( $order['line_code'] ?? '' );
		$shop_code  = trim( $order['shop_code'] ?? '' );

		// If shop_id is provided and valid, use it.
		if ( $shop_id ) {
			$shop = get_term( $shop_id, 'supplier' );
			if ( $shop && ! is_wp_error( $shop ) ) {
				// Check if codes changed, update if needed.
				$this->maybe_update_shop_meta( $shop->term_id, $wholesaler, $line_code, $shop_code );
				return $shop;
			}
		}

		// Shop name is required for new shops.
		if ( empty( $shop_name ) ) {
			// translators: %d is row number.
			return new \WP_Error( 'invalid_shop', sprintf( __( '行 %d: 書店名を入力してください', 'hanmoto' ), $row_num ) );
		}

		// Use the BookSelector trait method to get or create shop.
		$shop = $this->get_bookshop( $shop_name, $wholesaler, $line_code, $shop_code, true );
		if ( is_wp_error( $shop ) ) {
			// translators: %d is row number.
			return new \WP_Error( 'shop_error', sprintf( __( '行 %d: 書店の作成に失敗しました', 'hanmoto' ), $row_num ) );
		}

		return $shop;
	}

	/**
	 * Update shop meta if values changed.
	 *
	 * @param int    $term_id    Term ID.
	 * @param string $wholesaler Wholesaler.
	 * @param string $line_code  Line code.
	 * @param string $shop_code  Shop code.
	 */
	private function maybe_update_shop_meta( $term_id, $wholesaler, $line_code, $shop_code ) {
		if ( get_term_meta( $term_id, 'wholesaler', true ) !== $wholesaler ) {
			update_term_meta( $term_id, 'wholesaler', $wholesaler );
		}
		if ( get_term_meta( $term_id, 'line_code', true ) !== $line_code ) {
			update_term_meta( $term_id, 'line_code', $line_code );
		}
		if ( get_term_meta( $term_id, 'shop_code', true ) !== $shop_code ) {
			update_term_meta( $term_id, 'shop_code', $shop_code );
		}
	}

	/**
	 * Parse date string to Y-m-d H:i:s format.
	 *
	 * @param string $date Date string.
	 * @return string|false Formatted date or false on failure.
	 */
	private function parse_date( $date ) {
		if ( empty( $date ) ) {
			return false;
		}
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return false;
		}
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
