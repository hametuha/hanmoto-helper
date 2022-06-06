<?php

namespace Hametuha\HanmotoHelper\Models;


use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * Create Inventory model.
 */
class ModelInventory extends Singleton {

	/**
	 * {@inheritdoc }
	 */
	protected function init() {
		// Register post types.
		add_action( 'init', [ $this, 'register_post_types' ] );
		// Register meta boxes.
		add_action( 'save_post_inventory', [ $this, 'save_inventory_boxes' ], 10, 2 );
		add_action( 'add_meta_boxes', [ $this, 'add_inventory_boxes' ] );
		// Customize admin columns.
		$this->admin_columns();
		// Customize order.
		$this->supplier_detail();
	}


	/**
	 * Register post type
	 *
	 * @return void
	 */
	public function register_post_types() {
		// Inventory
		register_post_type( 'inventory', [
			'label'             => __( '在庫変動', 'hanmoto' ),
			'has_archive'       => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_nav_menu'  => false,
			'show_in_admin_bar' => false,
			'menu_icon'         => 'dashicons-database',
			'menu_position'     => 80,
			'supports'          => [ 'title', 'author', 'excerpt' ],
		] );

		// Transaction type.
		register_taxonomy( 'transaction_type', [ 'inventory' ], [
			'public'            => false,
			'show_ui'           => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'label'             => __( '取引種別', 'hanmoto' ),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'meta_box_cb'       => function ( $post ) {
				// tax_input[transaction_type][]
				$terms = get_terms( [
					'taxonomy'   => 'transaction_type',
					'hide_empty' => false,
				] );
				if ( ! $terms || is_wp_error( $terms ) ) {
					printf(
						'<div class="notice-error"><p>%s</p></div>',
						esc_html__( '取引種別が登録されていません。', 'hanmoto' )
					);

					return;
				}
				foreach ( $terms as $term ) {
					printf(
						'<div><label><input type="radio" name="tax_input[%s][]" value="%d" %s/> %s</label></div>',
						esc_attr( $term->taxonomy ),
						esc_attr( $term->term_id ),
						checked( has_term( $term, $term->taxonomy, $post ), true, false ),
						esc_html( $term->name )
					);
				}
			},
		] );

		// Suppliers.
		register_taxonomy( 'supplier', [ 'inventory' ], [
			'public'            => false,
			'show_ui'           => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'label'             => __( '取引先', 'hanmoto' ),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'meta_box_cb'       => function ( $post ) {
				// tax_input[supplier][]
				$terms = get_terms( [
					'taxonomy'   => 'supplier',
					'hide_empty' => false,
				] );
				if ( ! $terms || is_wp_error( $terms ) ) {
					printf(
						'<div class="notice-error"><p>%s</p></div>',
						esc_html__( '取引先が登録されていません。', 'hanmoto' )
					);

					return;
				}
				foreach ( $terms as $term ) {
					printf(
						'<div><label><input type="radio" name="tax_input[%s][]" value="%d" %s/> %s</label></div>',
						esc_attr( $term->taxonomy ),
						esc_attr( $term->term_id ),
						checked( has_term( $term, $term->taxonomy, $post ), true, false ),
						esc_html( $term->name )
					);
				}
			},
		] );

		// Orders.

	}

