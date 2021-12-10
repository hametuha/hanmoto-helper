<?php
/**
 * Book card template.
 *
 * @package hanmoto
 * @var array $args Arguments.
 */

$book = $args['book'];
?>

<div class="hanmoto-book hanmoto-book-card">

	<?php if ( ! empty( $book['summary']['cover'] ) ) : ?>
	<div class="hanmoto-book-cover">
		<img class="hanmoto-book-image" src="<?php echo esc_url( $book['summary']['cover'] ); ?>" alt="<?php echo esc_attr( $book['summary']['title'] ); ?>" />
	</div>
	<?php else: ?>
	<?php do_action( 'hanmoto_no_image', $book ); ?>
	<?php endif; ?>
	<div class="hanmoto-book-detail">
		<p class="hanmoto-book-title"><?php echo esc_html( $book['summary']['title'] ); ?></p>
		<p class="hanmoto-book-author"><?php echo esc_html( $book['summary']['author'] ); ?></p>
		<dl class="hanmoto-book-properties">
			<dt><?php esc_html_e( '出版者', 'hanmoto' ) ?></dt>
			<dd><?php echo esc_html( $book['summary']['publisher'] ); ?></dd>
			<dt>ISBN</dt>
			<dd><?php echo esc_html( $book['summary']['isbn'] ); ?></dd>
			<dt><?php esc_html_e( 'ページ数', 'hanmoto' ) ?></dt>
			<dd><?php
				$page = $book['onix']['DescriptiveDetail']['Extent'][0]['ExtentValue'];
				echo esc_html( sprintf( _n( '%dページ', '%dページ', $page, 'hanmoto' ), $page ) );
			?></dd>
			<dt><?php esc_html_e( '本体価格', 'hanmoto' ) ?></dt>
			<dd>
				<?php
					$price = $book['onix']['ProductSupply']['SupplyDetail']['Price'][0]['PriceAmount'];
					$price_placeholder = apply_filters( 'hanmoto_price_placeholder', __( '%s円＋税', 'hanmoto' ) );
					echo esc_html( sprintf( $price_placeholder, number_format_i18n( $price ) ) );
				?>
			</dd>
			<dt><?php esc_html_e( '発売日', 'hanmoto' ) ?></dt>
			<dd><?php echo esc_html( hanmoto_publish_date( $book ) ); ?></dd>
		</dl>
		<nav class="hanmobo-book-actions">
			<ul class="hanmoto-book-actions-list">
				<?php
				foreach( hanmoto_actions( $book ) as $link ) :
					$rel = [ 'noopener', 'noreferrer' ];
					if ( $link['sponsored'] ) {
						$rel[] = 'sponsored';
					}
					?>
					<li class="hanmoto-book-actions-item">
						<a class="hanmoto-book-actions-link" href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="<?php echo esc_attr( implode( ' ', $rel ) ); ?>">
							<?php echo esc_html( $link['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	</div>

</div>
