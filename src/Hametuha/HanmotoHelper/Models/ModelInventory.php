<?php

namespace Hametuha\HanmotoHelper\Models;


use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\BookSelector;
use Hametuha\HanmotoHelper\Utility\Validator;

/**
 * Create Inventory model.
 */
class ModelInventory extends Singleton {

	use BookSelector,
		Validator;

	/**
	 * {@inheritdoc }
	 */
	protected function init() {
		// Register post types.
		add_action( 'init', [ $this, 'register_post_types' ] );
		// Register meta boxes.
		add_action( 'save_post_inventory', [ $this, 'save_inventory_boxes' ], 10, 2 );
		add_action( 'edit_form_after_title', [ $this, 'editor_title' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_inventory_boxes' ] );
		// Add action.
		add_filter( 'query_vars', function( $vars ) {
			$vars[] = 'inventory_parent';
			return $vars;
		} );
		add_action( 'pre_get_posts', function( $wp_query ) {
			$parent = $wp_query->get( 'inventory_parent' );
			if ( $parent ) {
				$wp_query->set( 'post_parent', $parent );
			}
		} );
		// Customize admin columns.
		$this->admin_columns( 'inventory' );
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
			'supports'          => [ 'author', 'excerpt' ],
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
	 * Display title field.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function editor_title( $post ) {
		if ( 'inventory' !== $post->post_type ) {
			return;
		}
		printf(
			'<h1>%s <code style="font-size: inherit">#%d</code></h1>',
			esc_html__( '在庫変動', 'hanmoto' ),
			esc_html( $post->ID )
		);
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
				<span style="display: inline-block; margin-left: 10px;">
				<?php
				$dates = [
					0  => __( '当月末', 'hanmoto' ),
					1  => __( '翌月末', 'hanmoto' ),
					6  => __( '6ヶ月後月末', 'hanmoto' ),
					12 => __( '1年後月末', 'hanmoto' ),
				];
				$now = $post->post_date;
				foreach ( $dates as $month => $label) :
					try {
						$date = new \DateTime( $now );
					} catch ( \Exception $e ) {
						continue 1;
					}
					if ( $month ) {
						$date->modify( sprintf( '+%d month', $month ) );
					}
					$date = $date->format( 'Y-m-t' );
					?>
					<span>
						<?php echo esc_html( $label ); ?>
						<code><?php echo esc_html( $date ); ?></code>
					</span>
				<?php endforeach; ?>
				</span>
			</p>
			<p>
				<label style="display: block">
					<?php esc_html_e( '商品', 'hanmoto' ); ?>
					<br />
					<?php $this->book_select_pull_down( $post->post_parent ); ?>
				</label>
			</p>
			<?php
		}, $post_type, 'normal', 'high' );
	}

	/**
	 * Customize supplier.
	 *
	 * @return void
	 */
	public function supplier_detail() {

	}

	/**
	 * Get stock on specified date.
	 *
	 * @param string $date Get stock.
	 * @return int
	 */
	public function get_stock( $post_id, $date ) {
		global $wpdb;
		$query = <<<SQL
			SELECT SUM( CAST( pm.meta_value AS SIGNED ) )
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS pm
			ON p.ID = pm.post_id AND pm.meta_key = '_amount'
			WHERE p.post_type = 'inventory'
			  AND p.post_status IN ( 'publish', 'future' )
			  AND p.post_parent = %d
			  AND DATE(p.post_date) < %s
SQL;
		return (int) $wpdb->get_var( $wpdb->prepare( $query, $post_id, $date ) );
	}

	/**
	 * Get inventory change event.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $start   Default 30 days ago.
	 * @param string $end     Default today.
	 *
	 * @return array|\WP_Error
	 */
	public function get_inventory_changes( $post_id = 0, $start = '', $end = '' ) {
		$args = [
			'post_type'      => 'inventory',
			'post_status'    => [ 'publish', 'future' ],
			'posts_per_page' => -1,
			'orderby'        => [ 'date' => 'ASC' ],
			'meta_query'     => [
				[
					'key' => '_amount',
					'compare'  => 'EXISTS',
				],
			],
		];
		if ( $post_id ) {
			$args['post_parent'] = $post_id;
		}
		$date_query = [];
		if ( $this->is_date( $start ) ) {
			list( $start_year, $start_month, $start_day ) = array_map( 'intval', explode( '-', $start ) );
			$date_query['after'] = [
				'year'  => $start_year,
				'month' => $start_month,
				'day'   => $start_day,
			];
		}
		if ( $this->is_date( $end ) ) {
			list( $end_year, $end_month, $end_day )       = array_map( 'intval', explode( '-', $end ) );
			$date_query['before'] = [
				'year'  => $end_year,
				'month' => $end_month,
				'day'   => $end_day,
			];
		}
		if ( ! empty( $date_query ) ) {
			$date_query['inclusive'] = true;
			$args['date_query'] = [ $date_query ];
		}
		$query = new \WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return [];
		}
		$changes = [];
		foreach ( $query->posts as $post ) {
			$transaction = __( '不明', 'hanmoto' );
			$supplier    = __( '不明', 'hanmoto' );
			$terms = get_the_terms( $post, 'transaction_type' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$transaction = $terms[0]->name;
			}
			$suppliers = get_the_terms( $post, 'supplier' );
			if ( $suppliers && ! is_wp_error( $suppliers ) ) {
				$supplier = $suppliers[0]->name;
			}
			$changes[] = [
				'id'       => $post->ID,
				'title'    => get_the_title( $post->post_parent ),
				'isbn'     => get_post_meta( $post->post_parent, 'hanmoto_isbn', true ) ?: sprintf( 'H%012d', $post->post_parent ),
				'amount'   => (int) get_post_meta( $post->ID, '_amount', true ),
				'type'     => $transaction,
				'supplier' => $supplier,
				'date'     => mysql2date( 'Y-m-d', $post->post_date ),
				'datetime' => $post->post_date,
			];
		}
		return $changes;
	}
}
