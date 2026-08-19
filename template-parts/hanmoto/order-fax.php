<?php
/**
 * 注文短冊（FAX送付分）の印刷用テンプレート
 *
 * @package hanmoto
 */

use Hametuha\HanmotoHelper\Models\ModelOrderFax;

if ( ! current_user_can( 'edit_others_posts' ) ) {
	wp_die( esc_html__( '閲覧権限がありません。', 'hanmoto' ) );
}
the_post();
$model = ModelOrderFax::get_instance();
$slips = $model->get_slips( get_the_ID() );
if ( empty( $slips ) ) {
	wp_die( esc_html__( '短冊にできる注文がありません。', 'hanmoto' ) );
}
$fax_to     = (string) get_post_meta( get_the_ID(), ModelOrderFax::META_FAX_TO, true );
$publishers = $model->get_publishers( $slips );
$total      = count( $slips );
$books      = $model->count_books( $slips );
$pages      = (int) ceil( $total / ModelOrderFax::PER_PAGE );
$excluded   = count( get_post_meta( get_the_ID(), ModelOrderFax::META_ORDER ) ) - $total;
// 6件ずつページに分ける。
$table = array_chunk( $slips, ModelOrderFax::PER_PAGE );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<title><?php the_title(); ?></title>
	<style>
		@page {
			size: A4 portrait;
		}
		* {
			box-sizing: border-box;
		}
		body {
			font-family: "Noto Sans JP", sans-serif;
		}
		body, html { margin: 0; }
		section {
			page-break-after: always;
		}
		table {
			table-layout: auto;
			width: 100%;
			border-collapse: collapse;
		}
		th, td {
			border-top: 1mm solid black;
			border-bottom: 1mm solid black;
			height: 40mm;
			padding: 4mm;
		}
		caption {
			padding-bottom: 5mm;
		}
		.cell-store {
			width: 30%;
			text-align: left;
		}
		.cell-book {
			width: 35%;
		}
		.cell-amount {
			width: 15%;
			border-right: .25mm solid black;
			border-left: .25mm solid black;
		}
		.cell-order {
			text-align: left;
			border-right: .25mm solid black;
			line-height: 1.4;
			font-size: 10pt;
		}
		.cell-order small {
			font-size: 7pt;
			font-weight: bold;
		}
		.title {
			font-size: 12pt;
			font-weight: bold;
		}
		.author {
			font-size: 12pt;
			font-weight: bold;
		}
		.isbn {
			font-size: 9pt;
		}
		.amount-title {
			display: block;
			height: 5mm;
		}
		.amount {
			display: block;
			text-align: center;
			height: 25mm;
			line-height: 25mm;
			font-size: 20pt;
		}
		.amount-footer {
			display: block;
			height: 5mm;
			text-align: right;
		}
		.agent {
			font-size: 12pt;
		}
		.order-note {
			font-weight: bold;
		}
		.acode {
			font-size: 12pt;
			margin-left: 1em;
			font-weight: normal;
		}
		.shop-name {
			font-size: 14pt;
			display: block;
			margin: 3mm 0 0;
		}
		.shop-name.is-long {
			font-size: 12pt;
		}
		.scode {
			font-weight: normal;
			font-size: 12pt;
			margin-left: 3mm;
		}
		.scode-title {
			font-weight: normal;
			font-size: 9pt;
		}
		.toolbar {
			padding: 4mm;
			background: #f0f0f1;
			font-size: 10pt;
		}
		.toolbar p {
			margin: 0 0 1em;
		}
		@media print {
			.toolbar {
				display: none;
			}
		}
	</style>
