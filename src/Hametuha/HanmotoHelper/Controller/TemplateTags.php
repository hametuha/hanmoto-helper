<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\Renderer;
use project\Controller\TodoController;

/**
 * Template tags.
 *
 * @package hanmoto-helper
 */
class TemplateTags extends Singleton {

	use Renderer;

	/**
	 * Get list of books.
	 *
	 * @return array
	 */
	public static function get_list( $lifetime = 1800, $country_code = '4', $allow_979 = true ) {
		$ids      = self::setting()->get_publisher_ids();
		$key      = 'hanmoto_list_' . md5( implode( ',', [ implode( ',', $ids ), $country_code, $allow_979 ? '1' : '0' ] ) );
		$cash     = get_transient( $key );
		if ( false !== $cash ) {
			return $cash;
		}
		$filtered = self::get_instance()->openbd_filter( $ids, $country_code, $allow_979 );
		if ( empty( $filtered ) ) {
			return [];
		}
		$books = self::get_instance()->openbd_get( $filtered );
		if ( is_wp_error( $books ) ) {
			return [];
		}
		// Sort by published date.
		usort( $books, function( $a, $b ) {
			$a_date = $a['summary']['pubdate'] ?? 0;
			$b_date = $b['summary']['pubdate'] ?? 0;
			if ( $a_date === $b_date ) {
				return 0;
			} else {
				return $a_date > $b_date ? -1 : 1;
			}
		} );
		set_transient( $key, $books, $lifetime );
		return $books;
	}

	/**
	 * @param int    $limit    Limit of
	 * @param string $template
	 *
	 * @return false|string
	 */
	public static function render_list( $limit = 12, $template = '' ) {
		$books  = self::get_list();
		if ( empty( $books ) ) {
			return '';
		}
		ob_start();
		$counter = 0;
		foreach ( $books as $book ) {
			$counter++;
			if ( $counter > $limit ) {
				break;
			}
			self::get_instance()->load_template_part( 'template-parts/hanmoto/book', $template, [
				'book' => $book,
			] );
		}
		$result = ob_get_contents();
		ob_end_clean();
		return $result;
	}

	/**
	 * Getter for settting.
	 *
	 * @return Settings
	 */
	private static function setting() {
		return Settings::get_instance();
	}
}
