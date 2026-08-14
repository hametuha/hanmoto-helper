<?php

namespace Hametuha\HanmotoHelper\UI;


use Hametuha\HanmotoHelper\Models\ModelOrder;
use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\BookSelector;

/**
 * CSV importer.
 *
 * @package hanmoto
 */
class CsvImporter extends Singleton {

	use BookSelector;

	protected $slug = 'hanmoto_import';

	/**
	 * @var array Bookshops resolved while importing.
	 */
	protected $shop_cache = [];

	/**
	 * @var array Books resolved while importing.
	 */
	protected $book_cache = [];

	/**
	 * Columns of 書店注文総合シート.
	 *
	 * The 5th column「取次書店コード」is the numeric shop code and
	 * the 6th column「作業コード」is the alphanumeric line code(番線),
	 * so they are the opposite order of line_code and shop_code.
	 */
	const GENERAL_COLUMNS = [
		'no',
		'date',
		'publisher',
		'wholesaler',
		'shop_code',
		'line_code',
		'shop_name',
		'isbn',
		'book_title',
		'price',
		'amount',
		'subtotal',
		'in_charge',
		'note',
		'source',
	];

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		// Register menu.
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		// Add Ajax action.
		add_action( 'wp_ajax_hanmoto_import_csv', [ $this, 'import_handler' ] );
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	protected function title() {
		return __( '在庫一括処理', 'hanmoto' );
	}

	/**
	 * Capability.
	 *
	 * @return string
	 */
	protected function cap() {
		return apply_filters( 'hanmoto_import_cap', 'edit_others_posts' );
	}

	/**
	 * Register menu page.
	 *
	 * @return void
	 */
	public function admin_menu() {
		$title = $this->title();
		add_submenu_page( 'tools.php', $title, $title, $this->cap(), $this->slug, [ $this, 'render' ] );
	}

	/**
	 * Get available import types.
	 *
	 * @return array
	 */
	protected function get_types() {
		return [
			'general' => __( '書店注文総合シート', 'hanmoto' ),
		];
	}

	/**
	 * Transient key to keep the result of last import.
	 *
	 * @return string
	 */
	protected function result_key() {
		return 'hanmoto_import_result_' . get_current_user_id();
	}

