<?php

namespace Hametuha\HanmotoHelper\Utility;


use cli\Table;
use Hametuha\HanmotoHelper\Controller\PostType;
use Hametuha\HanmotoHelper\Services\WooCommerceOrder;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\CSS\OpacitySniff;

/**
 * Utility command for hanmoto helper.
 *
 *
 * @package hanmoto
 */
class Commands extends \WP_CLI_Command {

	use OpenDbApi,
		SettingsAccessor;

	/**
	 * Get detailed information of books.
	 *
	 * @synopsis <isbn>
	 * @param array $args Command arguments.
	 */
	public function detail( $args ) {
		list( $isbn ) = $args;
		$result       = $this->openbd_get( $isbn );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		print_r( $result );
	}

	/**
	 * Get list of ISBN.
	 *
	 * @synopsis <publisher_id>
	 * @param array $args Command arguments.
	 */
	public function filter( $args ) {
		list( $publisher_id ) = $args;
		\WP_CLI::line( sprintf( 'Retrieving books of %s...', $publisher_id ) );
		$result = $this->openbd_filter( explode( ',', $publisher_id ) );
		if ( empty( $result ) ) {
			\WP_CLI::error( 'No Book Found.' );
		}
		$details = $this->openbd_get( $result );
		$table   = new Table();
		$table->setHeaders( [ 'ISBN', 'Title', 'Author', 'Category', 'Price', 'Published' ] );
		foreach ( $details as $detail ) {
			$table->addRow( [
				$detail['onix']['RecordReference'],
				$detail['onix']['DescriptiveDetail']['TitleDetail']['TitleElement']['TitleText']['content'],
				implode( ', ', array_map( function( $author ) {
					return $author['PersonName']['content'];
				}, $detail['onix']['DescriptiveDetail']['Contributor'] ) ),
				$detail['onix']['DescriptiveDetail']['Subject'][0]['SubjectCode'],
				$detail['onix']['ProductSupply']['SupplyDetail']['Price'][0]['PriceAmount'],
				preg_replace( '/(\d{4})(\d{2})(\d{2})/', '$1/$2/$3', $detail['summary']['pubdate'] ),
			] );
		}
		$table->display();
	}

	/**
	 * Sync posts.
	 *
	 */
	public function sync() {
		$ids = $this->option()->get_publisher_ids();
		if ( empty( $ids ) ) {
			\WP_CLI::error( 'Publisher ID not set.' );
		}
		\WP_CLI::line( sprintf( 'Syncing %s...', implode( ', ', $ids ) ) );
		$result = PostType::get_instance()->sync();
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		list( $created, $updated, $failed ) = $result;
		\WP_CLI::success( sprintf( 'Created %d, Updated %d, Failed %d', $created, $updated, $failed ) );
	}

	/**
	 * Display order list.
	 *
	 * @param array $args command option.
	 * @synopsis <days> [<date>]
	 */
	public function orders( $args ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			\WP_CLI::error( 'WooCommerce is not active.' );
		}
		$args[]              = 'now';
		list( $days, $date ) = $args;
		$orders              = WooCommerceOrder::get_instance()->get_order_to_capture( $days, $date );
		if ( empty( $orders ) ) {
			\WP_CLI::success( 'No orders matched.' );
			exit;
		}
		$table = new \cli\Table();
		$table->setHeaders( [ 'ID', 'Name', 'Price', 'Captured' ] );
		foreach ( $orders as $order ) {
			$name    = $order->get_formatted_billing_full_name();
			$company = $order->get_billing_company();
			if ( $company ) {
				$name .= sprintf( ' (%s)', $company );
			}
			$table->addRow( [
				$order->get_id(),
				$name,
				$order->get_total(),
				WooCommerceOrder::get_instance()->will_captured( $order, 'Y-m-d H:i:s' ),
			] );
		}
		$table->display();
	}
}
