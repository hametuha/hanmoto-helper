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
			'direct'  => __( '伝票直接入力', 'hanmoto' ),
		];
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
					'<div class="%s"><p>%s</p></div>',
					( false !== strpos( $msg, 'Error' ) ? 'error' : 'updated' ),
					esc_html( $msg )
				);
			}
			?>
			<form method="post" action="<?php echo admin_url( 'admin-ajax.php?action=hanmoto_import_csv' ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'hanmoto_impoort', '_hanmotouploadnonce' ); ?>
				<fieldset>
					<legend><?php esc_html_e( 'CSVをアップロード', 'hanmoto' ); ?></legend>
					<p>
						<label>
							<?php esc_html_e( 'CSVファイル', 'hanmoto' ) ?><br />
							<input type="file" name="csv" accept="text/csv" />
						</label>
					</p>
					<p>
						<label>
							<?php esc_html_e( 'タイプ', 'hanmoto' ) ?><br />
							<select name="type">
								<?php
								foreach ( $this->get_types() as $value => $label ) {
									printf( '<option value="%s">%s</option>', esc_attr( $value ), esc_html( $label ) );
								}
								?>
							</select>
						</label>
					</p>
				</fieldset>
				<?php submit_button( __( 'インポート実行', 'hanmoto' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle Ajax.
	 *
	 * @return void
	 */
	public function import_handler() {
		set_time_limit( 0 );
		try {
			if ( ! current_user_can( $this->cap() ) ) {
				throw new \Exception( __( '権限がありません。', 'hanmoto' ) );
			}
			$type  = filter_input( INPUT_POST, 'type' );
			$types = $this->get_types();
			if ( ! array_key_exists( $type, $types ) ) {
				throw new \Exception( __( '指定されたインポートタイプが無効です。', 'hanmoto' ) );
			}
			if ( ! isset( $_FILES['csv'] ) || UPLOAD_ERR_OK !== $_FILES['csv']['error'] ) {
				throw new \Exception( __( 'ファイルのアップロードにエラーがありました。', 'hanmoto' ) );
			}
			$tmp_path = $_FILES['csv']['tmp_name'];
			$file_object = new \SplFileObject( $tmp_path, 'r' );
			$file_object->setFlags( \SplFileObject::READ_CSV );
			$counter = 0;
			$imported = 0;
			$failure  = 0;
			switch ( $type ) {
				case 'general':
					// Order sheet.

					while ( $line = $file_object->fgetcsv() ) {
						$counter++;
						if ( 2 > $counter || empty( $line ) ) {
							continue 1;
						}
						list( $no, $date, $publisher, $wholesaler, $line_code, $shop_code, $shop_name, $isbn, $book_title, $price, $amount, $subtotal, $in_charge, $note, $source ) = $line;
						$book_shop = $this->get_bookshop( $shop_name, $wholesaler, $line_code, $shop_code, true );
						if ( is_wp_error( $book_shop ) ) {
							$failure++;
							continue 1;
						}
						$book = $this->get_book_by_isbn( $isbn );
						if ( ! $book ) {
							$failure++;
							continue 1;
						}
						$date = $this->ensure_date( $date );
						// Insert post.
						$order_id = wp_insert_post( [
							'post_type'    => ModelOrder::post_type(),
							'post_date'    => $date,
							'post_status'  => 'publish',
							'post_title'   => '',
							'post_content' => '',
							'post_excerpt' => $note,
							'post_parent'  => $book->ID,
						] );
						if ( ! $order_id ) {
							$failure++;
							continue 1;
						}
						// Save metadata.
						foreach ( [
							'_amount'       => $amount,
							'_in_charge_of' => $in_charge,
							'_old_id'       => $no,
						] as $key => $value ) {
							update_post_meta( $order_id, $key, $value );
						}
						// Assign bookshop.
						wp_set_object_terms( $order_id, $book_shop->term_id, $book_shop->taxonomy );
						// Assign source.
						wp_set_object_terms( $order_id, [ $source ], 'source' );
						$imported++;
					}
					wp_redirect( admin_url( sprintf( 'tools.php?page=%s&msg=%s', $this->slug, rawurlencode( sprintf( __( '%d件インポート　%d件失敗', 'hanmoto' ), $imported, $failure ) ) ) ) );
					break;
				case 'direct':
					// Direct input files.
					break;
			}
			throw new \Exception( __( '不明なエラーです', 'hanmoto' ) );
		} catch ( \Exception $e ) {
			wp_redirect( admin_url( sprintf( 'tools.php?page=%s&msg=Error+%s', $this->slug, rawurlencode( $e->getMessage() ) ) ) );
		}
	}
}
