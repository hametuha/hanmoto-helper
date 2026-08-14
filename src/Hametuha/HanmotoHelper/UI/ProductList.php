<?php

namespace Hametuha\HanmotoHelper\UI;


use Hametuha\HanmotoHelper\Controller\PostType;
use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\SettingsAccessor;

/**
 * Show ISBN in the product list table and filter by it.
 *
 * @package hanmoto
 */
class ProductList extends Singleton {

	use SettingsAccessor;

	/**
	 * Query var of ISBN filter.
	 */
	const QUERY_VAR = 'hanmoto_isbn_status';

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ $this, 'render_isbn_filter' ] );
		// WooCommerce converts the search term to post__in, so run after it.
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ], 20 );
		add_action( 'admin_head-edit.php', [ $this, 'admin_head' ] );
	}

	/**
	 * Post types which have ISBN.
	 *
	 * @return string[]
	 */
	protected function post_types() {
		$post_types = [ 'product' ];
		$configured = $this->option()->post_type;
		if ( $configured && post_type_exists( $configured ) && ! in_array( $configured, $post_types, true ) ) {
			$post_types[] = $configured;
		}
		return $post_types;
	}

	/**
	 * Is the post type a target of this UI?
	 *
	 * @param string $post_type Post type to check.
	 * @return bool
	 */
	protected function is_target( $post_type ) {
		return in_array( (string) $post_type, $this->post_types(), true );
	}

	/**
	 * Display ISBN next to the product title.
	 *
	 * @param string[] $post_states Post states.
	 * @param \WP_Post $post        Post object.
	 * @return string[]
	 */
	public function display_post_states( $post_states, $post ) {
		if ( ! $this->is_target( $post->post_type ) ) {
			return $post_states;
		}
		$isbn = get_post_meta( $post->ID, PostType::META_KEY_ISBN, true );
		if ( ! $isbn ) {
			return $post_states;
		}
		$post_states[ PostType::META_KEY_ISBN ] = sprintf( '<code class="hanmoto-isbn-state">%s</code>', esc_html( $isbn ) );
		return $post_states;
	}

	/**
	 * Render the dropdown to filter by ISBN.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function render_isbn_filter( $post_type ) {
		if ( ! $this->is_target( $post_type ) ) {
			return;
		}
		$current = filter_input( INPUT_GET, self::QUERY_VAR );
		?>
		<select name="<?php echo esc_attr( self::QUERY_VAR ); ?>" aria-label="<?php esc_attr_e( 'ISBNの有無で絞り込み', 'hanmoto' ); ?>">
			<option value=""><?php esc_html_e( 'ISBN：すべて', 'hanmoto' ); ?></option>
			<option value="has" <?php selected( $current, 'has' ); ?>><?php esc_html_e( 'ISBNあり（書籍）', 'hanmoto' ); ?></option>
			<option value="none" <?php selected( $current, 'none' ); ?>><?php esc_html_e( 'ISBNなし', 'hanmoto' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Filter the list table query.
	 *
	 * @param \WP_Query $query Query object.
	 * @return void
	 */
	public function pre_get_posts( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $this->is_target( $query->get( 'post_type' ) ) ) {
			return;
		}
		$this->filter_by_isbn_status( $query );
		$this->search_by_isbn( $query );
	}

	/**
	 * Narrow down the products by existence of ISBN.
	 *
	 * @param \WP_Query $query Query object.
	 * @return void
	 */
	protected function filter_by_isbn_status( $query ) {
		$status = filter_input( INPUT_GET, self::QUERY_VAR );
		if ( ! in_array( $status, [ 'has', 'none' ], true ) ) {
			return;
		}
		$meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = [];
		}
		if ( 'has' === $status ) {
			// The meta row may exist but be empty, so check the value too.
			$meta_query[] = [
				'key'     => PostType::META_KEY_ISBN,
				'value'   => '',
				'compare' => '!=',
			];
		} else {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => PostType::META_KEY_ISBN,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => PostType::META_KEY_ISBN,
					'value'   => '',
					'compare' => '=',
				],
			];
		}
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Let the search box find products by ISBN.
	 *
	 * WooCommerce has already replaced the search term with post__in,
	 * so the products found by ISBN are merged into it.
	 *
	 * @param \WP_Query $query Query object.
	 * @return void
	 */
	protected function search_by_isbn( $query ) {
		$post_in = $query->get( 'post__in' );
		if ( ! $post_in || ! is_array( $post_in ) ) {
			// The search term is not converted, so widening it would break the keyword search.
			return;
		}
		$found = $this->get_ids_by_isbn( (string) filter_input( INPUT_GET, 's' ) );
		if ( ! $found ) {
			return;
		}
		$query->set( 'post__in', array_values( array_unique( array_merge( $post_in, $found ) ) ) );
	}

	/**
	 * Get post IDs which have the ISBN.
	 *
	 * @param string $term Searched term.
	 * @return int[]
	 */
	protected function get_ids_by_isbn( $term ) {
		// ISBN may be stored with hyphens, so compare the digits only.
		$digits = preg_replace( '/[^0-9]/u', '', $term );
		if ( 4 > strlen( $digits ) ) {
			return [];
		}
		global $wpdb;
		$query = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND REPLACE( meta_value, '-', '' ) LIKE %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_col( $wpdb->prepare( $query, PostType::META_KEY_ISBN, '%' . $wpdb->esc_like( $digits ) . '%' ) );
		return array_map( 'intval', $found );
	}

	/**
	 * Style the ISBN in the list table.
	 *
	 * @return void
	 */
	public function admin_head() {
		if ( ! $this->is_target( filter_input( INPUT_GET, 'post_type' ) ) ) {
			return;
		}
		?>
		<style>
			.wp-list-table code.hanmoto-isbn-state {
				padding: 1px 5px;
				font-size: 11px;
			}
		</style>
		<?php
	}
}
