<?php

namespace Hametuha\HanmotoHelper\Utility;

/**
 * Handle the codes of a bookshop.
 *
 * 書店の取次・番線・書店コードは途中で変わることがある。
 * タームには常に現在のコードを保持し、過去のコードは former_codes に退避する。
 * 過去の注文を照合するときは former_codes も検索対象にする。
 *
 * @package hanmoto
 */
trait ShopCodes {

	/**
	 * Meta key to keep the codes of the past.
	 */
	const META_FORMER_CODES = 'former_codes';

	/**
	 * Normalize the codes of a bookshop.
	 *
	 * @param string $wholesaler Wholesaler.
	 * @param string $line_code  Line code(番線).
	 * @param string $shop_code  Shop code(書店コード).
	 * @return string[] wholesaler, line_code and shop_code.
	 */
	public function normalize_shop_codes( $wholesaler, $line_code, $shop_code ) {
		return [
			trim( (string) $wholesaler ),
			str_replace( '-', '', trim( (string) $line_code ) ),
			trim( (string) $shop_code ),
		];
	}

	/**
	 * Build the string which identifies the codes of a bookshop.
	 *
	 * @param string $wholesaler Wholesaler.
	 * @param string $line_code  Line code(番線).
	 * @param string $shop_code  Shop code(書店コード).
	 * @return string Empty string if all of them are empty.
	 */
	public function shop_codes_key( $wholesaler, $line_code, $shop_code ) {
		$codes = $this->normalize_shop_codes( $wholesaler, $line_code, $shop_code );
		if ( '' === implode( '', $codes ) ) {
			return '';
		}
		return implode( '/', $codes );
	}

	/**
	 * Get the codes of the past.
	 *
	 * @param int $term_id Term ID of bookshop.
	 * @return string[]
	 */
	public function get_former_shop_codes( $term_id ) {
		$codes = array_map( 'strval', (array) get_term_meta( $term_id, self::META_FORMER_CODES ) );
		return array_values( array_unique( $this->reject_empty( $codes ) ) );
	}

	/**
	 * Remove the empty strings from the array.
	 *
	 * @param string[] $values Values to filter.
	 * @return string[]
	 */
	protected function reject_empty( $values ) {
		return array_filter(
			$values,
			function ( $value ) {
				return '' !== $value;
			}
		);
	}

	/**
	 * Save the codes of the past.
	 *
	 * @param int      $term_id Term ID of bookshop.
	 * @param string[] $codes   Codes to keep.
	 * @return void
	 */
	public function set_former_shop_codes( $term_id, $codes ) {
		delete_term_meta( $term_id, self::META_FORMER_CODES );
		foreach ( array_unique( $this->reject_empty( array_map( 'strval', $codes ) ) ) as $code ) {
			add_term_meta( $term_id, self::META_FORMER_CODES, $code );
		}
	}

	/**
	 * Update the current codes and keep the replaced ones.
	 *
	 * @param int    $term_id    Term ID of bookshop.
	 * @param string $wholesaler Wholesaler.
	 * @param string $line_code  Line code(番線).
	 * @param string $shop_code  Shop code(書店コード).
	 * @return void
	 */
	public function update_shop_codes( $term_id, $wholesaler, $line_code, $shop_code ) {
		list( $wholesaler, $line_code, $shop_code ) = $this->normalize_shop_codes( $wholesaler, $line_code, $shop_code );
		$next                                       = $this->shop_codes_key( $wholesaler, $line_code, $shop_code );
		$current                                    = $this->shop_codes_key(
			get_term_meta( $term_id, 'wholesaler', true ),
			get_term_meta( $term_id, 'line_code', true ),
			get_term_meta( $term_id, 'shop_code', true )
		);
		if ( $current === $next ) {
			return;
		}
		if ( '' !== $current ) {
			// The replaced codes are still needed to find the orders of the past.
			$former   = $this->get_former_shop_codes( $term_id );
			$former[] = $current;
			$this->set_former_shop_codes( $term_id, array_diff( $former, [ $next ] ) );
		}
		update_term_meta( $term_id, 'wholesaler', $wholesaler );
		update_term_meta( $term_id, 'line_code', $line_code );
		update_term_meta( $term_id, 'shop_code', $shop_code );
	}

	/**
	 * Get a bookshop which had the codes in the past.
	 *
	 * @param string $wholesaler Wholesaler.
	 * @param string $line_code  Line code(番線).
	 * @param string $shop_code  Shop code(書店コード).
	 * @return \WP_Term|null
	 */
	public function get_shop_by_former_codes( $wholesaler, $line_code, $shop_code ) {
		$key = $this->shop_codes_key( $wholesaler, $line_code, $shop_code );
		if ( '' === $key ) {
			return null;
		}
		$term_query = new \WP_Term_Query( [
			'taxonomy'   => 'supplier',
			'number'     => 1,
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'   => self::META_FORMER_CODES,
					'value' => $key,
				],
			],
		] );
		$terms      = $term_query->get_terms();
		return empty( $terms ) ? null : $terms[0];
	}

	/**
	 * Parse the codes typed in a textarea.
	 *
	 * @param string $input One「取次/番線/書店コード」per line.
	 * @return string[]
	 */
	public function parse_shop_codes( $input ) {
		$codes = [];
		foreach ( preg_split( '/\R/u', (string) $input ) as $line ) {
			$parts = explode( '/', $line );
			if ( 3 !== count( $parts ) ) {
				continue;
			}
			$key = $this->shop_codes_key( $parts[0], $parts[1], $parts[2] );
			if ( '' !== $key ) {
				$codes[] = $key;
			}
		}
		return array_values( array_unique( $codes ) );
	}
}
