<?php

namespace Hametuha\HanmotoHelper\UI;


use Hametuha\HanmotoHelper\Models\ModelOrder;
use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\BookSelector;

class StatisticHandler extends Singleton {

	use BookSelector;

	protected $slug = 'book_statistics';

	/**
	 * @return void
	 */
	protected function init() {
		// Capabilities
		add_filter( 'map_meta_cap', [ $this, 'meta_cap' ], 10, 4 );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		// Add support for book selector.
		add_action( 'admin_print_scripts', [ $this, 'book_selector_helper' ], 1 );
		add_action( 'admin_print_footer_scripts', [ $this, 'book_selector_helper' ], 1 );
		add_action( 'wp_print_scripts', [ $this, 'book_selector_helper' ], 1 );
		add_action( 'wp_print_footer_scripts', [ $this, 'book_selector_helper' ], 1 );
	}

	/**
	 *
	 *
	 * @return string[]
	 */
	public function meta_cap( $caps, $cap, $user_id, $args = [] ) {
		switch ( $cap ) {
			case 'see_stats':
				// TODO: Authors also can see stats.
				return [ 'edit_others_posts' ];
			case 'see_stats_of':
				list( $post_id ) = $args;
				$post = get_post( $post_id );
				if ( ! $post ) {
					return [ 'do_not_allow' ];
				}
				// TODO: Authors also can see stats.
				return [ 'edit_others_posts' ];
			default:
				return $caps;
		}
	}

	/**
	 * Add menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		$title = __( '出版統計', 'hanmoto' );
		add_menu_page( $title, $title, 'see_stats', $this->slug, [ $this, 'render_statistic' ], 'dashicons-chart-line', 82 );
		// 在庫変動
		$title = __( '在庫変動', 'hanmoto' );
		add_submenu_page( $this->slug, $title, $title, 'see_stats', 'inventory_stats', [ $this, 'render_inventory' ] );
	}

	/**
	 * Statistic screen.
	 *
	 * @return void
	 */
	public function render_statistic() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '出版統計ダッシュボード', 'hanmoto' ); ?></h1>
		</div>
		<?php
	}

	/**
	 * Render inventory stats.
	 *
	 * @return void
	 */
	public function render_inventory() {
		wp_enqueue_script( 'hanmoto-screen-stats-inventory' );
		wp_enqueue_style( 'wp-components' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '在庫変動', 'hanmoto' ); ?></h1>

			<div id="hanmoto-screen-stats-inventory">

			</div>
		</div>
		<?php
	}

	/**
	 * Helper for book selector.
	 *
	 * @return void
	 */
	public function book_selector_helper() {
		static $done = false;
		if ( $done || ! wp_script_is( 'hanmoto-book-selector' ) ) {
			return;
		}
		// TODO: Filter book list if user is an author.
		// TODO: Book has ISBN or not?
		$books = get_posts( [
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => - 1,
		] );
		wp_localize_script( 'hanmoto-book-selector', 'HanmotoBookSelector', [
			'books' => array_map( function( $book ) {
				return [
					'id'    => $book->ID,
					'title' => get_the_title( $book ),
					'url'   => get_permalink( $book ),
				];
			}, $books ),
		] );
		$done = true;
	}
}
