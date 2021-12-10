<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\OpenDbApi;
use Hametuha\HanmotoHelper\Utility\SettingsAccessor;

/**
 * Post type controller.
 *
 * @package hanmoto
 */
class PostType extends Singleton {

	use SettingsAccessor,
		OpenDbApi;

	const META_KEY_ISBN = 'hanmoto_isbn';

	const META_KEY_SYNCED = 'hanmoto_last_synced';

	const META_KEY_DATA = 'hanmoto_data';

	/**
	 * Constructor.
	 */
	protected function init() {
		if ( $this->option()->create_post_type ) {
			add_action( 'init', [ $this, 'create_post_type' ] );
		}
		add_action( 'init', [ $this, 'register_meta_fields' ], 100 );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
	}

	/**
	 * Register post type.
	 */
	public function create_post_type() {
		$post_type_args = apply_filters( 'hanmoto_post_type_args', [
			'label'  => __( 'Books', 'hanmoto' ),
			'labels' => [
				'singular_name' => __( 'Book', 'hanmoto' ),
			],
			'public'  => false,
			'show_ui' => true,
			'show_in_rest' => true,
			'menu_icon'  => 'dashicons-book',
			'supports' =>  [ 'title', 'editor', 'author', 'custom-fields' ],
		] );
		register_post_type( 'books', $post_type_args );
	}

	/**
	 * Get isbn code.
	 *
	 * @param null|int|\WP_Post $post
	 * @return string
	 */
	public function get_isbn( $post = null ) {
		$post = get_post( $post );
		return get_post_meta( $post->ID, self::META_KEY_ISBN, true );
	}

	/**
	 * Get last synced.
	 *
	 * @param null|int|\WP_Post $post
	 * @return mixed
	 */
	public function get_last_synced( $post = null ) {
		$post = get_post( $post );
		return get_post_meta( $post->ID, self::META_KEY_SYNCED, true );
	}

	/**
	 * Sync isbn code.
	 *
	 * @return int[]|\WP_Error Synced count or WP_Error on failure.
	 */
	public function sync() {
		$post_type = $this->option()->post_type;
		if ( ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'hanmoto_sync_error', __( 'No post type found.', 'hanmoto' ) );
		}
		$isbn = $this->option()->get_publisher_ids();
		if ( empty( $isbn ) ) {
			return new \WP_Error( 'hanmoto_sync_error', __( 'Publisher ID not set.', 'hanmoto' ) );
		}
		$filtered = $this->openbd_filter( $isbn );
		if ( empty( $filtered ) ) {
			return new \WP_Error( 'hanmoto_sync_error', __( 'No book found.', 'hanmoto' ) );
		}
		$books = $this->openbd_get( $filtered );
		if ( is_wp_error( $books ) ) {
			return $books;
		}
		$updated = 0;
		$created = 0;
		$failed  = 0;
		$isbns = array_map( function( $book ) {
			return $book['summary']['isbn'];
		}, $books );
		$exists = [];
		// Get existing posts.
		$per_page = 100;
		$paged    = 1;
		while ( true ) {
			$query = new \WP_Query( [
				'post_type' => $this->option()->post_type,
				'posts_page_page' => $per_page,
				'paged'           => $paged,
				'meta_query'      => [
					[
						'key'      => self::META_KEY_ISBN,
						'value'    => $isbn,
						'operator' => 'IN',
					],
				],
			] );
			if ( ! $query->have_posts() ) {
				break;
			}
			foreach ( $query->posts as $post ) {
				$exists[ $this->get_isbn( $post ) ] = $post->ID;
			}
			$paged++;
		}
		foreach ( $books as $book ) {
			$isbn  = $book['summary']['isbn'];
			$title = $book['summary']['title'];
			$args = [
				'post_type'  => $this->option()->post_type,
				'post_name'  => $isbn,
				'post_title' => $title,
				'post_date'  => $this->convert_date_time( $book['summary']['pubdate'] ),
			];
			$on_update = false;
			if ( isset( $exists[ $isbn ] ) ) {
				// Existing.
				$args['ID'] = $exists[ $isbn ];
				$on_update = true;
			} else {
				// Newly create.
				$args['post_status'] = 'publish';
			}
			// Save.
			$result = wp_insert_post( $args );
			// Count.
			if ( ! $result ) {
				$failed++;
			} elseif ( $on_update ) {
				$updated++;
			} else {
				$created++;
			}
			// Save post meta.
			if ( $result ) {
				update_post_meta( $result, self::META_KEY_ISBN, $isbn );
				update_post_meta( $result, self::META_KEY_SYNCED, current_time( 'mysql' ) );
				update_post_meta( $result, self::META_KEY_DATA, $book );
			}
		}
		return [ $created, $updated, $failed ];
	}

	/**
	 * Convert hanmoto.com style date to mysql date.
	 *
	 * @param string $pub_date Date format.
	 *
	 * @return string
	 */
	public function convert_date_time( $pub_date ) {
		return preg_replace( '/(\d{4})(\d{2})(\d{2})/u', '$1-$2-$3 00:00:00', $pub_date );
	}

	/**
	 * Register meta fields.
	 */
	public function register_meta_fields() {
		$post_type = $this->option()->post_type;
		if ( ! post_type_exists( $post_type ) ) {
			return;
		}
		foreach ( [
			self::META_KEY_ISBN,
			self::META_KEY_SYNCED,
		] as $key ) {
			register_post_meta( $post_type, $key, [
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			] );
		}
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script( 'hanmoto-editor-helper' );
		wp_enqueue_style( 'habmoto-card' );
		wp_localize_script( 'hanmoto-editor-helper', 'HanmotoEditorHelper', [
			'postType' => $this->option()->post_type,
		] );
	}
}