</head>
<body>
<div class="toolbar">
	<p>
		<button type="button" onclick="window.print();"><?php esc_html_e( '印刷する', 'hanmoto' ); ?></button>
		<a href="<?php echo esc_url( (string) get_edit_post_link( get_the_ID() ) ); ?>"><?php esc_html_e( '編集画面に戻る', 'hanmoto' ); ?></a>
	</p>
	<?php if ( 'publish' !== get_post_status() ) : ?>
		<p>
			<strong>
				<?php esc_html_e( '※ この送付分はまだ「下書き」です。FAXを送ったら編集画面で「公開」にしてください。公開すると注文が「送付済み」になります。', 'hanmoto' ); ?>
			</strong>
		</p>
	<?php endif; ?>
	<?php if ( 0 < $excluded ) : ?>
		<p>
			<strong>
				<?php
				printf(
					// translators: %d is the number of orders.
					esc_html__( '※ 返品・冊数なしの%d件は短冊から除外しています。', 'hanmoto' ),
					(int) $excluded
				);
				?>
			</strong>
		</p>
	<?php endif; ?>
</div>
<?php foreach ( $table as $index => $orders ) : ?>
<section>
	<table>
		<caption>
			<?php
			if ( $fax_to ) {
				// translators: %s is the addressee.
				printf( esc_html__( '%s様　', 'hanmoto' ), esc_html( $fax_to ) );
			}
			if ( $publishers ) {
				// translators: %s is the publisher.
				printf( esc_html__( '%s受注分', 'hanmoto' ), esc_html( $publishers ) );
			}
			printf(
				'<strong>%1$d</strong>%2$s<strong>%3$d</strong>%4$s<small>（%5$d/%6$d ページ）</small>',
				(int) $total,
				esc_html__( '件', 'hanmoto' ),
				(int) $books,
				esc_html__( '冊', 'hanmoto' ),
				(int) $index + 1,
				(int) $pages
			);
			?>
		</caption>
		<tbody>
		<?php foreach ( $orders as $slip ) : ?>
			<tr>
				<th class="cell-store">
					<span class="agent"><?php echo esc_html( $slip['wholesaler'] ); ?></span>
					<code class="acode"><?php echo esc_html( $slip['line_code'] ); ?></code><br />
					<span class="shop-name <?php echo esc_attr( $model->is_long_shop_name( $slip['shop_name'] ) ? 'is-long' : '' ); ?>"><?php echo esc_html( $slip['shop_name'] ); ?></span>
					<span class="scode-title"><?php esc_html_e( '取次書店コード', 'hanmoto' ); ?></span>
					<code class="scode"><?php echo esc_html( $slip['shop_code'] ); ?></code>
				</th>
				<td class="cell-amount">
					<span class="amount-title"><?php esc_html_e( '冊数', 'hanmoto' ); ?></span>
					<code class="amount"><?php echo esc_html( $slip['amount'] ); ?></code>
					<span class="amount-footer"><?php esc_html_e( '冊', 'hanmoto' ); ?></span>
				</td>
				<td class="cell-order">
					<small><?php esc_html_e( '【注文日】', 'hanmoto' ); ?></small>
					<span><?php echo esc_html( mysql2date( __( 'Y/n/j', 'hanmoto' ), $slip['order']->post_date ) ); ?></span>
					<br />
					（<?php echo esc_html( $model->get_order_number( $slip ) ); ?>）
					<br />
					<small><?php esc_html_e( '【ご担当者様】', 'hanmoto' ); ?></small><br />
					<?php echo esc_html( $slip['in_charge'] ? $slip['in_charge'] : __( '記載なし', 'hanmoto' ) ); ?>
					<?php if ( $slip['note'] ) : ?>
						<br />
						<span class="order-note">※<?php echo esc_html( $slip['note'] ); ?></span>
					<?php endif; ?>
				</td>
				<td class="cell-book">
					<span class="title"><?php echo esc_html( $slip['title'] ); ?></span><br />
					<span class="author"><?php echo esc_html( $slip['authors'] ? $slip['authors'] : '---' ); ?></span><br />
					<span class="publisher"><?php echo esc_html( $slip['publisher'] ); ?></span>
					<?php if ( $slip['isbn'] ) : ?>
						<code class="isbn">ISBN<?php echo esc_html( $slip['isbn'] ); ?></code>
					<?php endif; ?>
					<br />
					<span class="price">
						<?php
						printf(
							// translators: %s is price.
							esc_html__( '本体¥%s+税', 'hanmoto' ),
							esc_html( number_format( $slip['price'] ) )
						);
						?>
					</span>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</section>
<?php endforeach; ?>
</body>
</html>
