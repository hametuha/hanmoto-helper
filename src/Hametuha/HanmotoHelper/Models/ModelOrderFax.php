<?php

namespace Hametuha\HanmotoHelper\Models;

use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\OrderFaxOrders;

/**
 * 注文短冊（FAX送付分）
 *
 * 書店注文をまとめて「FAX送付分」として記録し、取次に送る短冊を印刷する。
 * 注文側の _order_fax は複数値。1つの注文が複数の送付分に入ることがある。
 * 一括操作は送付済みを弾くが、編集画面からは人間の判断で追加できる。
 *
 * @package hanmoto
 */
class ModelOrderFax extends Singleton {

	use OrderFaxOrders;

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
		add_filter( 'template_include', [ $this, 'override_template' ] );
		// 送付分を完全に消したら、注文側に残る紐付けも掃除する。
		add_action( 'before_delete_post', [ $this, 'before_delete_post' ] );
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
		$preparing = 0;
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
			$faxes = $this->get_faxes_of_order( $id );
			if ( $faxes ) {
				// 二重発注は実損になるので、すでに送付分に入っている注文は除外する。
				// まだ送っていない（下書きの）送付分なら、そちらに足せばよい。
				if ( wp_list_filter( $faxes, [ 'sent' => true ] ) ) {
					++$sent;
				} else {
					++$preparing;
				}
				continue;
			}
			$targets[] = $id;
		}
		if ( empty( $targets ) ) {
			return new \WP_Error( 'bad_request', $this->exclusion_message(
				__( '短冊にできる注文がありませんでした。', 'hanmoto' ),
				$returning,
				$sent,
				$preparing
			), [ 'status' => 400 ] );
		}
		$post_id = wp_insert_post( [
			'post_type'   => self::POST_TYPE,
			// 下書きで作る。公開したら「送付済み」の意味になる。
			'post_status' => 'draft',
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
			add_post_meta( $id, self::META_FAX, $post_id );
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
					__( '%d件の注文でFAX送付分を下書きで作成しました。FAXを送ったら「公開」にしてください。', 'hanmoto' ),
					count( $targets )
				),
				$returning,
				$sent,
				$preparing
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
	 * @param int    $preparing Number of orders which are in a draft fax.
	 * @return string
	 */
	protected function exclusion_message( $message, $returning, $sent, $preparing = 0 ) {
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
		if ( $preparing ) {
			$message .= "\n" . sprintf(
				// translators: %d is the number of orders.
				__( '準備中の送付分に入っている%d件は除外しました。まとめたい場合はその送付分を開いて追加してください。', 'hanmoto' ),
				$preparing
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
	 * Clean up the links when a fax is deleted for good.
	 *
	 * 掃除しないと、注文が永久に「送付済み」のまま一括操作から弾かれる。
	 * ゴミ箱は復元できるので、完全削除のときだけ掃除する。
	 *
	 * @param int $post_id Post ID being deleted.
	 * @return void
	 */
	public function before_delete_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}
		$this->detach_orders( $post_id, $this->get_order_ids( $post_id ) );
	}
}
