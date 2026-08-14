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
	 * Columns of 書店注文総合シート.
	 */
	const GENERAL_COLUMNS = [
		'no',
		'date',
		'publisher',
		'wholesaler',
		'line_code',
		'shop_code',
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
					/* translators: %1$s is prefix, %2$d is imported, %3$d is failure. */
					__( '%1$s%2$d件成功　%3$d件失敗', 'hanmoto' ),
					$dry_run ? __( '[テスト実行] ', 'hanmoto' ) : '',
					$result['imported'],
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
	 * @return array{imported:int, errors:array}
	 */
	protected function import_general( $file_object, $dry_run ) {
		// New bookshops are created under this term, so check it even in dry run.
		if ( ! get_term_by( 'slug', 'bookshop', 'supplier' ) ) {
			throw new \Exception( __( 'タクソノミー「書店」が登録されていません。', 'hanmoto' ) );
		}
		$columns  = count( self::GENERAL_COLUMNS );
		$counter  = 0;
		$imported = 0;
		$errors   = [];
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
			$book_shop = $this->get_bookshop( $row['shop_name'], $row['wholesaler'], $row['line_code'], $row['shop_code'], ! $dry_run );
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
			$book = $this->get_book_by_isbn( $row['isbn'] );
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
			'errors'   => $errors,
		];
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
