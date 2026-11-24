<?php

namespace Hametuha\HanmotoHelper\Services;


use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * Decide when a book shop order is captured (status completed).
 *
 * Completing a book shop order lets WooPay.jp capture its authorized charge.
 * Pay.JP releases the authorization at the expiry WooPay.jp saves on the order
 * (`_woocommerce_payjp_should_expires`), which can be much shorter than our
 * own setting (e.g. a few days for foreign-issued cards). So the authorization
 * expiry takes priority, and the setting is only a fallback for orders
 * without one (immediately captured charges, other payment methods).
 *
 * @package hanmoto
 */
class OrderCapture extends Singleton {

	/**
	 * Processing date in GMT (Y-m-d H:i:s).
	 */
	const META_KEY_PROCESSING = '_order_will_captured';

	/**
	 * Authorization expiry saved by WooPay.jp, in site timezone.
	 */
	const META_KEY_PAYJP_EXPIRES = '_woocommerce_payjp_should_expires';

	/**
	 * Card origin saved by WooPay.jp: jp, foreign or unknown.
	 */
	const META_KEY_PAYJP_CARD_ORIGIN = '_woocommerce_payjp_card_is_jp';

	/**
	 * Set when WooPay.jp has captured the charge.
	 */
	const META_KEY_PAYJP_CAPTURED = '_woocommerce_payjp_captured';

	/**
	 * Cron hook name. Keep it as is: sites already have it scheduled.
	 */
	const CRON_EVENT = 'hanmoto_capture_order';

	/**
	 * @inheritDoc
	 */
	protected function init() {
		add_action( 'woocommerce_order_status_processing', [ $this, 'set_processing_date' ], 1 );
		add_action( 'init', [ $this, 'register_cron' ] );
		add_action( self::CRON_EVENT, [ $this, 'do_cron' ] );
		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'render_admin_info' ] );
	}

	/**
	 * Book shop order checker.
	 *
	 * @return WooCommerceOrder
	 */
	protected function shop_order() {
		return WooCommerceOrder::get_instance();
	}

	/**
	 * Save the processing date and leave a note on when it will be captured.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function set_processing_date( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $this->shop_order()->is_shop_order( $order ) ) {
			return;
		}
		$order->update_meta_data( self::META_KEY_PROCESSING, current_time( 'mysql', true ) );
		$order->save_meta_data();
		// translators: %s is date.
		$note = sprintf( __( 'この注文は%sに請求されます。', 'hanmoto' ), $this->will_captured( $order ) );
		if ( $this->authorization_expires( $order ) ) {
			// translators: %s is card origin.
			$note .= sprintf( __( '（カードの与信期限による。カード: %s）', 'hanmoto' ), $this->card_origin_label( $order ) );
		}
		$order->add_order_note( $note );
	}

	/**
	 * Get authorization expiry as UNIX timestamp, if it still matters.
	 *
	 * @param \WC_Order $order Order object.
	 * @return int 0 if the order has no pending authorization.
	 */
	public function authorization_expires( $order ) {
		if ( 'payjp' !== $order->get_payment_method() || $order->get_meta( self::META_KEY_PAYJP_CAPTURED ) ) {
			return 0;
		}
		$expires = (string) $order->get_meta( self::META_KEY_PAYJP_EXPIRES );
		if ( ! $expires ) {
			return 0;
		}
		try {
			return ( new \DateTimeImmutable( $expires, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Get the time the order should be captured.
	 *
	 * @param \WC_Order $order Order object.
	 * @param int       $days  Fallback days after processing. Negative means the setting.
	 * @return int UNIX timestamp. 0 if the order has never been processing.
	 */
	public function capture_timestamp( $order, $days = -1 ) {
		$expires = $this->authorization_expires( $order );
		if ( $expires ) {
			/**
			 * Seconds before the authorization expiry to capture.
			 *
			 * Cron runs hourly, so capturing right at the expiry would be too late.
			 *
			 * @param int       $margin Default 1 day.
			 * @param \WC_Order $order  Order object.
			 */
			$margin = (int) apply_filters( 'hanmoto_capture_expiry_margin', DAY_IN_SECONDS, $order );
			return $expires - $margin;
		}
		$processed = (string) $order->get_meta( self::META_KEY_PROCESSING );
		if ( ! $processed ) {
			return 0;
		}
		if ( 0 > $days ) {
			$days = WooCommerceSetting::capture_days();
		}
		return strtotime( $processed . ' UTC' ) + DAY_IN_SECONDS * $days;
	}

	/**
	 * Get captured date.
	 *
	 * @param \WC_Order|int $order  Order object.
	 * @param string        $format Date format.
	 * @return string
	 */
	public function will_captured( $order, $format = '' ) {
		$order = wc_get_order( $order );
		if ( ! $order ) {
			return '';
		}
		$timestamp = $this->capture_timestamp( $order );
		if ( ! $timestamp ) {
			return '';
		}
		return wp_date( $format ?: get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Label of card origin.
	 *
	 * @param \WC_Order $order Order object.
	 * @return string
	 */
	public function card_origin_label( $order ) {
		switch ( $order->get_meta( self::META_KEY_PAYJP_CARD_ORIGIN ) ) {
			case 'jp':
				return __( '国内発行', 'hanmoto' );
			case 'foreign':
				return __( '海外発行', 'hanmoto' );
			default:
				return __( '不明', 'hanmoto' );
		}
	}

	/**
	 * Get shipped book shop orders to be captured.
	 *
	 * @param int    $offset Fallback days after processing. Negative means the setting.
	 * @param string $date   Date to compare in GMT.
	 * @return \WC_Order[]
	 */
	public function get_order_to_capture( $offset = -1, $date = 'now' ) {
		$now    = 'now' === $date ? time() : strtotime( $date . ' UTC' );
		$orders = wc_get_orders( [
			'status' => [ WooCommerceOrder::STATUS ],
			'limit'  => -1,
		] );
		return array_values( array_filter( $orders, function ( $order ) use ( $offset, $now ) {
			$timestamp = $this->capture_timestamp( $order, (int) $offset );
			return $timestamp && $timestamp <= $now;
		} ) );
	}

	/**
	 * Register cron
	 */
	public function register_cron() {
		if ( wp_next_scheduled( self::CRON_EVENT ) ) {
			return;
		}
		wp_schedule_event( time(), 'hourly', self::CRON_EVENT );
	}

	/**
	 * Complete shipped book shop orders, which lets WooPay.jp capture them.
	 */
	public function do_cron() {
		foreach ( $this->get_order_to_capture() as $order ) {
			if ( ! $this->shop_order()->is_shop_order( $order ) ) {
				continue;
			}
			$order->update_status( 'completed' );
		}
	}

	/**
	 * Show capture date and card origin on the order screen.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function render_admin_info( $order ) {
		if ( ! $this->shop_order()->is_shop_order( $order ) ) {
			return;
		}
		$will_captured = $this->will_captured( $order, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		?>
		<p class="form-field form-field-wide">
			<strong><?php esc_html_e( '書店注文の請求予定', 'hanmoto' ); ?></strong><br />
			<?php echo esc_html( $will_captured ?: __( '未定（処理中になっていません）', 'hanmoto' ) ); ?>
			<?php if ( 'payjp' === $order->get_payment_method() ) : ?>
				<br />
				<?php
				// translators: %s is card origin.
				echo esc_html( sprintf( __( 'カード: %s', 'hanmoto' ), $this->card_origin_label( $order ) ) );
				?>
			<?php endif; ?>
		</p>
		<?php
	}
}
