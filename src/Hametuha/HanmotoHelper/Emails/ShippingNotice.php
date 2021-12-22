<?php

namespace Hametuha\HanmotoHelper\Emails;

use Hametuha\HanmotoHelper\Controller\WooCommerceHelper;
use Hametuha\HanmotoHelper\Services\Pattern\AbstractEmail;
use Hametuha\HanmotoHelper\Services\Utilities\Accessor;
use Hametuha\HanmotoHelper\Services\WooCommerceOrder;

/**
 * Notices.
 *
 * @package hanmoto
 */
class ShippingNotice extends AbstractEmail {

	use Accessor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_id      = 'hanmoto_';
		$this->id             = 'shipping_notice';
		$this->customer_email = true;
		$this->email_type     = 'html';
		$this->title          = __( '注文出荷通知', 'hanmoto' );
		$this->description    = __( '商品の出荷が完了した時に顧客に通知されます。', 'hanmoto' );
		$this->placeholders   = [
			'{order_date}'   => '',
			'{order_number}' => '',
			'{will_capture}' => '',
		];
		// Order screen.
		add_action( 'woocommerce_order_actions', [ $this, 'order_actions' ] );
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'do_action' ], 10, 2 );
		// Trigger email.
		add_action( 'woocommerce_order_status_processing_to_' . WooCommerceOrder::STATUS, [ $this, 'trigger' ], 10, 2 );
		// Parent constructor.
		parent::__construct();
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '{site_title} ご注文#{order_number}を出荷しました', 'hanmoto' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( '#{order_number}出荷完了通知', 'hanmoto' );
	}

	/**
	 * Trigger email.
	 *
	 * @param int       $order_id Order id.
	 * @param \WC_Order $order    Order object.
	 */
	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();

		if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( is_a( $order, 'WC_Order' ) ) {
			$this->object                          = $order;
			$this->recipient                       = $order->get_billing_email();
			$this->placeholders['{order_date}']    = wc_format_datetime( $this->object->get_date_created() );
			$this->placeholders['{order_number}']  = $this->object->get_order_number();
			$this->placeholders['{{will_capture}'] = WooCommerceOrder::get_instance()->will_captured( $order );
		}

		if ( $this->is_enabled() && $this->get_recipient() && $this->order->is_shop_order( $order ) ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * Custom actions.
	 *
	 * @param string[] $actions
	 * @return string[]
	 */
	public function order_actions( $actions ) {
		global $theorder;
		if ( $this->order->is_shop_order( $theorder ) ) {
			$actions['send_shipped_notice'] = __( '出荷通知を送信', 'hanmoto' );
		}
		return $actions;
	}

	/**
	 * Post actions.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function do_action( $post_id, $post ) {
		$order = wc_get_order( $post_id );
		if ( ! $this->order->is_shop_order( $order ) ) {
			return;
		}
		$action = filter_input( INPUT_POST, 'wc_order_action' );
		if ( 'send_shipped_notice' !== $action ) {
			return;
		}
		$this->trigger( $order->get_id(), $order );
	}
}
