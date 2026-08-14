<?php

namespace Hametuha\HanmotoHelper\Utility;


use cli\Table;
use Hametuha\HanmotoHelper\Controller\PostType;
use Hametuha\HanmotoHelper\Models\ModelInventory;
use Hametuha\HanmotoHelper\Services\WooCommerceOrder;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\CSS\OpacitySniff;

/**
 * Utility command for hanmoto helper.
 *
 *
 * @package hanmoto
 */
class Commands extends \WP_CLI_Command {

	use OpenDbApi;
	use SettingsAccessor;
	use Validator;

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
				implode( ', ', array_map( function ( $author ) {
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

	/**
	 * Get stock at specified date.
	 *
	 * @synopsis <post_id> <date>
	 * @param array $args Command options.
	 * @return void
	 */
	public function stock( $args ) {
		list( $post_id, $date ) = $args;
		if ( ! $this->is_date( $date ) ) {
			\WP_CLI::error( __( '日付形式が不正です。', 'hanmoto' ) . ' ' . $date );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			\WP_CLI::error( __( '投稿を発見できませんでした。', 'hanmoto' ) . ' ' . $date );
		}
		// translators: %1$d is id, %2$s is title.
		\WP_CLI::line( sprintf( __( '#%1$d %2$s の在庫を確認しています。', 'hanmoto' ), $post_id, get_the_title( $post ) ) );
		$stock = ModelInventory::get_instance()->get_stock( $post_id, $date );
		// translators: %1$s is date, %2$d is stock.
		\WP_CLI::line( sprintf( __( '%1$s以前での在庫は%2$dです。', 'hanmoto' ), mysql2date( get_option( 'date_format' ), $date ), $stock ) );
		$changes = ModelInventory::get_instance()->get_inventory_changes( $post_id, $date );
		if ( is_wp_error( $changes ) ) {
			\WP_CLI::error( $changes->get_error_message() );
		}

		if ( empty( $changes ) ) {
			\WP_CLI::error( __( '該当期間のデータはありません。', 'hanmoto' ) );
		}
		$table = new Table();
		$table->setHeaders( [ 'id', 'Date', 'Title', 'Type', 'Supplier', 'Amount', 'Subtotal' ] );
		foreach ( $changes as $change ) {
			$stock += $change['amount'];
			$table->addRow( [ $change['id'], $change['date'], $change['title'], $change['type'], $change['supplier'], $change['amount'], $stock ] );
		}
		$table->display();
	}

	/**
	 * Swap 番線(line_code) and 書店コード(shop_code) of bookshops which have them in reverse.
	 *
	 * The CSV importer used to save「取次書店コード」as line_code and「作業コード」as shop_code,
	 * but line_code means 番線(e.g. 12A34) and shop_code means 書店コード(e.g. 123456).
	 * This command detects the reversed bookshops by the format of the codes and fixes them.
	 * Running it twice is safe because a fixed bookshop no longer matches the condition.
	 *
	 * @subcommand fix-shop-codes
	 * @synopsis [--dry-run]
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 * @return void
	 */
	public function fix_shop_codes( $args, $assoc_args ) {
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$parent  = get_term_by( 'slug', 'bookshop', 'supplier' );
		if ( ! $parent ) {
			\WP_CLI::error( __( 'タクソノミー「書店」が登録されていません。', 'hanmoto' ) );
		}
		$terms = get_terms( [
			'taxonomy'   => 'supplier',
			'hide_empty' => false,
			'parent'     => $parent->term_id,
		] );
		if ( is_wp_error( $terms ) ) {
			\WP_CLI::error( $terms->get_error_message() );
		}
		$codes     = [];
		$planned   = [];
		$valid     = 0;
		$empty     = 0;
		$ambiguous = [];
		foreach ( $terms as $term ) {
			$line = trim( (string) get_term_meta( $term->term_id, 'line_code', true ) );
			$shop = trim( (string) get_term_meta( $term->term_id, 'shop_code', true ) );
			// Keep the codes after this command so that the collision can be detected.
			$codes[ $term->term_id ] = [ $term, $line, $shop ];
			if ( '' === $line && '' === $shop ) {
				++$empty;
				continue;
			}
			if ( $this->is_line_code( $line ) && $this->is_shop_code( $shop ) ) {
				// Already correct.
				++$valid;
				continue;
			}
			if ( ! $this->is_shop_code( $line ) || ! $this->is_line_code( $shop ) ) {
				// Neither correct nor reversed, so a human should decide.
				$ambiguous[] = sprintf( '%s [%d] 番線=%s 書店コード=%s', $term->name, $term->term_id, $line ?: '(空)', $shop ?: '(空)' );
				continue;
			}
			$planned[ $term->term_id ] = true;
			$codes[ $term->term_id ]   = [ $term, $shop, $line ];
		}
		// A bookshop registered twice under different names may share the codes after the swap.
		// Swapping it would make the importer pick the other term, so leave it to a human.
		$collision = [];
		$groups    = [];
		foreach ( $codes as $term_id => list( $term, $line, $shop ) ) {
			$groups[ implode( '/', [ get_term_meta( $term_id, 'wholesaler', true ), $line, $shop ] ) ][] = $term_id;
		}
		foreach ( $groups as $key => $term_ids ) {
			if ( 2 > count( $term_ids ) ) {
				continue;
			}
			foreach ( $term_ids as $term_id ) {
				unset( $planned[ $term_id ] );
				$collision[] = sprintf( '%s [%d] %s', $codes[ $term_id ][0]->name, $term_id, $key );
			}
		}
		$fixed = 0;
		foreach ( array_keys( $planned ) as $term_id ) {
			list( $term, $line, $shop ) = $codes[ $term_id ];
			if ( ! $dry_run ) {
				update_term_meta( $term_id, 'line_code', $line );
				update_term_meta( $term_id, 'shop_code', $shop );
			}
			// translators: %1$s is shop name, %2$s is line code, %3$s is shop code.
			\WP_CLI::line( sprintf( __( '%1$s: 番線 %2$s ／ 書店コード %3$s', 'hanmoto' ), $term->name, $line, $shop ) );
			++$fixed;
		}
		foreach ( $ambiguous as $line ) {
			\WP_CLI::warning( __( '番線と書店コードを判定できません。この書店の注文はインポートできないので、手動でコードを直してください: ', 'hanmoto' ) . $line );
		}
		foreach ( $collision as $line ) {
			\WP_CLI::warning( __( '同じ書店が別名で二重登録されています。統合しないとインポート時に注文が二重登録されるので、統合してから再実行してください: ', 'hanmoto' ) . $line );
		}
		$result = sprintf(
			// translators: %1$d is fixed, %2$d is already valid, %3$d is empty, %4$d is ambiguous, %5$d is collision.
			__( '入れ替え %1$d件 ／ 既に正しい %2$d件 ／ コード未設定 %3$d件 ／ 要確認 %4$d件 ／ コード重複 %5$d件', 'hanmoto' ),
			$fixed,
			$valid,
			$empty,
			count( $ambiguous ),
			count( $collision )
		);
		if ( $dry_run ) {
			\WP_CLI::success( __( '[テスト実行] ', 'hanmoto' ) . $result );
		} else {
			\WP_CLI::success( $result );
		}
	}

	/**
	 * Is the code a 番線 like 12A34?
	 *
	 * @param string $code Code to check.
	 * @return bool
	 */
	private function is_line_code( $code ) {
		return '' !== $code && (bool) preg_match( '/[A-Za-zＡ-Ｚａ-ｚ]/u', $code );
	}

	/**
	 * Is the code a 書店コード like 123456?
	 *
	 * @param string $code Code to check.
	 * @return bool
	 */
	private function is_shop_code( $code ) {
		return '' !== $code && (bool) preg_match( '/\A[0-9０-９]+\z/u', $code );
	}
}
