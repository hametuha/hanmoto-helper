<?php

namespace Hametuha\HanmotoHelper\Utility;

/**
 * Validate utility.
 *
 * @package hanmoto
 */
trait Validator {

	/**
	 * Is date format?
	 *
	 * @param string $date Date format.
	 * @return bool
	 */
	public function is_date( $date ) {
		return (bool) preg_match( '/\d{4}-\d{2}-\d{2}/u', $date );
	}

	/**
	 * Is date?
	 *
	 * @param string $date Date format.
	 * @return bool
	 */
	public function is_date_or_empty( $date ) {
		return empty( $date ) || $this->is_date( $date );
	}
}
