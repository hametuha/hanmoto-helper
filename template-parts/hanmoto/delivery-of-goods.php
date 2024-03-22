<?php
if ( ! current_user_can( 'edit_others_posts' ) ) {
	wp_die( __( '閲覧権限がありません。', 'hanmoto' ) );
}
the_post();
$inventory_ids = get_post_meta( get_the_ID(), '_inventory' );
$inventories   = \Hametuha\HanmotoHelper\Models\ModelDelivery::get_instance()->get_inventories( $inventory_ids );
if ( empty( $inventories ) ) {
	wp_die( __( '該当する在庫情報がありません。', 'hanmoto' ) );
}
// Prefix.
$prefix       = sprintf( 'H%08d-', get_the_ID() );
$supplier_ids = [];
$type_ids     = [];
foreach ( $inventories->posts as $inventory ) {
	$suppliers = get_the_terms( $inventory, 'supplier' );
	foreach ( $suppliers as $term ) {
		if ( ! in_array( $term->term_id, $supplier_ids, true ) ) {
			$supplier_ids[] = $term->term_id;
		}
	}
	$types = get_the_terms( $inventory, 'transaction_type' );
	foreach ( $types as $term ) {
		if ( ! in_array( $term->term_id, $type_ids, true ) ) {
			$type_ids[] = $term->term_id;
		}
	}
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<title><?php the_title(); ?></title>
	<style>
		@page {
			size: A4 portrait;
			margin: 5mm;
		}
		section {
			min-height: 50vw;
		}
		@media only print {
			section {
				min-height: 142mm;
			}
		}
		section.even {
			page-break-after: always;
		}
		header {
			display: flex;
			justify-content: space-between;
		}
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
			font-size: 10pt;
		}
		section {
			padding: 10mm 0;
		}
		section.even {
			border-top: 0.5mm dashed #000;
		}
		h1 {
			text-align: center;
			border-bottom: 0.25mm solid #000;
			padding-bottom: 10mm;
			margin-bottom: 10mm;
			font-size: 14pt;
		}
		header {
			border-bottom: 0.25mm solid #000;
			padding-bottom: 5mm;
		}
		.to {
			display: flex;
			justify-content: start;
		}
		.stamp {
			margin-left: 10mm;
			border: 2mm solid #ccc;
			color: #ccc;
			width: 20mm;
			height: 20mm;
			line-height: 16mm;
			text-align: center;
			font-size: 14pt;
		}
		.to strong {
			font-size: 12pt;
		}
		.from th {
			text-align: right;
		}
		.from td {
			text-align: left;
		}
		.from th,
		.from td {
			padding: 0.25mm;
		}
		.order {
			table-layout: auto;
			width: 100%;
			border-collapse: collapse;
		}
		.order th,
		.order td {
			padding: 1.25mm;
			border-left: 0.25mm dotted #000;
			border-right: 0.25mm dotted #000;
		}
		.order thead th {
			border-bottom: 0.25mm solid #000;
		}
		.order tbody td {
			border-bottom: 0.25mm dotted #000;
			text-align: right;
		}
		.order tfoot th {
			border-top: 0.6mm double #000;
			border-bottom: 0.25mm solid #000;
		}
		.order tfoot td {
			text-align: right;
			border-bottom: 0.25mm solid #000;
		}
		.order-col-1 {
			width: 8em;
			font-family: monospace;
		}
		.order-col-3,
		.order-col-4,
		.order-col-5 {
			width: 6em;
			font-family: monospace;
		}
		.order-col-6 {
			width: 10em;
			font-family: monospace;
		}
		.order-col-2 {
			text-align: left !important;
		}
		.mono {
			font-family: monospace;
		}
		footer {
			margin-top: 1cm;
			display: flex;
			justify-content: end;
		}
		footer address {
			font-style: normal;
			font-size: 9pt;
		}
	</style>