	/**
	 * Save post data.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 *
	 * @return void
	 */
	public function save_inventory_boxes( $post_id, $post ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hanmotoinventorynonce' ), 'update_inventory' ) ) {
			return;
		}
		// Save all meta.
		foreach ( [
			'unit_price',
			'amount',
			'margin',
			'vat',
			'capture_at',
		] as $key ) {
			update_post_meta( $post_id, '_' . $key, filter_input( INPUT_POST, $key ) );
		}
	}

	/**
	 * Register meta box for inventory.
	 *
	 * @param $post_type
	 *
	 * @return void
	 */
	public function add_inventory_boxes( $post_type ) {
		if ( 'inventory' !== $post_type ) {
			return;
		}
		add_meta_box( 'inventory-meta', __( '在庫増減詳細', 'hanmoto' ), function ( \WP_Post $post ) {
			wp_nonce_field( 'update_inventory', '_hanmotoinventorynonce', false );
			?>
			<table>
				<thead>
				<tr>
					<th>
						<label for="habmoto-unit_price">
							<?php esc_html_e( '単価', 'hanmoto' ); ?>
						</label>
					</th>
					<th>
						<label for="habmoto-amount">
							<?php esc_html_e( '総数', 'hanmoto' ); ?>
						</label>
					</th>
					<th>
						<label for="habmoto-margin">
							<?php esc_html_e( '料率', 'hanmoto' ); ?><br />
						</label>
					</th>
					<th>
						<label for="habmoto-vat">
							<?php esc_html_e( '消費税', 'hanmoto' ); ?><br />
						</label>
					</th>
				</tr>
				</thead>
				<tfoot>
					<tr>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td style="text-align: right">
							<?php
							esc_html_e( '合計:', 'hanmoto' );
							echo $this->color_price( $this->get_total( $post ), 'strong' );
							?>
						</td>
						<td style="text-align: right">
							<?php
							esc_html_e( '税込合計:', 'hanmoto' );
							echo $this->color_price( $this->get_tax_total( $post ), 'strong' );
							?>
						</td>
					</tr>
				</tfoot>
				<tbody>
				<tr>
					<td>
						<input type="number" id="habmoto-unit_price" name="unit_price"
							value="<?php echo esc_attr( get_post_meta( $post->ID, '_unit_price', true ) ); ?>" />
					</td>
					<td>
						<input type="number" id="habmoto-amount" name="amount"
							value="<?php echo esc_attr( get_post_meta( $post->ID, '_amount', true ) ); ?>" />
					</td>
					<td>

						<input type="number" id="habmoto-margin" name="margin"
							value="<?php echo esc_attr( get_post_meta( $post->ID, '_margin', true ) ); ?>" />
					</td>
					<td>
						<input type="number" id="habmoto-vat" name="vat"
							value="<?php echo esc_attr( get_post_meta( $post->ID, '_vat', true ) ); ?>"
							placeholder="<?php esc_attr_e( '10%', 'hanmoto' ); ?>" />
					</td>
				</tr>
				</tbody>
			</table>
			<p>
				<label>
					<?php esc_html_e( '請求日', 'hanmoto' ); ?><br />
					<input type="date" name="capture_at" value="<?php echo esc_attr( get_post_meta( $post->ID, '_capture_at', true ) ); ?>" />
				</label>
			</p>
			<p>
				<label style="display: block">
					<?php esc_html_e( '商品', 'hanmoto' ); ?><br />
					<?php
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
					<select name="post_parent">
						<?php
						foreach ( $product_ids as $id => $label ) {
							printf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $id ),
								selected( $id, $post->post_parent, false ),
								esc_html( $label )
							);
						}
						?>
					</select>
				</label>
			</p>
			<?php
		}, $post_type );
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
	 * Customize columns.
	 *
	 * @return void
	 */
	public function admin_columns() {
		add_filter( 'manage_inventory_posts_columns', function( $columns ) {
			$new_columns = [];
			foreach ( $columns as $key => $label ) {
				switch ( $key ) {
					case 'author':
						$new_columns['inventory'] = __( '在庫変動', 'hanmoto' );
						$new_columns['sub_total'] = __( '総額', 'hanmoto' );
						break;
					case 'date':
						$new_columns[ $key ]       = $label;
						$new_columns['capture_at'] = __( '請求日', 'hanmoto' );
						break;
					default:
						$new_columns[ $key ] = $label;
						break;
				}
			}
			return $new_columns;
		} );
		// Add parent post.
		add_filter( 'display_post_states', function( $states, \WP_Post $post ) {
			if ( ( 'inventory' === $post->post_type ) && $post->post_parent ) {
				$states[] = get_the_title( $post->post_parent );
			}
			return $states;
		}, 10, 2 );
		// Render columns.
		add_action( 'manage_inventory_posts_custom_column', function( $column, $post_id ) {
			switch ( $column ) {
				case 'inventory':
					$move = get_post_meta( $post_id, '_amount', true );
					printf( '<span style="display:block; text-align: right;">%s</span>', number_format( $move ) );
					break;
				case 'sub_total':
					$sub_total = $this->get_total( $post_id );
					printf( '<span style="display:block; text-align: right;">%s</span>', number_format( $sub_total ) );
					break;
				case 'capture_at':
					$capture_at = get_post_meta( $post_id, '_capture_at', true );
					echo $capture_at ? mysql2date( __( 'Y年m月d日', 'hanmoto' ), $capture_at ) : '<span style="color:lightgrey">---</spans>';
					break;
			}
		}, 10, 2 );
	}

	/**
	 * Customize supplier.
	 *
	 * @return void
	 */
	public function supplier_detail() {
		// Save information.
		add_action( 'edited_term', function( $term_id, $tt_id, $taxonomy ) {
			if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_habmotononce' ), 'update_supplier' ) ) {
				return;
			}
			update_term_meta( $term_id, 'address', filter_input( INPUT_POST, 'address' ) );
		}, 10, 3 );
		// Render fields.
		add_action( 'supplier_edit_form_fields', function( \WP_Term $tag, $taxonomy ) {
			?>
			<tr>
				<th><label for="hanmoto-address"><?php esc_html_e( '住所', 'hanmoto' ); ?></label></th>
				<td>
					<?php wp_nonce_field( 'update_supplier', '_habmotononce', false ); ?>
					<textarea style="box-sizing: border-box" class="widefat" rows="10" id="hanmoto-address" name="address"><?php echo esc_textarea( get_term_meta( $tag->term_id, 'address', true ) ); ?></textarea>
				</td>
			</tr>
			<?php
		}, 10, 2 );
	}
}
