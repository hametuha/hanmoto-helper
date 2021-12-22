<?php

namespace Hametuha\HanmotoHelper\Services;


use Hametuha\HanmotoHelper\Controller\WooCommerceHelper;
use Hametuha\HanmotoHelper\Emails\ShippingNotice;
use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * Order detail.
 *
 * @package hanmoto
 */
class WooCommerceOrder extends Singleton {

	/**
	 * Custom coupon type.
	 */
	const COUPON_TYPE = 'book_shop';

	/**
	 * Order status.
	 */
	const STATUS = 'shipped';

	const META_KEY_WILL_CAPTURE = '_order_will_captured';

	const CRON_EVENT = 'hanmoto_capture_order';

	/**
	 * @inheritDoc
	 */
	protected function init() {
		add_action( 'woocommerce_account_edit-address_endpoint', [ $this, 'book_shop_form' ], 11 );
		add_action( 'rest_api_init', [ $this, 'register_endpoint' ] );
		add_filter( 'map_meta_cap', [ $this, 'meta_cap' ], 10, 4 );
		// Add coupon type.
		add_filter( 'woocommerce_coupon_discount_types', [ $this, 'custom_coupon_type' ] );
		// cart template.
		add_action( 'woocommerce_cart_coupon', [ $this, 'display_coupon' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_cart_helper' ] );
		// Calculate coupon.
		add_filter( 'woocommerce_product_coupon_types', [ $this, 'add_coupon_type' ] );
		add_filter( 'woocommerce_coupon_is_valid_for_product', [ $this, 'validate_coupon' ], 10, 4 );
		add_filter( 'woocommerce_coupon_get_discount_amount', [ $this, 'get_discount_amount' ], 10, 5 );
		add_filter( 'woocommerce_admin_order_buyer_name', [ $this, 'order_buyer_name' ], 10, 2 );
		// Add status.
		add_action( 'init', [ $this, 'register_status' ] );
		add_filter( 'wc_order_statuses', [ $this, 'order_statuses' ] );
		add_action( 'woocommerce_order_status_processing', [ $this, 'set_processing_date' ], 1 );
		add_filter( 'click_post_for_woo_imported_order_status', [ $this, 'imported_order_status' ], 10, 2);
		// Notifications.
		add_action( 'woocommerce_thankyou_order_received_text', [ $this, 'thank_you_notice' ], 10, 2 );
		add_action( 'woocommerce_email_order_details', [ $this, 'processing_email' ], 10, 4 );
		add_action( 'woocommerce_email_before_order_table', [ $this, 'order_notification' ], 10, 4 );
		add_filter( 'woocommerce_email_classes', [ $this, 'email_classes' ] );
		// Status update via cron.
		add_action( 'init', [ $this, 'register_cron' ] );
		add_action( self::CRON_EVENT, [ $this, 'do_cron' ] );
	}

	/**
	 * Register status.
	 */
	public function register_status() {
		register_post_status( 'wc-' . self::STATUS, [
			'label'                     => __( '出荷済み', 'hanmoto' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( '出荷済み (%s)', '出荷済み(%s)', 'hanmoto' )
		] );
	}

	/**
	 * Add custom status.
	 *
	 * @param string[] $statuses Statueses.
	 * @return string[]
	 */
	public function order_statuses( $statuses ) {
		$new_statuses = array();
		foreach ( $statuses as $key => $status ) {
			$new_statuses[ $key ] = $status;
			if ( 'wc-processing' === $key ) {
				$new_statuses[ 'wc-' . self::STATUS ] = __( '出荷済み', 'hanmoto' );
			}
		}
		return $new_statuses;
	}

	/**
	 * Add extr a capability.
	 *
	 * @param string[] $caps    Capabilities.
	 * @param string   $cap     Capability to be checked.
	 * @param int      $user_id User ID.
	 *
	 * @return string[]
	 */
	public function meta_cap( $caps, $cap, $user_id ) {
		if ( 'book_shop' === $cap ) {
			if ( get_user_meta( $user_id, 'is_book_shop', true ) ) {
				$caps []= 'read';
			} else {
				$caps []= 'do_not_allow';
			}
			$caps = array_values( array_filter( $caps, function( $c ) {
				return 'book_shop' !== $c;
			} ) );
		}
		return $caps;
	}

	/**
	 * Register REST API
	 */
	public function register_endpoint() {
		register_rest_route( 'hanmoto/v1', 'registration/shop', [
			[
				'methods'             => [ 'POST', 'DELETE' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'callback'            => [ $this, 'rest_callback' ],
			],
		] );
	}

	/**
	 * REST request.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_callback( \WP_REST_Request $request ) {
		$user_is = get_user_meta( get_current_user_id(), 'is_book_shop', true );
		switch ( $request->get_method() ) {
			case 'POST':
				if ( $user_is ) {
					return new \WP_Error( 'invalid_request', __( 'すでに書店として登録されています。', 'hanmoto' ), [
						'response' => 400,
					] );
				}
				update_user_meta( get_current_user_id(), 'is_book_shop', 1 );
				break;
			case 'DELETE':
				if ( ! $user_is ) {
					return new \WP_Error( 'invalid_request', __( '書店として登録されていません。', 'hanmoto' ), [
						'response' => 400,
					] );
				}
				delete_user_meta( get_current_user_id(), 'is_book_shop' );
				break;
		}
		return new \WP_REST_Response( [
			'success' => true,
		] );
	}

	/**
	 * Render book shop form
	 *
	 * @return void
	 */
	public function book_shop_form( $value  ) {
		wp_enqueue_script( 'hanmoto-book-shop-account' );
		wp_localize_script( 'hanmoto-book-shop-account', 'HanmotoBookShopAccount', [
			'confirm_on'  => __( '請求先住所を書店として登録します。よろしいですか？', 'hanmoto' ),
			'confirm_off' => __( '書店としての登録を解除します。以降、仕入れ注文はできなくなります。', 'hanmoto' ),
		] );
		?>
		<hr style="clear: both;" />
		<h2><?php esc_html_e( '書店登録', 'hanmoto' ); ?></h2>
		<p>
			<label>
				<input type="checkbox" value="1" id="hanmoto-is-book-shop"<?php checked( current_user_can( 'book_shop' ) ) ?> />
				<?php esc_html_e( '書店として利用する', 'hanmoto' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Permission callback.
	 *
	 * @return bool|\WP_Error
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'read' );
	}

	/**
	 * ADdd coupon types.
	 *
	 * @param sting $types Coupon types.
	 * @return string[]
	 */
	public function add_coupon_type( $types ) {
		$types[] = self::COUPON_TYPE;
		return $types;
	}

	/**
	 * Add coupon type.
	 *
	 * @param array $discount_types value and labe.
	 * @return array
	 */
	public function custom_coupon_type( $discount_types ) {
		$discount_types[ self::COUPON_TYPE ] =__( '書店注文', 'hanmoto' );
		return $discount_types;
	}

	/**
	 * Enqueue cart helper.
	 *
	 * @return void
	 */
	public function enqueue_cart_helper() {
		wp_enqueue_script( 'hanmoto-cart-helper' );
	}

	/**
	 * Display shop order coupon.
	 *
	 * @return void
	 */
	public function display_coupon() {
		$coupon = $this->get_shop_coupon();
		if ( ! $coupon ) {
			return;
		}
		if ( ! current_user_can( 'book_shop' ) ) {
			printf(
				'<span class="description">%s &raquo; <a href="%s">%s</a></span>',
				esc_html__( '書店取引をご希望の方はログインして「マイアカウント ＞ 住所 ＞ 請求先情報」から登録してください。', 'hanmoto' ),
				esc_url( wc_get_page_permalink( 'myaccount' ) ),
				esc_html__( 'マイアカウント', 'hanmoto' )
			);
		} else {
			printf(
				'<button class="button" id="book-shop-coupon" data-coupon="%s">%s</span>',
				esc_attr( $coupon->get_code() ),
				esc_html__( '書店注文価格を適用', 'hanmoto' )
			);
		}
	}

	/**
	 * Get coupon.
	 *
	 * @return \WC_Coupon|null
	 */
	public function get_shop_coupon() {
		$coupon_id = get_option( 'hanmoto_book_shop_coupon' );
		$coupon    = new \WC_Coupon( $coupon_id );
		return ( $coupon->get_id() && $coupon->is_type( self::COUPON_TYPE ) ) ? $coupon : null;
	}

	/**
	 * Helper instance.
	 *
	 * @return WooCommerceHelper
	 */
	protected function helper() {
		return WooCommerceHelper::get_instance();
	}

	/**
	 * Is shop coupon?
	 *
	 * @param \WC_Coupon $coupon Coupon object.
	 * @return bool
	 */
	public function is_shop_coupon( $coupon ) {
		if ( ! $coupon->is_type( self::COUPON_TYPE ) ) {
			return false;
		}
		$store_coupon = $this->get_shop_coupon();
		if ( ! $store_coupon || ( $coupon->get_code() !== $store_coupon->get_code() ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Is book shop order?
	 *
	 * @param int|\WC_Order $order Order.
	 * @return bool
	 */
	public function is_shop_order( $order ) {
		$order = wc_get_order( $order );
		$shop_coupon = $this->get_shop_coupon();
		if ( ! $shop_coupon ) {
			return false;
		}
		foreach ( $order->get_coupon_codes() as $code ) {
			if ( $code === $shop_coupon->get_code() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is coupon valid for product?
	 *
	 * @param bool        $valid   Is valid coupon.
	 * @param \WC_Product $product Product.
	 * @param \WC_Coupon  $coupon  Coupon.
	 * @param array       $values  Options.
	 *
	 * @return bool
	 */
	public function validate_coupon( $valid, $product, $coupon, $values ) {
		// Is store coupon?
		if ( ! $this->is_shop_coupon( $coupon ) ) {
			return $valid;
		}
		// Is book shop owner?
		if ( ! current_user_can( 'book_shop' ) ) {
			return false;
		}
		// Is product allow book shop order?
		return $this->helper()->product_can_order( $product );
	}

	/**
	 * Calculate amount.
	 *
	 * @param float      $discount           Final price.
	 * @param float      $discounting_amount Amount.
	 * @param \stdClass  $cart_item
	 * @param bool       $single
	 * @param \WC_Coupon $coupon
	 *
	 * @return float|int
	 */
	public function get_discount_amount( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
		if ( $this->is_shop_coupon( $coupon ) ) {
			$rate = min( 100, $coupon->get_amount() ) / 100;
			$discount = $cart_item[ 'line_subtotal' ] / $cart_item['quantity'] * $rate;
		}
		return $discount;
	}

	/**
	 * Display as post stats.
	 *
	 * @param string    $buyer Name.
	 * @param \WC_Order $order Order object.
	 *
	 * @return string
	 */
	public function order_buyer_name( $buyer, $order ) {
		$shop_coupon = $this->get_shop_coupon();
		if ( ! $shop_coupon ) {
			return $buyer;
		}
		$coupons = array_filter( $order->get_coupons(), function( $coupon ) use ( $shop_coupon ) {
			return $coupon->get_code() === $shop_coupon->get_code();
		} );
		if ( ! empty( $coupons ) ) {
			$company = get_post_meta( $order->get_id(), '_billing_company', true );
			if ( $company ) {
				$buyer = $company;
			}
			$buyer .= __( '（書店注文）', 'hanmoto' );
		}
		return $buyer;
	}

	/**
	 * Display order status.
	 *
	 * @param string    $message Message.
	 * @param \WC_Order $order   Order object.
	 * @return string
	 */
	public function thank_you_notice( $message, $order ) {
		if ( $this->is_shop_order( $order ) ) {
			$message = __( '書店注文を受け付けました。発送通知まで今しばらくお待ちください。', 'hanmoto' );
		}
		return $message;
	}

	/**
	 * Save order action.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public function set_processing_date( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $this->is_shop_order( $order ) ) {
			return;
		}
		$now = current_time( 'mysql', true );
		update_post_meta( $order_id, self::META_KEY_WILL_CAPTURE, $now );
		$order->add_order_note( sprintf( __( 'この注文は%sに請求されます。', 'hanmoto' ), $this->will_captured( $order ) ) );
	}

	/**
	 * Email message.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Is admin?
	 * @param bool      $plain_text    Is plain text?
	 * @param \WC_Email $email         email object.
	 *
	 * @return void
	 */
	public function processing_email( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! $order->has_status( 'processing' ) || ! $this->is_shop_order( $order ) ) {
			return;
		}
		$message = __( '書店仕入れの注文として受け付けました。出荷後にご連絡差し上げます。', 'hanmoto' );
		if ( $plain_text ) {
			echo "\n" . $message . "\n";
		} else {
			printf(
				'<p><strong>%s: </strong><br />%s</p>',
				esc_html__( '【書店注文】', 'hanmoto' ),
				esc_html( $message )
			);
		}
	}

	/**
	 * Get captured date.
	 *
	 * @param \WC_Order $order  Order object.
	 * @param string    $format Date format.
	 * @return string
	 */
	public function will_captured( $order, $format = '' ) {
		$order = wc_get_order( $order );
		if ( ! $order ) {
			return '';
		}
		if ( ! $format ) {
			$format = get_option( 'date_format' );
		}
		$ordered = strtotime( get_post_meta( $order->get_id(), self::META_KEY_WILL_CAPTURE, true ) );
		$ordered += 60 * 60 * 24 * WooCommerceSetting::capture_days();
		return date_i18n( $format, $ordered );
	}

	/**
	 * List of available emails.
	 *
	 * @param \WC_Email[] $emails Email classes.
	 * @return \WC_Email[]
	 */
	public function email_classes( $emails ) {
		$emails[ 'hanmoto-shipping-notice' ] = new ShippingNotice();
		return $emails;
	}

	/**
	 * Get order to be captured.
	 *
	 * @param int    $offset Offset days.
	 * @param string $date   Date.
	 *
	 * @return \WC_Order[]
	 */
	public function get_order_to_capture( $offset = -1, $date = 'now' ) {
		if ( 0 > $offset ) {
			$offset = WooCommerceSetting::capture_days();
		}
		if ( 'now' === $date ) {
			$date = current_time( 'mysql', true );
		}
		$time = strtotime( $date ) - 60 * 60 * 24 * $offset;
		$should = date( 'Y-m-d H:i:s', $time );
		$query = new \WP_Query( [
			'post_type'      => 'shop_order',
			'post_status'    => 'wc-' . self::STATUS,
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => self::META_KEY_WILL_CAPTURE,
					'value'   => $should,
					'compare' => '<=',
					'type'    => 'DATETIME',
				],
			],
		] );
		return array_map( 'wc_get_order', $query->posts );
	}

	/**
	 * Register cron
	 */
	public function register_cron() {
		if ( wp_next_scheduled( self::CRON_EVENT ) ) {
			return;
		}
		wp_schedule_event( current_time( 'timestamp', true ), 'hourly', self::CRON_EVENT );
	}

	/**
	 * Execute cron.
	 */
	public function do_cron() {
		foreach ( $this->get_order_to_capture() as $order ) {
			if ( ! $this->is_shop_order( $order ) ) {
				continue;
			}
			// Complete order.
			$order->update_status( 'completed' );
		}
	}

	/**
	 * Change status of order if imported via click-post-for-woo
	 *
	 * @param string    $status Default "completed"
	 * @param \WC_Order $order  Order object.
	 *
	 * @return string
	 */
	public function imported_order_status( $status, $order ) {
		if ( $this->is_shop_order( $order ) ) {
			$status = self::STATUS;
		}
		return $status;
	}
}