</head>
<body>
<?php
$counter    = 1;
$issue_date = mysql2date( __( 'Y年m月d日', 'hanmoto' ), ( get_post_meta( get_the_ID(), '_issued_at', true ) ?: get_post()->post_date ) );
$vendor     = get_post_meta( get_the_ID(), '_issued_by', true ) ?: get_option( 'hanmoto_issued_by', get_bloginfo( 'name' ) );
$owner      = get_post_meta( get_the_ID(), '_issue_owner', true ) ?: get_option( 'hanmoto_issue_owner', '---' );
foreach ( $supplier_ids as $supplier_id ) {
	foreach ( $type_ids as $type_id ) {
		$supplier         = get_term_by( 'id', $supplier_id, 'supplier' );
		$transaction_type = get_term_by( 'id', $type_id, 'transaction_type' );
		$capture_at       = '';
		$rows             = [];
		$total_amount     = 0;
		$total_unit_price = 0;
		$total_subtotal   = 0;
		foreach ( $inventories->posts as $inventory ) {
			if ( ! has_term( $supplier->term_id, $supplier->taxonomy, $inventory ) ) {
				continue 1;
			}
			if ( ! has_term( $transaction_type->term_id, $transaction_type->taxonomy, $inventory ) ) {
				continue 1;
			}
			// This is the inventory.
			$id                = get_post_meta( $inventory->post_parent, 'hanmoto_isbn', true ) ?: '#' . $inventory->ID;
			$title             = get_the_title( $inventory->post_parent );
			$price             = get_post_meta( $inventory->ID, '_unit_price', true );
			$margin            = get_post_meta( $inventory->ID, '_margin', true );
			$amount            = get_post_meta( $inventory->ID, '_amount', true ) * -1;
			$subtotal          = round( $price * $amount / 100 * $margin );
			$total_amount     += $amount;
			$total_unit_price += $amount * $price;
			$total_subtotal   += $subtotal;
			$capture           = get_post_meta( $inventory->ID, '_capture_at', true );
			if ( $capture_at < $capture ) {
				$capture_at = $capture;
			}
			$rows[] = [ $id, $title, number_format( $amount ), number_format( $price ), $margin . '%', number_format( $subtotal ) ];
		}
		if ( empty( $rows ) ) {
			continue 1;
		}
		// Render contents.
		$issue_no = $prefix . sprintf( '%03d', $counter );
		foreach ( [
			'納品書（Ａ）',
			'納品書版元控（Ｂ）',
			'請求明細（Ｃ）',
			'請求明細版元控（Ｄ）',
		] as $index => $print_label ) :
			?>
			<section class="<?php echo ( ( $index + 1 ) % 2 === 0 ) ? 'even' : 'odd'; ?>">
				<h1><?php echo esc_html( $print_label ); ?></h1>
				<header>
					<div class="to">
						<p>
							<strong>
								<?php
								// translators: %s is a supplier.
								printf( esc_html__( '%s 御中', 'hanmoto' ), esc_html( $supplier->name ) );
								?>
							</strong>
							<br />
							<br />
							<?php
							echo nl2br( esc_html( get_term_meta( $supplier->term_id, 'address', true ) ) );
							$in_charge = get_term_meta( $supplier->term_id, 'in_charge', true );
							if ( $in_charge ) {
								echo '<br />';
								printf(
									// translators: %s is a person in charge.
									esc_html__( '%s 様', 'hanmoto' ),
									esc_html( $in_charge )
								);
							}
							?>
						</p>
						<?php if ( in_array( $index, [ 1, 3 ], true ) ) : ?>
							<div class="stamp">
								<?php esc_html_e( '印', 'hanmoto' ); ?>
							</div>
						<?php endif; ?>
					</div>
					<div class="from">
						<table>
							<tr>
								<th><?php esc_html_e( '発行', 'hanmoto' ); ?></th>
								<td><?php echo esc_html( $vendor ); ?></td>
							</tr>
							<tr>
								<th>No.</th>
								<td><?php echo esc_html( $issue_no ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( '注文種別', 'hanmoto' ); ?></th>
								<td><?php echo esc_html( $transaction_type->name ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( '発行日', 'hanmoto' ); ?></th>
								<td><?php echo $issue_date; ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( '請求〆日', 'hanmoto' ); ?></th>
								<td><?php echo mysql2date( __( 'Y年m月d日', 'hanmoto' ), $capture_at ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( '担当者', 'hanmoto' ); ?></th>
								<td><?php echo esc_html( $owner ); ?></td>
							</tr>
						</table>
					</div>
					<div class="address">
						<address>
							株式会社破滅派<br />
							〒104-0061<br />
							東京都中央区銀座1-3-3<br />
							G1ビル7F 1211<br />
							登録番号: T1010401087592
						</address>
					</div>
				</header>

				<table class="order">
					<thead>
					<tr>
						<th><?php esc_html_e( 'ISBN', 'hanmoto' ); ?></th>
						<th><?php esc_html_e( '署名', 'hanmoto' ); ?></th>
						<th><?php esc_html_e( '部数', 'hanmoto' ); ?></th>
						<th><?php esc_html_e( '本体価格', 'hanmoto' ); ?></th>
						<th><?php esc_html_e( '正味', 'hanmoto' ); ?></th>
						<th><?php esc_html_e( '正味金額', 'hanmoto' ); ?></th>
					</tr>
					</thead>
					<tfoot>
					<tr>
						<th>&nbsp;</th>
						<th>&nbsp;</th>
						<th><?php esc_html_e( '部数合計', 'hanmoto' ); ?></th>
						<th colspan="2"><?php esc_html_e( '本体価格合計', 'hanmoto' ); ?></th>
						<th><?php esc_html_e( '正味金額合計', 'hanmoto' ); ?></th>
					</tr>
					<tr class="mono">
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td><?php echo number_format( $total_amount ); ?></td>
						<td colspan="2"><?php echo number_format( $total_unit_price ); ?></td>
						<td><?php echo esc_html( number_format( $total_subtotal ) ); ?></td>
					</tr>
					</tfoot>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
					<tr>
						<?php foreach ( $row as $i => $cell ) : ?>
						<td class="order-col-<?php echo $i + 1; ?>"><?php echo esc_html( $cell ); ?></td>
						<?php endforeach; ?>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</section>
			<?php
		endforeach;
		$counter++;
	}
}
?>
</body>
</html>
