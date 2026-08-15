<?php

namespace Hametuha\HanmotoHelper\Models;

use Hametuha\HanmotoHelper\Controller\PostType;
use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * 注文短冊（FAX送付分）
 *
 * 書店注文をまとめて「FAX送付分」として記録し、取次に送る短冊を印刷する。
 * 送付済みの注文には _order_fax が付くので、二重発注を防げる。
 *
 * @package hanmoto
 */
class ModelOrderFax extends Singleton {

	/**
	 * Post type name.
	 */
	const POST_TYPE = 'order-fax';

	/**
	 * Meta key on a fax post which holds the target order IDs.
	 */
	const META_ORDER = '_order';

	/**
	 * Meta key on an order post which holds the fax post ID.
	 */
	const META_FAX = '_order_fax';

	/**
	 * Meta key of the addressee.
	 */
	const META_FAX_TO = '_fax_to';

	/**
	 * How many slips are printed in a page.
	 */
	const PER_PAGE = 6;

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'admin_head', [ $this, 'enqueue_assets' ] );
		add_filter( 'bulk_actions-edit-' . ModelOrder::post_type(), [ $this, 'add_bulk_actions' ] );
		add_action( 'rest_api_init', [ $this, 'register_apis' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
		add_filter( 'template_include', [ $this, 'override_template' ] );
		// Filter the order list by the sending status.
		add_action( 'restrict_manage_posts', [ $this, 'render_fax_filter' ] );
		add_action( 'pre_get_posts', [ $this, 'filter_by_fax_status' ] );
	}

	/**
	 * Register post type.
	 *
	 * @return void
	 */
	public function register_post_types() {
		register_post_type( self::POST_TYPE, [
			'label'             => __( 'FAX送付分', 'hanmoto' ),
			'supports'          => [ 'title', 'author' ],
			'public'            => current_user_can( 'edit_others_posts' ),
			'show_in_nav_menu'  => false,
			'show_in_admin_bar' => false,
			'show_in_menu'      => 'edit.php?post_type=' . ModelOrder::post_type(),
			'capability_type'   => 'post',
			'map_meta_cap'      => true,
			// パーマリンクを使わない。public が権限で変わるので、
			// リライトルールの再生成に依存すると環境によって404になる。
			'rewrite'           => false,
			'capabilities'      => [
				// 一覧から一括操作で作るので、直接の新規作成は塞ぐ。
				'create_posts' => 'create_order_fax',
			],
		] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . ModelOrder::post_type() !== $screen->id ) {
			return;
		}
		wp_enqueue_script( 'hanmoto-order-fax-helper' );
	}

	/**
	 * Add bulk action to the order list.
	 *
	 * @param array $bulk_actions Registered bulk actions.
	 * @return array
	 */
	public function add_bulk_actions( $bulk_actions ) {
		$bulk_actions['make-order-fax'] = __( 'FAX送付分を作成', 'hanmoto' );
		return $bulk_actions;
	}

	/**
	 * Register REST API.
	 *
	 * @return void
	 */
	public function register_apis() {
		register_rest_route( 'hanmoto/v1', 'order-fax', [
			[
				'methods'             => 'POST',
				'args'                => [
					'ids' => [
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => function ( $vars ) {
							return ! empty( $this->parse_ids( $vars ) );
						},
					],
				],
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_others_posts' );
				},
				'callback'            => [ $this, 'create_fax' ],
			],
		] );
	}

	/**
	 * Convert comma separated IDs to the array of order IDs.
	 *
	 * @param string|int[] $ids IDs to parse.
	 * @return int[]
	 */
	protected function parse_ids( $ids ) {
		if ( ! is_array( $ids ) ) {
			$ids = explode( ',', (string) $ids );
		}
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		return $ids;
	}

	/**
	 * Create a fax post from the selected orders.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_fax( $request ) {
		$ids       = $this->parse_ids( $request->get_param( 'ids' ) );
		$targets   = [];
		$returning = 0;
		$sent      = 0;
		foreach ( $ids as $id ) {
			$order = get_post( $id );
			if ( ! $order || ModelOrder::post_type() !== $order->post_type ) {
				continue;
			}
			if ( 0 >= (int) get_post_meta( $id, '_amount', true ) ) {
				// 返品や冊数未入力は発注の短冊に混ぜられない。
				++$returning;
				continue;
			}
			if ( get_post_meta( $id, self::META_FAX, true ) ) {
				// 二重発注は実損になるので、送付済みは除外する。
				++$sent;
				continue;
			}
			$targets[] = $id;
		}
		if ( empty( $targets ) ) {
			return new \WP_Error( 'bad_request', $this->exclusion_message(
				__( '短冊にできる注文がありませんでした。', 'hanmoto' ),
				$returning,
				$sent
			), [ 'status' => 400 ] );
		}
		$post_id = wp_insert_post( [
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => sprintf(
				// translators: %s is date.
				__( 'FAX送付分 %s', 'hanmoto' ),
				date_i18n( __( 'Y年n月j日', 'hanmoto' ) )
			),
			'post_author' => get_current_user_id(),
		], true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		foreach ( $targets as $id ) {
			add_post_meta( $post_id, self::META_ORDER, $id );
			update_post_meta( $id, self::META_FAX, $post_id );
		}
		// 前回の宛先を初期値にしておく。毎回入力しなくてよい。
		$last = $this->get_last_fax_to( $post_id );
		if ( $last ) {
			update_post_meta( $post_id, self::META_FAX_TO, $last );
		}
		return new \WP_REST_Response( [
			'message'  => $this->exclusion_message(
				sprintf(
					// translators: %d is the number of orders.
					__( '%d件の注文でFAX送付分を作成しました。', 'hanmoto' ),
					count( $targets )
				),
				$returning,
				$sent
			),
			'id'       => $post_id,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
		] );
	}

	/**
	 * Build the message which tells the excluded orders.
	 *
	 * @param string $message   Base message.
	 * @param int    $returning Number of returning orders.
	 * @param int    $sent      Number of already sent orders.
	 * @return string
	 */
	protected function exclusion_message( $message, $returning, $sent ) {
		if ( $returning ) {
			$message .= "\n" . sprintf(
				// translators: %d is the number of orders.
				__( '返品・冊数なしの%d件は短冊にできないので除外しました。', 'hanmoto' ),
				$returning
			);
		}
		if ( $sent ) {
			$message .= "\n" . sprintf(
				// translators: %d is the number of orders.
				__( '送付済みの%d件は二重発注になるので除外しました。再送する場合は既存のFAX送付分を開いて印刷してください。', 'hanmoto' ),
				$sent
			);
		}
		return $message;
	}

	/**
	 * Get the addressee of the last fax.
	 *
	 * @param int $exclude Post ID to exclude.
	 * @return string
	 */
	protected function get_last_fax_to( $exclude = 0 ) {
		$posts = get_posts( [
			'post_type'   => self::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => 1,
			'exclude'     => array_filter( [ $exclude ] ),
			'orderby'     => 'ID',
			'order'       => 'DESC',
			'meta_query'  => [
				[
					'key'     => self::META_FAX_TO,
					'value'   => '',
					'compare' => '!=',
				],
			],
		] );
		return empty( $posts ) ? '' : (string) get_post_meta( $posts[0]->ID, self::META_FAX_TO, true );
	}

	/**
	 * Register meta box.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function add_meta_boxes( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}
		add_meta_box( 'order-fax-meta', __( 'FAX送付情報', 'hanmoto' ), [ $this, 'render_meta_box' ], $post_type, 'normal', 'high' );
	}

	/**
	 * Render meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'update_order_fax', '_hanmotofaxnonce', false );
		$slips = $this->get_slips( $post->ID );
		?>
		<p>
			<label>
				<?php esc_html_e( '宛先', 'hanmoto' ); ?><br />
				<input class="regular-text" type="text" name="fax_to"
					value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_FAX_TO, true ) ); ?>"
					placeholder="<?php esc_attr_e( '例・八木書店', 'hanmoto' ); ?>" />
			</label>
			<span class="description"><?php esc_html_e( '短冊の見出しに「◯◯様」と入ります。', 'hanmoto' ); ?></span>
		</p>
		<?php if ( empty( $slips ) ) : ?>
			<div class="notice notice-error inline">
				<p><?php esc_html_e( '注文が紐付けられていません。', 'hanmoto' ); ?></p>
			</div>
			<?php
			return;
		endif;
		?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( '短冊を印刷', 'hanmoto' ); ?>
			</a>
			<span class="description">
				<?php
				printf(
					// translators: %1$d is the number of orders, %2$d is the number of books, %3$d is the number of pages.
					esc_html__( '%1$d件 %2$d冊（%3$dページ）', 'hanmoto' ),
					count( $slips ),
					esc_html( $this->count_books( $slips ) ),
					(int) ceil( count( $slips ) / self::PER_PAGE )
				);
				?>
			</span>
		</p>
		<table class="widefat striped">
			<thead>
			<tr>
				<th><?php esc_html_e( '注文', 'hanmoto' ); ?></th>
				<th><?php esc_html_e( '受注日', 'hanmoto' ); ?></th>
				<th><?php esc_html_e( '書店', 'hanmoto' ); ?></th>
				<th><?php esc_html_e( '商品', 'hanmoto' ); ?></th>
				<th style="text-align: right;"><?php esc_html_e( '冊数', 'hanmoto' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $slips as $slip ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( (string) get_edit_post_link( $slip['order']->ID ) ); ?>">
							#<?php echo esc_html( $slip['order']->ID ); ?>
						</a>
					</td>
					<td><?php echo esc_html( mysql2date( 'Y-m-d', $slip['order']->post_date ) ); ?></td>
					<td><?php echo esc_html( $slip['shop_name'] ); ?></td>
					<td><?php echo esc_html( $slip['title'] ); ?></td>
					<td style="text-align: right;"><?php echo esc_html( number_format( $slip['amount'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save meta data.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_post( $post_id, $post ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hanmotofaxnonce' ), 'update_order_fax' ) ) {
			return;
		}
		update_post_meta( $post_id, self::META_FAX_TO, trim( (string) filter_input( INPUT_POST, 'fax_to' ) ) );
	}

	/**
	 * Get the slips of a fax post.
	 *
	 * 印刷に必要な値をすべて解決して返す。テンプレートはこれを並べるだけでよい。
	 * 返品などで短冊にできないものは除外する。
	 *
	 * @param int $post_id Post ID of fax.
	 * @return array[]
	 */
	public function get_slips( $post_id ) {
		$ids = $this->parse_ids( get_post_meta( $post_id, self::META_ORDER ) );
		if ( empty( $ids ) ) {
			return [];
		}
		$orders = get_posts( [
			'post_type'   => ModelOrder::post_type(),
			'post__in'    => $ids,
			'post_status' => 'any',
			'numberposts' => -1,
		] );
		$slips  = [];
		foreach ( $orders as $order ) {
			$amount = (int) get_post_meta( $order->ID, '_amount', true );
			if ( 0 >= $amount ) {
				continue;
			}
			$slips[] = $this->build_slip( $order, $amount );
		}
		// 受注日順。同じ日は書店ごと・書名ごとにまとめると仕分けやすい。
		usort( $slips, function ( $a, $b ) {
			foreach ( [ 'date', 'shop_name', 'title' ] as $key ) {
				$compared = strcmp( $a[ $key ], $b[ $key ] );
				if ( 0 !== $compared ) {
					return $compared;
				}
			}
			return $a['order']->ID - $b['order']->ID;
		} );
		return $slips;
	}

	/**
	 * Resolve everything printed on a slip.
	 *
	 * @param \WP_Post $order  Order post.
	 * @param int      $amount Amount of books.
	 * @return array
	 */
	protected function build_slip( $order, $amount ) {
		$shop    = $this->get_bookshop_term( $order->ID );
		$sources = get_the_terms( $order, 'source' );
		$product = $order->post_parent ? get_post( $order->post_parent ) : null;
		$price   = $product ? get_post_meta( $product->ID, '_regular_price', true ) : '';
		return [
			'order'      => $order,
			'amount'     => $amount,
			'date'       => mysql2date( 'Y-m-d', $order->post_date ),
			'source'     => ( ! empty( $sources ) && ! is_wp_error( $sources ) ) ? $sources[0]->name : '',
			'in_charge'  => (string) get_post_meta( $order->ID, '_in_charge_of', true ),
			'note'       => (string) $order->post_excerpt,
			'shop_name'  => $shop ? $shop->name : '',
			'wholesaler' => $shop ? (string) get_term_meta( $shop->term_id, 'wholesaler', true ) : '',
			'line_code'  => $shop ? (string) get_term_meta( $shop->term_id, 'line_code', true ) : '',
			'shop_code'  => $shop ? (string) get_term_meta( $shop->term_id, 'shop_code', true ) : '',
			'title'      => $product ? get_the_title( $product ) : '',
			'authors'    => $product ? (string) get_post_meta( $product->ID, 'hanmoto_authors', true ) : '',
			'publisher'  => $product ? (string) get_post_meta( $product->ID, 'hanmoto_publisher', true ) : '',
			'isbn'       => $product ? (string) get_post_meta( $product->ID, PostType::META_KEY_ISBN, true ) : '',
			'price'      => is_numeric( $price ) ? (int) $price : 0,
		];
	}

	/**
	 * Get the bookshop term of an order.
	 *
	 * @param int $order_id Post ID of order.
	 * @return \WP_Term|null
	 */
	protected function get_bookshop_term( $order_id ) {
		$terms = get_the_terms( $order_id, 'supplier' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}
		return $terms[0];
	}

	/**
	 * Count total books of slips.
	 *
	 * @param array[] $slips Slips.
	 * @return int
	 */
	public function count_books( $slips ) {
		$total = 0;
		foreach ( $slips as $slip ) {
			$total += $slip['amount'];
		}
		return $total;
	}

	/**
	 * Get publishers of slips.
	 *
	 * @param array[] $slips Slips.
	 * @return string
	 */
	public function get_publishers( $slips ) {
		$publishers = [];
		foreach ( $slips as $slip ) {
			if ( '' !== $slip['publisher'] && ! in_array( $slip['publisher'], $publishers, true ) ) {
				$publishers[] = $slip['publisher'];
			}
		}
		return implode( '・', $publishers );
	}

	/**
	 * Get order number printed on a slip.
	 *
	 * 旧システムはCSVの連番に経路を埋め込んでいたが、いまは注文経路がタクソノミーにある。
	 *
	 * @param array $slip Slip.
	 * @return string
	 */
	public function get_order_number( $slip ) {
		if ( $slip['source'] ) {
			// translators: %1$s is order source, %2$d is order ID.
			return sprintf( __( '%1$sNo.%2$d', 'hanmoto' ), $slip['source'], $slip['order']->ID );
		}
		// translators: %d is order ID.
		return sprintf( __( 'No.%d', 'hanmoto' ), $slip['order']->ID );
	}

	/**
	 * Override template for printing.
	 *
	 * @param string $template Template file.
	 * @return string
	 */
	public function override_template( $template ) {
		if ( is_singular( self::POST_TYPE ) ) {
			$template = hanmoto_root_dir() . '/template-parts/hanmoto/order-fax.php';
		}
		return $template;
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
			<option value="unsent" <?php selected( $current, 'unsent' ); ?>><?php esc_html_e( '未送付の注文', 'hanmoto' ); ?></option>
			<option value="sent" <?php selected( $current, 'sent' ); ?>><?php esc_html_e( '送付済みの注文', 'hanmoto' ); ?></option>
		</select>
		<?php
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
		if ( ! in_array( $status, [ 'sent', 'unsent' ], true ) ) {
			return;
		}
		$meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = [];
		}
		if ( 'unsent' === $status ) {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => self::META_FAX,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => self::META_FAX,
					'value'   => '',
					'compare' => '=',
				],
			];
		} else {
			$meta_query[] = [
				'key'     => self::META_FAX,
				'value'   => '',
				'compare' => '!=',
			];
		}
		$query->set( 'meta_query', $meta_query );
	}
}
