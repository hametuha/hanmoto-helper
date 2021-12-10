<?php

namespace Hametuha\HanmotoHelper\Utility;

/**
 * Renderer.
 *
 * @package hanmoto
 */
trait Renderer {

	use OpenDbApi;

	/**
	 * Load template.
	 *
	 * @param string $name   Path to file.
	 * @param string $suffix If specified, load in prior.
	 * @param array  $args   Arguments.
	 */
	protected function load_template_part( $name, $suffix = '', $args = [] ) {
		if ( false === get_template_part( $name, $suffix, $args ) ) {
			$templates = [ hanmoto_root_dir() . '/' . $name . '.php' ];
			if ( $suffix ) {
				array_unshift( $templates, hanmoto_root_dir() . '/' . $name . '.php' );
			}
			$templates = apply_filters( 'hanmoto_template_files', $templates, $name, $suffix, $args );
			foreach ( $templates as $located ) {
				if ( file_exists( $located ) ) {
					load_template( $located, false, $args );
					break;
				}
			}
		}
	}

	/**
	 * Get ISBN block.
	 *
	 * @param string|string[] $isbn
	 * @param string          $style
	 * @return string
	 */
	public function render_isbn( $isbn, $style = 'card' ) {
		$books = $this->openbd_get( $isbn );
		if ( empty( $books ) ) {
			return '';
		}
		ob_start();
		printf( '<div class="hanmoto-books hanmoto-books-%s">', esc_attr( $style ) );
		foreach ( $books as $book ) {
			$this->load_template_part( 'template-parts/hanmoto/book', $style, [ 'book' => $book ] );
		}
		echo '</div>';
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
}
