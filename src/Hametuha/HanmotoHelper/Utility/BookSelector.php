<?php

namespace Hametuha\HanmotoHelper\Utility;

use Hametuha\HanmotoHelper\Controller\PostType;
use Hametuha\HanmotoHelper\Models\ModelOrder;

/**
 * Book Selector.
 *
 * @package hanmoto
 */
trait BookSelector {

	/**
	 * Render book select pulldown.
	 *
	 * @param int    $current_value Current value of posts.
	 * @param string $name          Select name field.
	 * @param string $id            ID attributes.
	 *
	 * @return void
	 */
	public function book_select_pull_down( $current_value, $name = 'post_parent', $id = '' ) {
		$products    = get_posts( [
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => - 1,
		] );
		$product_ids = [
			0 => __( '指定しない', 'habmoto' ),
		];
		foreach ( $products as $product ) {
			$product_ids[ $product->ID ] = get_the_title( $product );
		}
		?>
		<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
			<?php
			foreach ( $product_ids as $id => $label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $id ),
					selected( $id, $current_value, false ),
					esc_html( $label )
				);
			}
			?>
		</select>
		<?php
	}

	/**
	 * Hierarchical callback.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $label    Taxonomy label.
	 * @return callable
	 */
	public function hierarchical_radio( $taxonomy, $label ) {
		return function ( $post ) use ( $taxonomy, $label ) {
			wp_enqueue_script( 'hanmoto-taxonomy-selector' );
			// tax_input[source][]
			$terms = get_terms( [
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'parent'     => 0,
			] );
			if ( ! $terms || is_wp_error( $terms ) ) {
				printf(
					'<div class="notice-error"><p>%s</p></div>',
					// translators: %s is product name.
					sprintf( esc_html__( '%sが登録されていません。', 'hanmoto' ), esc_html( $label ) )
				);
				return;
			}
			?>
			<select class="hanmoto-select2" name="tax_input[<?php echo esc_attr( $taxonomy ); ?>][]">
				<?php foreach ( $terms as $term ) : ?>
				<optgroup label="<?php echo esc_attr( $term->name ); ?>">
					<?php
					$children = get_terms( [
						'taxonomy'   => $term->taxonomy,
						'hide_empty' => false,
						'parent'     => $term->term_id,
					] );
					if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
						foreach ( $children as $child ) {
							printf(
								'<option value="%d" %s>%s</option>',
								esc_attr( $child->term_id ),
								selected( has_term( $child, $child->taxonomy, $post ), true, false ),
								esc_html( $child->name )
							);
						}
					}
					?>
				</optgroup>
				<?php endforeach; ?>
			</select>
			<?php
		};
	}

	/**
	 * Customize columns.
	 *
	 * @return void
	 */
	public function admin_columns( $post_type ) {
		// add styles.
		add_action( 'admin_head', function() use ( $post_type ) {
			$screen = get_current_screen();
			if ( 'edit-' . $post_type !== $screen->id ) {
				return;
			}
			?>
			<style>
				.wp-list-table th.column-inventory,
				.wp-list-table th.column-taxonomy-transaction_type,
				.wp-list-table th.column-sub_total {
					width: 80px;
				}
				.wp-list-table th.column-taxonomy-supplier {
					width: 120px;
				}
				.wp-list-table th.column-capture_at {
					width: 14%;
				}
			</style>
			<?php
		} );
		// Add columns.
		add_filter( 'manage_' . $post_type . '_posts_columns', function( $columns ) use ( $post_type ) {
			wp_enqueue_script( 'hanmoto-inventory-bulk-action' );
			$new_columns = [];
			foreach ( $columns as $key => $label ) {
				switch ( $key ) {
					case 'title':
						$new_columns['inventory_group'] = __( '取引', 'hanmoto' );
						$new_columns['item_title']      = __( '商品', 'hanmoto' );
						break;
					case 'author':
						switch ( $post_type ) {
							case ModelOrder::post_type():
								$new_columns['inventory'] = __( '冊数', 'hanmoto' );
								break 2;
							default:
								$new_columns['inventory'] = __( '在庫変動', 'hanmoto' );
								$new_columns['sub_total'] = __( '総額', 'hanmoto' );
								break 2;
						}
					case 'date':
						$new_columns[ $key ] = __( '受注日', 'hanmoto' );
						switch ( $post_type ) {
							case ModelOrder::post_type():
								$new_columns['shipped_at'] = __( '搬送日', 'hanmoto' );
								break 2;
							default:
								$new_columns['capture_at'] = __( '請求日', 'hanmoto' );
								break 2;
						}
					default:
						$new_columns[ $key ] = $label;
						break;
				}
			}
			return $new_columns;
		} );
		// Render columns.
		add_action( 'manage_' . $post_type . '_posts_custom_column', function( $column, $post_id ) use ( $post_type ) {
			switch ( $column ) {
				case 'inventory_group':
					$group = get_post_meta( $post_id, '_group', true );
					if ( $group ) {
						printf( '<a href="%s">%s</a>', get_edit_post_link( $group ), get_the_title( $group ) );
					} else {
						printf( '<span style="color: lightgray;">%s</span>', esc_html__( '未設定', 'hanmoto' ) );
					}
					break;
				case 'item_title':
					$parent = wp_get_post_parent_id( $post_id );
					printf(
						'<a href="%s">%s</span>',
						esc_url( admin_url( sprintf( 'edit.php?post_type=%s&inventory_parent=%d', $post_type, $parent ) ) ),
						esc_html( $parent ? get_the_title( $parent ) : __( '登録のない商品', 'hanmoto' ) )
					);
					break;
				case 'inventory':
					$move = get_post_meta( $post_id, '_amount', true );
					printf( '<span style="display:block; text-align: right;">%s</span>', number_format( $move ) );
					break;
				case 'sub_total':
					$sub_total = $this->get_total( $post_id );
					printf( '<span style="display:block; text-align: right;">%s</span>', number_format( $sub_total ) );
					break;
				case 'capture_at':
				case 'shipped_at':
					$capture_at = get_post_meta( $post_id, '_' . $column, true );
					echo $capture_at ? mysql2date( __( 'Y年m月d日', 'hanmoto' ), $capture_at ) : '<span style="color:lightgrey">---</spans>';
					break;
			}
		}, 10, 2 );
	}

	/**
	 * Get total cost for post.
	 *
	 * @param null|int|\WP_Post $post Post object.
	 *
	 * @return float
	 */
	public function get_total( $post = null ) {
		$post       = get_post( $post );
		$unit_price = (float) get_post_meta( $post->ID, '_unit_price', true );
		$margin     = (float) get_post_meta( $post->ID, '_margin', true );
		$amount     = (int) get_post_meta( $post->ID, '_amount', true );
		$sub_total  = $unit_price * ( $margin / 100 ) * $amount * -1;
		return $sub_total;
	}

	/**
	 * Get tax value.
	 *
	 * @param null|int|\WP_Post $post Post object.
	 *
	 * @return float
	 */
	public function get_tax_total( $post = null ) {
		$post  = get_post( $post );
		$price = $this->get_total( $post );
		$tax   = get_post_meta( $post->ID, '_vat', true );
		if ( ! is_numeric( $tax ) ) {
			$tax = 10;
		}
		return $price * ( 1 + $tax / 100 );
	}

	/**
	 * Display price in colored format.
	 *
	 * @param float  $price Total price
	 * @param string $tag   Tag
	 *
	 * @return string
	 */
	public function color_price( $price, $tag = 'span' ) {
		$color = 'grey';
		if ( $price > 0 ) {
			$color = 'green';
		} elseif ( $price < 0 ) {
			$color = 'red';
		}
		return sprintf(
			'<%1$s style="color: %2$s">%3$s</%1$s>',
			$tag,
			$color,
			esc_html( number_format( wc_format_localized_price( $price ) ) )
		);
	}

	/**
	 * Get a bookshop or create one if not exists.
	 *
	 * @param string $name       Bookshop name.
	 * @param string $wholesaler Wholesaler name.
	 * @param string $line_code  Line code.
	 * @param string $shop_code  Shop code.
	 * @param bool   $create     If false, not create.
	 *
	 * @return \WP_Term|null|\WP_Error
	 */
	public function get_bookshop( $name, $wholesaler, $line_code, $shop_code, $create = false ) {
		$wholesaler   = trim( $wholesaler );
		$name         = trim( $name );
		$line_code    = str_replace( '-', '', trim( $line_code ) );
		$shop_code    = trim( $shop_code );
		$meta_queries = [
			[
				'key'   => 'wholesaler',
				'value' => $wholesaler,
			],
			[
				'key'   => 'line_code',
				'value' => $line_code,
			],
			[
				'key'   => 'shop_code',
				'value' => $shop_code,
			],
		];
		$term_query   = new \WP_Term_Query( [
			'taxonomy'   => 'supplier',
			'number'     => 1,
			'hide_empty' => false,
			'meta_query' => $meta_queries,
		] );
		$terms        = $term_query->get_terms();
		if ( $terms ) {
			return $terms[0];
		}
		if ( ! $create ) {
			return null;
		}
		// Create new term.
		$parent = get_term_by( 'slug', 'bookshop', 'supplier' );
		if ( is_wp_error( $parent ) ) {
			return $parent;
		} elseif ( ! $parent ) {
			return new \WP_Error( 'bookshop_error', __( 'タクソノミー「書店」が登録されていません。', 'hanmoto' ) );
		}
		$bookshop = wp_insert_term( $name, 'supplier', [
			'parent' => $parent->term_id,
			'name'   => $name,
		] );
		if ( is_wp_error( $bookshop ) ) {
			return $bookshop;
		}
		$term_id = $bookshop['term_id'];
		foreach ( $meta_queries as $meta ) {
			update_term_meta( $term_id, $meta['key'], $meta['value'] );
		}
		return get_term( $term_id, 'supplier' );
	}

	/**
	 * Get book by ISBN.
	 *
	 * @param string $isbn ISBN of product.
	 * @return \WP_Post|null
	 */
	public function get_book_by_isbn( $isbn, $post_type = 'product' ) {
		$isbn          = preg_replace( '/[^a-zA-Z0-9]/u', '', trim( $isbn ) );
		$product_query = new \WP_Query( [
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'   => PostType::META_KEY_ISBN,
					'value' => $isbn,
				],
			],
		] );
		if ( ! $product_query->have_posts() ) {
			return null;
		}
		return $product_query->posts[0];
	}

	/**
	 * Ensure that the date format is Y-m-d.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	public function ensure_date( $date ) {
		list( $year, $month, $day ) = preg_split( '@(/|-)@u', $date );
		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}
}
