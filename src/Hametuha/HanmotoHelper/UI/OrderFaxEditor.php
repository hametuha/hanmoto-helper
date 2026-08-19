<?php

namespace Hametuha\HanmotoHelper\UI;

use Hametuha\HanmotoHelper\Models\ModelOrderFax;
use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * Edit screen of a fax(FAX送付分).
 *
 * 紐付いた注文の一覧・追加・削除をここで扱う。
 * 追加と削除はRESTで即時に反映されるので「更新」ボタンとは無関係に動く。
 * 一覧の行はJSが描くので、追加・削除後の再描画と初期表示で処理が1つになる。
 *
 * @package hanmoto
 */
class OrderFaxEditor extends Singleton {

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post_' . ModelOrderFax::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
		add_action( 'admin_head', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Is the current screen the editor of a fax?
	 *
	 * @return bool
	 */
	protected function is_editor() {
		$screen = get_current_screen();
		return $screen && 'post' === $screen->base && ModelOrderFax::POST_TYPE === $screen->post_type;
	}

	/**
	 * Enqueue assets and print styles.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_editor() ) {
			return;
		}
		$post_id = (int) filter_input( INPUT_GET, 'post' );
		$model   = ModelOrderFax::get_instance();
		wp_enqueue_script( 'hanmoto-order-fax-editor' );
		wp_localize_script( 'hanmoto-order-fax-editor', 'HanmotoOrderFax', [
			'faxId'  => $post_id,
			'orders' => $model->get_order_summaries( $post_id ),
			'stats'  => $model->get_stats( $post_id ),
		] );
		?>
		<style>
			.hanmoto-fax-orders td.column-amount,
			.hanmoto-fax-orders th.column-amount {
				text-align: right;
				width: 60px;
			}
			.hanmoto-fax-orders th.column-action {
				width: 80px;
			}
			.hanmoto-fax-orders tr.is-not-printable td {
				color: #a7aaad;
			}
			.hanmoto-fax-badge {
				display: inline-block;
				margin-left: 6px;
				padding: 0 6px;
				border-radius: 9px;
				background: #f0b849;
				color: #1d2327;
				font-size: 11px;
				line-height: 18px;
			}
			.hanmoto-fax-add {
				margin-top: 1.5em;
				padding-top: 1em;
				border-top: 1px solid #dcdcde;
			}
			.hanmoto-fax-results {
				max-height: 320px;
				overflow-y: auto;
			}
			.hanmoto-fax-results label {
				display: block;
				padding: 4px 0;
			}
			.hanmoto-fax-spinner {
				float: none;
				margin: 0 4px;
				visibility: hidden;
			}
			.hanmoto-fax-spinner.is-active {
				visibility: visible;
			}
		</style>
		<?php
	}

	/**
	 * Register meta box.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function add_meta_boxes( $post_type ) {
		if ( ModelOrderFax::POST_TYPE !== $post_type ) {
			return;
		}
		add_meta_box( 'order-fax-meta', __( 'FAX送付情報', 'hanmoto' ), [ $this, 'render_meta_box' ], $post_type, 'normal', 'high' );
	}

	/**
	 * Render meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'update_order_fax', '_hanmotofaxnonce', false );
		if ( 'publish' === $post->post_status ) :
			?>
			<div class="notice notice-success inline">
				<p>
					<strong><?php esc_html_e( '送付済み', 'hanmoto' ); ?></strong>
					<?php echo esc_html( mysql2date( __( 'Y年n月j日 H:i', 'hanmoto' ), $post->post_date ) ); ?>
					<?php esc_html_e( 'に公開されました。入っている注文は「送付済み」として扱われます。', 'hanmoto' ); ?>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php esc_html_e( 'この送付分はまだ下書きです。FAXを送ったら「公開」にしてください。公開すると、入っている注文が「送付済み」になります。', 'hanmoto' ); ?>
				</p>
			</div>
			<?php
		endif;
		?>
		<p>
			<label>
				<?php esc_html_e( '宛先', 'hanmoto' ); ?><br />
				<input class="regular-text" type="text" name="fax_to"
					value="<?php echo esc_attr( get_post_meta( $post->ID, ModelOrderFax::META_FAX_TO, true ) ); ?>"
					placeholder="<?php esc_attr_e( '例・八木書店', 'hanmoto' ); ?>" />
			</label>
			<span class="description"><?php esc_html_e( '短冊の見出しに「◯◯様」と入ります。', 'hanmoto' ); ?></span>
		</p>
		<div id="hanmoto-order-fax">
			<p>
				<a class="button button-primary" href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( '短冊を印刷', 'hanmoto' ); ?>
				</a>
				<span class="description hanmoto-fax-stats"></span>
			</p>
			<table class="widefat striped hanmoto-fax-orders">
				<thead>
				<tr>
					<th><?php esc_html_e( '注文', 'hanmoto' ); ?></th>
					<th><?php esc_html_e( '受注日', 'hanmoto' ); ?></th>
					<th><?php esc_html_e( '書店', 'hanmoto' ); ?></th>
					<th><?php esc_html_e( '商品', 'hanmoto' ); ?></th>
					<th class="column-amount"><?php esc_html_e( '冊数', 'hanmoto' ); ?></th>
					<th class="column-action"><?php esc_html_e( '操作', 'hanmoto' ); ?></th>
				</tr>
				</thead>
				<tbody></tbody>
			</table>
			<div class="hanmoto-fax-add">
				<h4><?php esc_html_e( '注文を追加', 'hanmoto' ); ?></h4>
				<p class="description">
					<?php esc_html_e( '書店名・書名・注文IDで検索できます。追加と削除はすぐに反映されるので、「更新」を押す必要はありません。', 'hanmoto' ); ?>
				</p>
				<p>
					<input type="search" class="regular-text hanmoto-fax-query"
						placeholder="<?php esc_attr_e( '例・ジュンク堂', 'hanmoto' ); ?>" />
					<button type="button" class="button hanmoto-fax-search"><?php esc_html_e( '検索', 'hanmoto' ); ?></button>
					<span class="spinner hanmoto-fax-spinner"></span>
				</p>
				<div class="hanmoto-fax-results"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save meta data.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_post( $post_id, $post ) {
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_hanmotofaxnonce' ), 'update_order_fax' ) ) {
			return;
		}
		update_post_meta( $post_id, ModelOrderFax::META_FAX_TO, trim( (string) filter_input( INPUT_POST, 'fax_to' ) ) );
	}
}
