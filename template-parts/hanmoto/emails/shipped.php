<?php
/**
 * Email template for book shop order.
 *
 * @package hanmoto
 * @var \Hametuha\HanmotoHelper\Emails\ShippingNotice $email
 * @var string   $email_heading
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Order $order
 * @var string   $additional_content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<?php /* translators: %s: Customer first name */ ?>
	<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) ); ?></p>

	<p>
	<?php
	/* translators: %s Capture date */
	printf( esc_html__( '書店注文分を発送いたしました。%sに請求を確定いたします。', 'hanmoto' ), esc_html( $email->order->will_captured( $order ) ) );
	?>
	</p>
<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
do_action( 'woocommerce_email_footer', $email );
