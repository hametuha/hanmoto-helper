<?php

namespace Hametuha\HanmotoHelper\UI;

use Hametuha\HanmotoHelper\Models\ModelOrder;
use Hametuha\HanmotoHelper\Models\ModelOrderFax;
use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * Order list table.
 *
 * どのFAX送付分に入っているかを列に出し、送付状況で絞り込めるようにする。
 * 「送付済み」は送付分が公開されているかどうかで決まる。
 *
 * @package hanmoto
 */
class OrderList extends Singleton {

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		// Filter the order list by the sending status.
		add_action( 'restrict_manage_posts', [ $this, 'render_fax_filter' ] );
		add_action( 'pre_get_posts', [ $this, 'filter_by_fax_status' ] );
		// Show the faxes in the order list.
		add_filter( 'manage_' . ModelOrder::post_type() . '_posts_columns', [ $this, 'add_fax_column' ] );
		add_action( 'manage_' . ModelOrder::post_type() . '_posts_custom_column', [ $this, 'render_fax_column' ], 10, 2 );
		add_filter( 'the_posts', [ $this, 'prime_fax_caches' ], 10, 2 );
		add_action( 'admin_head', [ $this, 'order_list_style' ] );
	}

	/**
	 * Get the model of fax.
	 *
	 * @return ModelOrderFax
	 */
	protected function model() {
		return ModelOrderFax::get_instance();
	}
	/**
	 * Statuses of an order about faxing.
	 *
	 * @return array Slug and label.
	 */
	protected function fax_statuses() {
		return [
			'unsent'    => __( '未送付の注文', 'hanmoto' ),
			'preparing' => __( '準備中の注文（下書きの送付分）', 'hanmoto' ),
			'sent'      => __( '送付済みの注文', 'hanmoto' ),
		];
	}

	/**
	 * Render the filter of sending status.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_fax_filter( $post_type ) {
		if ( ModelOrder::post_type() !== $post_type ) {
			return;
		}
		$current = filter_input( INPUT_GET, 'fax_status' );
		?>
		<select name="fax_status" aria-label="<?php esc_attr_e( 'FAX送付状況で絞り込み', 'hanmoto' ); ?>">
			<option value=""><?php esc_html_e( 'FAX送付状況：すべて', 'hanmoto' ); ?></option>
			<?php foreach ( $this->fax_statuses() as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Build the SQL which tells the order belongs to a fax.
	 *
	 * 参照先の送付分の post_status を見るので meta_query では書けない。
	 *
	 * @param string $alias      Alias to avoid the conflict of sub queries.
	 * @param bool   $sent_only  If true, count only the published(=sent) faxes.
	 * @return string
	 */
	protected function belongs_to_fax_sql( $alias, $sent_only ) {
		global $wpdb;
		$status = $sent_only ? " AND {$alias}f.post_status = 'publish'" : '';
		return "EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} AS {$alias}m
			INNER JOIN {$wpdb->posts} AS {$alias}f
				ON {$alias}f.ID = {$alias}m.meta_value AND {$alias}f.post_type = '" . ModelOrderFax::POST_TYPE . "'
			WHERE {$alias}m.post_id = {$wpdb->posts}.ID
				AND {$alias}m.meta_key = '" . ModelOrderFax::META_FAX . "'{$status}
		)";
	}

	/**
	 * Filter the order list by the sending status.
	 *
	 * @param \WP_Query $query Main query in admin list.
	 * @return void
	 */
	public function filter_by_fax_status( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ModelOrder::post_type() !== $query->get( 'post_type' ) ) {
			return;
		}
		$status = filter_input( INPUT_GET, 'fax_status' );
		if ( ! array_key_exists( (string) $status, $this->fax_statuses() ) ) {
			return;
		}
		// WHERE を直接足す。使い終わったら外して他のクエリに影響させない。
		$callback = function ( $where ) use ( $status, &$callback ) {
			remove_filter( 'posts_where', $callback );
			switch ( $status ) {
				case 'sent':
					$where .= ' AND ' . $this->belongs_to_fax_sql( 'sent', true );
					break;
				case 'preparing':
					$where .= ' AND ' . $this->belongs_to_fax_sql( 'any', false );
					$where .= ' AND NOT ' . $this->belongs_to_fax_sql( 'sent', true );
					break;
				default:
					$where .= ' AND NOT ' . $this->belongs_to_fax_sql( 'any', false );
					break;
			}
			return $where;
		};
		add_filter( 'posts_where', $callback );
	}

	/**
	 * Print the style of the fax column.
	 *
	 * @return void
	 */
	public function order_list_style() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . ModelOrder::post_type() !== $screen->id ) {
			return;
		}
		?>
		<style>
			.wp-list-table th.column-order_fax {
				width: 15%;
			}
			.wp-list-table td.column-order_fax {
				line-height: 1.6;
			}
		</style>
		<?php
	}

	/**
	 * Add the column of fax to the order list.
	 *
	 * @param array $columns Registered columns.
	 * @return array
	 */
	public function add_fax_column( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'taxonomy-source' === $key ) {
				$new['order_fax'] = __( 'FAX送付分', 'hanmoto' );
			}
		}
		if ( ! isset( $new['order_fax'] ) ) {
			// 注文経路の列がない場合でも必ず出す。
			$new['order_fax'] = __( 'FAX送付分', 'hanmoto' );
		}
		return $new;
	}

	/**
	 * Render the column of fax.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_fax_column( $column, $post_id ) {
		if ( 'order_fax' !== $column ) {
			return;
		}
		$faxes = $this->model()->get_faxes_of_order( $post_id );
		if ( empty( $faxes ) ) {
			printf( '<span style="color: #a7aaad;">%s</span>', esc_html__( '未送付', 'hanmoto' ) );
			return;
		}
		$lines = [];
		foreach ( $faxes as $fax ) {
			// 同じ日に作った送付分はタイトルが同じなので、IDを添えて区別できるようにする。
			$line = sprintf(
				'<a href="%1$s">%2$s</a> <small style="color: #a7aaad;">#%3$d</small>',
				esc_url( $fax['edit_url'] ),
				esc_html( $fax['title'] ),
				$fax['id']
			);
			if ( ! $fax['sent'] ) {
				$line .= sprintf(
					' <span style="color: #a7aaad;">%s</span>',
					esc_html__( '（下書き）', 'hanmoto' )
				);
			}
			$lines[] = $line;
		}
		echo wp_kses_post( implode( '<br />', $lines ) );
	}

	/**
	 * Prime the caches of faxes to avoid N+1 queries in the list.
	 *
	 * @param \WP_Post[] $posts Posts of the query.
	 * @param \WP_Query  $query Query object.
	 * @return \WP_Post[]
	 */
	public function prime_fax_caches( $posts, $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || empty( $posts ) ) {
			return $posts;
		}
		if ( ModelOrder::post_type() !== $query->get( 'post_type' ) ) {
			return $posts;
		}
		$order_ids = wp_list_pluck( $posts, 'ID' );
		update_meta_cache( 'post', $order_ids );
		$fax_ids = [];
		foreach ( $order_ids as $order_id ) {
			$fax_ids = array_merge( $fax_ids, $this->model()->get_fax_ids( $order_id ) );
		}
		$fax_ids = array_values( array_unique( $fax_ids ) );
		if ( ! empty( $fax_ids ) ) {
			_prime_post_caches( $fax_ids, false, false );
		}
		return $posts;
	}
}