	/**
	 * Render Menu page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->title() ); ?></h1>
			<?php
			$msg = filter_input( INPUT_GET, 'msg' );
			if ( $msg ) {
				printf(
					'<div class="notice %s"><p>%s</p></div>',
					( false !== strpos( $msg, 'Error' ) ? 'notice-error' : 'notice-success' ),
					esc_html( $msg )
				);
			}
			$this->render_last_result();
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php?action=hanmoto_import_csv' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'hanmoto_import', '_hanmotouploadnonce' ); ?>
				<fieldset>
					<legend><?php esc_html_e( 'CSVをアップロード', 'hanmoto' ); ?></legend>
					<p>
						<label>
							<?php esc_html_e( 'CSVファイル', 'hanmoto' ); ?><br />
							<input type="file" name="csv" accept="text/csv,.csv" required />
						</label>
					</p>
					<p>
						<label>
							<?php esc_html_e( 'タイプ', 'hanmoto' ); ?><br />
							<select name="type">
								<?php
								foreach ( $this->get_types() as $value => $label ) {
									printf( '<option value="%s">%s</option>', esc_attr( $value ), esc_html( $label ) );
								}
								?>
							</select>
						</label>
					</p>
					<p>
						<label>
							<input type="checkbox" name="dry_run" value="1" checked />
							<?php esc_html_e( 'テスト実行（登録せずに結果だけ確認する）', 'hanmoto' ); ?>
						</label>
					</p>
				</fieldset>
				<p class="description">
					<?php
					printf(
						/* translators: %s is comma separated column names. */
						esc_html__( '「書店注文総合シート」の列順: %s（1行目はヘッダーとして読み飛ばします）', 'hanmoto' ),
						esc_html( implode( ', ', self::GENERAL_COLUMNS ) )
					);
					?>
					<br />
					<?php esc_html_e( '受注日・書店・書籍・冊数が一致する注文が既にある行はスキップするので、繰り返し実行しても二重登録されません。', 'hanmoto' ); ?>
				</p>
				<?php submit_button( __( 'インポート実行', 'hanmoto' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the detail of last import.
	 *
	 * @return void
	 */
	protected function render_last_result() {
		$result = get_transient( $this->result_key() );
		if ( ! $result ) {
			return;
		}
		delete_transient( $this->result_key() );
		if ( empty( $result['errors'] ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( '取り込めなかった行', 'hanmoto' ); ?></strong></p>
			<ol>
				<?php foreach ( $result['errors'] as $error ) : ?>
					<li>
						<?php
						printf(
							/* translators: %1$d is line number, %2$s is reason. */
							esc_html__( '%1$d行目: %2$s', 'hanmoto' ),
							(int) $error['line'],
							esc_html( $error['message'] )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}

	/**
	 * Redirect back to the import screen and stop.
	 *
	 * @param string $message Message to display.
	 * @param array  $errors  Error detail of each line.
	 * @return void
	 */
	protected function redirect_back( $message, $errors = [] ) {
		set_transient( $this->result_key(), [ 'errors' => $errors ], 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( sprintf( 'tools.php?page=%s&msg=%s', $this->slug, rawurlencode( $message ) ) ) );
		exit;
	}

	/**
	 * Handle Ajax.
	 *
	 * @return void
	 */
	public function import_handler() {
		set_time_limit( 0 );
		$file_object = null;
		$normalized  = '';
		try {
			if ( ! current_user_can( $this->cap() ) ) {
				throw new \Exception( __( '権限がありません。', 'hanmoto' ) );
			}
			check_admin_referer( 'hanmoto_import', '_hanmotouploadnonce' );
			$type  = filter_input( INPUT_POST, 'type' );
			$types = $this->get_types();
			if ( ! array_key_exists( $type, $types ) ) {
				throw new \Exception( __( '指定されたインポートタイプが無効です。', 'hanmoto' ) );
			}
			if ( ! isset( $_FILES['csv'] ) || UPLOAD_ERR_OK !== $_FILES['csv']['error'] ) {
				throw new \Exception( __( 'ファイルのアップロードにエラーがありました。', 'hanmoto' ) );
			}
			$dry_run = (bool) filter_input( INPUT_POST, 'dry_run' );
			// Convert the encoding of uploaded file to UTF-8 before parsing.
			$normalized  = $this->normalize_encoding( $_FILES['csv']['tmp_name'] );
			$file_object = new \SplFileObject( $normalized, 'r' );
			$file_object->setFlags( \SplFileObject::READ_CSV );
			switch ( $type ) {
				case 'general':
					$result = $this->import_general( $file_object, $dry_run );
					break;
				default:
					throw new \Exception( __( '指定されたインポートタイプが無効です。', 'hanmoto' ) );
			}
			// Free the file handle before removing the temporary file.
			$file_object = null;
			$this->remove_temp_file( $normalized );
			$this->redirect_back(
				sprintf(
					/* translators: %1$s is prefix, %2$d is imported, %3$d is skipped, %4$d is failure. */
					__( '%1$s%2$d件成功　%3$d件スキップ（登録済み）　%4$d件失敗', 'hanmoto' ),
					$dry_run ? __( '[テスト実行] ', 'hanmoto' ) : '',
					$result['imported'],
					$result['skipped'],
					count( $result['errors'] )
				),
				$result['errors']
			);
		} catch ( \Exception $e ) {
			$file_object = null;
			$this->remove_temp_file( $normalized );
			$this->redirect_back( sprintf( 'Error %s', $e->getMessage() ) );
		}
	}

	/**
	 * Import 書店注文総合シート.
	 *
	 * @param \SplFileObject $file_object CSV file.
	 * @param bool           $dry_run     If true, no post will be created.
	 * @throws \Exception If the taxonomy is not ready.
	 * @return array{imported:int, skipped:int, errors:array}
	 */
	protected function import_general( $file_object, $dry_run ) {
		// New bookshops are created under this term, so check it even in dry run.
		if ( ! get_term_by( 'slug', 'bookshop', 'supplier' ) ) {
			throw new \Exception( __( 'タクソノミー「書店」が登録されていません。', 'hanmoto' ) );
		}
		$this->shop_cache = [];
		$this->book_cache = [];
		// Fetch every registered order at once so that each row costs no query.
		$registered = $this->get_registered_order_keys();
		$columns    = count( self::GENERAL_COLUMNS );
		$counter    = 0;
		$imported   = 0;
		$skipped    = 0;
		$errors     = [];
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( $line = $file_object->fgetcsv() ) {
			++$counter;
			// Skip header row and blank lines.
			if ( 2 > $counter || $this->is_blank_line( $line ) ) {
				continue 1;
			}
			if ( $columns > count( $line ) ) {
				$errors[] = [
					'line'    => $counter,
					// translators: %1$d is expected columns, %2$d is actual columns.
					'message' => sprintf( __( '列数が足りません（%1$d列必要ですが%2$d列でした）', 'hanmoto' ), $columns, count( $line ) ),
				];
				continue 1;
			}
			$row       = array_combine( self::GENERAL_COLUMNS, array_slice( $line, 0, $columns ) );
			$book_shop = $this->resolve_bookshop( $row, ! $dry_run );
			if ( is_wp_error( $book_shop ) ) {
				$errors[] = [
					'line'    => $counter,
					// translators: %1$s is shop name, %2$s is reason.
					'message' => sprintf( __( '書店「%1$s」を登録できません: %2$s', 'hanmoto' ), $row['shop_name'], $book_shop->get_error_message() ),
				];
				continue 1;
			}
			if ( ! $book_shop && ! $dry_run ) {
				$errors[] = [
					'line'    => $counter,
					// translators: %s is shop name.
					'message' => sprintf( __( '書店「%s」が見つかりません', 'hanmoto' ), $row['shop_name'] ),
				];
				continue 1;
			}
			$book = $this->resolve_book( $row['isbn'] );
			if ( ! $book ) {
				$errors[] = [
					'line'    => $counter,
					// translators: %1$s is ISBN, %2$s is book title.
					'message' => sprintf( __( 'ISBN %1$s（%2$s）の書籍が見つかりません', 'hanmoto' ), $row['isbn'], $row['book_title'] ),
				];
				continue 1;
			}
			$date = $this->parse_date( $row['date'] );
			if ( ! $date ) {
				$errors[] = [
					'line'    => $counter,
					// translators: %s is date string in CSV.
					'message' => sprintf( __( '日付「%s」を解釈できません', 'hanmoto' ), $row['date'] ),
				];
				continue 1;
			}
			// Skip the row which is already registered.
			// A brand new bookshop( null in dry run ) can not have any order yet.
			$order_key = $book_shop ? $this->order_key( $book->ID, $book_shop->term_id, $date, $row['amount'] ) : '';
			if ( $order_key && isset( $registered[ $order_key ] ) ) {
				++$skipped;
				continue 1;
			}
			if ( $order_key ) {
				// Keep it so that the duplicated rows in a same CSV are also skipped.
				$registered[ $order_key ] = true;
			}
			if ( $dry_run ) {
				++$imported;
				continue 1;
			}
			// Insert post.
			$order_id = wp_insert_post( [
				'post_type'    => ModelOrder::post_type(),
				'post_date'    => $date,
				'post_status'  => 'publish',
				'post_title'   => '',
				'post_content' => '',
				'post_excerpt' => $row['note'],
				'post_parent'  => $book->ID,
			], true );
			if ( is_wp_error( $order_id ) ) {
				$errors[] = [
					'line'    => $counter,
					'message' => $order_id->get_error_message(),
				];
				continue 1;
			}
			// Save metadata.
			foreach ( [
				'_amount'       => (int) $row['amount'],
				'_in_charge_of' => $row['in_charge'],
				'_old_id'       => $row['no'],
			] as $key => $value ) {
				update_post_meta( $order_id, $key, $value );
			}
			// Assign bookshop.
			wp_set_object_terms( $order_id, $book_shop->term_id, $book_shop->taxonomy );
			// Assign source.
			if ( '' !== trim( (string) $row['source'] ) ) {
				wp_set_object_terms( $order_id, [ $row['source'] ], 'source' );
			}
			++$imported;
		}
		return [
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		];
	}

	/**
	 * Get the key of every order already registered.
	 *
	 * The key is built from post/term IDs instead of ISBN and bookshop codes,
	 * because they are resolved to the same IDs while importing.
	 *
	 * @return array Keys of registered orders.
	 */
	protected function get_registered_order_keys() {
		global $wpdb;
		$query = <<<SQL
			SELECT p.post_parent AS book_id, tt.term_id AS shop_id, LEFT( p.post_date, 10 ) AS order_date, pm.meta_value AS amount
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS pm
				ON pm.post_id = p.ID AND pm.meta_key = '_amount'
			INNER JOIN {$wpdb->term_relationships} AS tr
				ON tr.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} AS tt
				ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'supplier'
			WHERE p.post_type = %s
SQL;
		$keys  = [];
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $wpdb->get_results( $wpdb->prepare( $query, ModelOrder::post_type() ) ) as $order ) {
			$keys[ $this->order_key( $order->book_id, $order->shop_id, $order->order_date, $order->amount ) ] = true;
		}
		return $keys;
	}

	/**
	 * Build the key which identifies an order.
	 *
	 * @param int    $book_id Post ID of book.
	 * @param int    $shop_id Term ID of bookshop.
	 * @param string $date    Date string.
	 * @param mixed  $amount  Amount of books.
	 * @return string
	 */
	protected function order_key( $book_id, $shop_id, $date, $amount ) {
		return implode( '/', [ (int) $book_id, (int) $shop_id, substr( (string) $date, 0, 10 ), (int) $amount ] );
	}

	/**
	 * Get bookshop of the row with cache.
	 *
	 * Bookshops repeat a lot in a CSV, so the result is kept in memory.
	 *
	 * @param array $row    Parsed CSV row.
	 * @param bool  $create Create the bookshop if not exists.
	 * @return \WP_Term|\WP_Error|null
	 */
	protected function resolve_bookshop( $row, $create ) {
		// The bookshop is looked up by these codes, so the name is not a part of the key.
		$key = implode( '/', [ trim( $row['wholesaler'] ), str_replace( '-', '', trim( $row['line_code'] ) ), trim( $row['shop_code'] ) ] );
		if ( ! array_key_exists( $key, $this->shop_cache ) ) {
			$this->shop_cache[ $key ] = $this->find_bookshop( $row, $create );
		}
		return $this->shop_cache[ $key ];
	}

	/**
	 * Find bookshop of the row.
	 *
	 * @param array $row    Parsed CSV row.
	 * @param bool  $create Create the bookshop if not exists.
	 * @return \WP_Term|\WP_Error|null
	 */
	protected function find_bookshop( $row, $create ) {
		$found = $this->get_bookshop( $row['shop_name'], $row['wholesaler'], $row['line_code'], $row['shop_code'], false );
		if ( $found ) {
			return $found;
		}
		// The codes do not match any bookshop, but the same name may be already taken.
		// Detect it here so that the dry run reports the same failure as the actual import.
		$duplicated = $this->get_bookshop_by_name( $row['shop_name'] );
		if ( $duplicated ) {
			return new \WP_Error(
				'bookshop_code_mismatch',
				sprintf(
					// translators: %1$s is codes in database, %2$s is codes in CSV.
					__( '同名の書店が登録済みですが、コードが一致しません。登録済み: %1$s ／ CSV: %2$s', 'hanmoto' ),
					implode( '/', [
						get_term_meta( $duplicated->term_id, 'wholesaler', true ),
						get_term_meta( $duplicated->term_id, 'line_code', true ),
						get_term_meta( $duplicated->term_id, 'shop_code', true ),
					] ),
					implode( '/', [ trim( $row['wholesaler'] ), trim( $row['line_code'] ), trim( $row['shop_code'] ) ] )
				)
			);
		}
		if ( ! $create ) {
			return null;
		}
		return $this->get_bookshop( $row['shop_name'], $row['wholesaler'], $row['line_code'], $row['shop_code'], true );
	}

	/**
	 * Get bookshop by its name.
	 *
	 * @param string $name Name of bookshop.
	 * @return \WP_Term|null
	 */
	protected function get_bookshop_by_name( $name ) {
		$parent = get_term_by( 'slug', 'bookshop', 'supplier' );
		if ( ! $parent ) {
			return null;
		}
		$terms = get_terms( [
			'taxonomy'   => 'supplier',
			'hide_empty' => false,
			'name'       => trim( $name ),
			'parent'     => $parent->term_id,
			'number'     => 1,
		] );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}
		return $terms[0];
	}

	/**
	 * Get book by ISBN with cache.
	 *
	 * @param string $isbn ISBN of book.
	 * @return \WP_Post|null
	 */
	protected function resolve_book( $isbn ) {
		$key = preg_replace( '/[^a-zA-Z0-9]/u', '', trim( $isbn ) );
		if ( ! array_key_exists( $key, $this->book_cache ) ) {
			$this->book_cache[ $key ] = $this->get_book_by_isbn( $isbn );
		}
		return $this->book_cache[ $key ];
	}

	/**
	 * Detect if the CSV line has no content.
	 *
	 * @param array $line Parsed CSV line.
	 * @return bool
	 */
	protected function is_blank_line( $line ) {
		if ( empty( $line ) ) {
			return true;
		}
		foreach ( $line as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Parse date string in CSV.
	 *
	 * @param string $date Date string.
	 * @return string Empty string if invalid.
	 */
	protected function parse_date( $date ) {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return '';
		}
		// Normalize Japanese style date(2024年1月5日) and full width numbers.
		$date      = mb_convert_kana( $date, 'a' );
		$date      = preg_replace( '/年|月/u', '/', $date );
		$date      = preg_replace( '/日\z/u', '', $date );
		$date      = str_replace( '.', '/', $date );
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return '';
		}
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Convert the encoding of CSV to UTF-8.
	 *
	 * Bookstore sheets are often exported as Shift_JIS(CP932) or UTF-8 with BOM.
	 *
	 * @param string $path Path of uploaded file.
	 * @throws \Exception If the file is unreadable.
	 * @return string Path of normalized file.
	 */
	protected function normalize_encoding( $path ) {
		$content = file_get_contents( $path );
		if ( false === $content ) {
			throw new \Exception( __( 'ファイルを読み込めませんでした。', 'hanmoto' ) );
		}
		// Remove UTF-8 BOM.
		$content  = preg_replace( '/\A\xEF\xBB\xBF/', '', $content );
		$encoding = mb_detect_encoding( $content, [ 'UTF-8', 'SJIS-win', 'EUC-JP' ], true );
		if ( ! $encoding ) {
			throw new \Exception( __( 'ファイルの文字コードを判定できませんでした。UTF-8で保存し直してください。', 'hanmoto' ) );
		}
		if ( 'UTF-8' !== $encoding ) {
			$content = mb_convert_encoding( $content, 'UTF-8', $encoding );
		}
		$tmp = wp_tempnam( 'hanmoto-import' );
		if ( ! $tmp || false === file_put_contents( $tmp, $content ) ) {
			throw new \Exception( __( '一時ファイルを作成できませんでした。', 'hanmoto' ) );
		}
		return $tmp;
	}

	/**
	 * Remove temporary file if exists.
	 *
	 * @param string $path Path of temporary file.
	 * @return void
	 */
	protected function remove_temp_file( $path ) {
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
