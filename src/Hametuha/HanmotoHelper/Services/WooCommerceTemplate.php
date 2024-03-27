<?php

namespace Hametuha\HanmotoHelper\Services;


use Hametuha\HanmotoHelper\Controller\PostType;
use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Services\Utilities\Accessor;
use Hametuha\HanmotoHelper\Utility\OpenDbApi;

/**
 * Product template hooks.
 *
 * @package hanmoto
 *
 */
class WooCommerceTemplate extends Singleton {

	use Accessor;

	/**
	 * @inheritDoc
	 */
	protected function init() {
		// Single product.
		add_action( 'woocommerce_single_product_summary', [ $this, 'after_title' ], 6 );
		add_action( 'woocommerce_product_meta_start', [ $this, 'product_meta' ] );
		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'external_buttons' ], 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'woocommerce_product_tabs', [ $this, 'tabs' ] );
		// Cart.
		add_action( 'woocommerce_before_cart_table', [ $this, 'before_cart' ] );
		add_action( 'woocommerce_after_cart_item_name', [ $this, 'item_in_cart' ], 10, 2 );

	}

	/**
	 * Enqueueu assets.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'hanmoto-woocommerce' );
	}

	/**
	 * After title.
	 *
	 * @return void
	 */
	public function after_title() {
		$output = '';
		$author = get_post_meta( get_the_ID(), 'hanmoto_authors', true );
		if ( $author ) {
			$output = sprintf( '<p class="hanmoto-author">%s</p>', esc_html( $author ) );
		}
		$output = apply_filters( 'hanmoto_single_author', $output, get_the_ID() );
		echo $output;
	}

	/**
	 * Display product meta.
	 *
	 * @return void
	 */
	public function product_meta() {
		$output  = [];
		$post_id = get_the_ID();
		// ISBN.
		$isbn = get_post_meta( $post_id, PostType::META_KEY_ISBN, true );
		if ( $isbn ) {
			$output [] = sprintf( '<span class="isbn hanmoto-meta">ISBN: <code>%s</code></span>', esc_html( $isbn ) );
		}
		// Publisher.
		$publisher = get_post_meta( $post_id, 'hanmoto_publisher', true );
		if ( $publisher ) {
			$output [] = sprintf( '<span class="publisher hanmoto-meta">%s: <strong>%s</strong></span>', esc_html__( '発行者', 'hanmoto' ), esc_html( $publisher ) );
		}
		// Published date.
		$date = get_post_meta( $post_id, 'hanmoto_published_at', true );
		if ( $date ) {
			$output [] = sprintf( '<span class="published_at  hanmoto-meta">%s: %s</span>', esc_html__( '発売日', 'hanmoto' ), esc_html( mysql2date( get_option( 'date_format' ), $date ) ) );
		}
		// Page lenght.
		$pages = get_post_meta( $post_id, 'hanmoto_pages', true );
		if ( is_numeric( $pages ) ) {
			// translators: %s is page length.
			$length    = sprintf( _n( '%sページ', '%sページ', $pages, 'hanmoto' ), number_format( $pages ) );
			$output [] = sprintf( '<span class="page_length hanmoto-meta">%s: %s</span>', esc_html__( '長さ', 'hanmoto' ), esc_html( $length ) );
		}
		// Can order
		if ( $this->helper->product_can_order( $post_id ) ) {
			$output [] = sprintf( '<span class="book_shop_order hanmoto-meta">%s: <a href="#tab-title-book_shop_order">%s</a></span>', esc_html__( '書店注文', 'hanmoto' ), esc_html__( '可能', 'hanmoto' ) );
		}
		$output = apply_filters( 'hanmoto_single_meta', $output, $post_id );
		echo implode( "\n", $output );
	}

	/**
	 * Display external buttons.
	 *
	 * @return void
	 */
	public function external_buttons() {
		$book = $this->helper->get_product_book( get_the_ID() );
		if ( ! $book ) {
			return;
		}
		$actions = array_filter( hanmoto_actions( $book ), function( $link ) {
			return ! in_array( $link['id'], [ 'hanmoto', 'direct' ], true );
		} );
		if ( empty( $actions ) ) {
			return;
		}
		$output = '<div class="hanmoto-actions">';
		foreach ( $actions as $link ) {
			$rel = [ 'noopener', 'noreferrer' ];
			if ( $link['sponsored'] ) {
				$rel[] = 'sponsored';
			}
			$output .= sprintf(
				'<a class="button hanmoto-button hanmoto-button-%s" href="%s" target="_blank" rel="%s">%s</a>',
				esc_attr( $link['id'] ),
				esc_url( $link['url'] ),
				esc_attr( implode( ' ', $rel ) ),
				// translators: %s is shop name.
				esc_html( $link['title'] )
			);
		}
		$output .= '</div>';
		echo apply_filters( 'hanmoto_external_shops', $output, get_the_ID() );
	}

	/**
	 * Product tabs.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function tabs( $tabs ) {
		if ( $this->helper->product_can_order( get_the_ID() ) ) {
			$tabs['book_shop_order'] = [
				'title'    => __( '書店注文情報', 'hanmoto' ),
				'priority' => 40,
				'callback' => [ $this, 'tab_content' ],
			];
		}
		return $tabs;
	}

	/**
	 * Render tab contents.
	 *
	 * @return void
	 */
	public function tab_content() {
		?>
		<div class="hanmoto-book-shop-notice">
			<h2><?php esc_html_e( '注文を検討の書店様へ', 'hanmoto' ); ?></h2>
			<?php
			$desc = get_option( 'hanmoto_retail_desc' );
			if ( $desc ) {
				echo wp_kses_post( wpautop( $desc ) );
			}
			$link = get_option( 'hanmoto_retail_desc_url' );
			if ( $link ) {
				printf( '<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>', esc_url( $link ), esc_html__( '注文について詳しく', 'hanmoto' ) );
			}
			$props = [];
			// Coupon.
			$coupon = $this->order->get_shop_coupon();
			if ( $coupon ) {
				$props['rate'] = [
					'label' => __( '掛け率', 'hanmoto' ),
					'value' => sprintf( '%s%%', 100 - $coupon->get_amount() ),
					'desc'  => '',
				];
			}
			$props['date'] = [
				'label' => __( '請求日', 'hanmoto' ),
				// translators: %d is days.
				'value' => sprintf( esc_html__( '%d日後請求確定', 'hanmoto' ), $this->helper->get_capture_date() ),
				'desc'  => esc_html__( '注文日から指定の日数が経過すると請求が確定します。', 'hanmoto' ),
			];
			$order_sheet   = get_post_meta( get_the_ID(), 'hanmoto_order_sheet', true );
			if ( $order_sheet ) {
				$props['order_sheet'] = [
					'label' => __( '注文書', 'hanmoto' ),
					// translators: %s is URL.
					'value' => wp_kses_post( sprintf( __( '<a href="%s" target="_blank" rel="noopener noreferrer">注文用FAX</a>', 'hanmoto' ), esc_url( $order_sheet ) ) ),
					'desc'  => esc_html__( 'ダウンロードしてご利用ください。', 'hanmoto' ),
				];
			}
			$url = get_option( 'hanmoto_retail_external_url' );
			if ( $url ) {
				$label               = get_option( 'hanmoto_retail_external_label' ) ?: __( '外部注文サイト', 'hanmoto' );
				$props['order_site'] = [
					'label' => __( '他の注文方法', 'hanmoto' ),
					'value' => sprintf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( $url ),
						esc_html( $label )
					),
					'desc'  => wp_kses_post( get_option( '' ) ),
				];
			}
			$book = $this->helper->get_product_book( get_the_ID() );
			if ( $book ) {
				$actions = array_filter( hanmoto_actions( $book ), function( $action ) {
					return 'hanmoto' === $action['id'];
				} );
				if ( ! empty( $actions ) ) {
					foreach ( $actions as $action ) {
						$props['hanmoto'] = [
							'label' => __( '参考情報', 'hanmoto' ),
							// translators: %s is URL.
							'value' => wp_kses_post( sprintf( __( '<a href="%s" target="_blank" rel="noopener noreferrer">版元ドットコムの情報を見る</a>', 'hanmoto' ), esc_url( $action['url'] ) ) ),
						];
					}
				}
			}
			// Promotion tools.
			$promotion_tools = get_post_meta( get_the_ID(), 'hanmoto_promotion_tools', true );
			if ( ! empty( $promotion_tools ) ) :
				?>
				<div class="hanmoto-book-promotion">
					<h3><?php esc_html_e( '販促ツールなど', 'hanmoto' ); ?></h3>
					<?php echo wp_kses_post( wpautop( $promotion_tools ) ); ?>
				</div>
			<?php endif; ?>

			<hr />

			<dl class="hanmoto-book-shop-props">
				<?php foreach ( $props as $key => $prop ) : ?>
				<dt><?php echo esc_html( $prop['label'] ); ?></dt>
				<dd>
					<?php echo $prop['value']; ?>
					<?php if ( ! empty( $prop['desc'] ) ) : ?>
						<span class="description"><?php echo $prop['desc']; ?></span>
					<?php endif; ?>
				</dd>
				<?php endforeach; ?>
			</dl>

			<hr />

			<?php
			// Book shop registration.
			if ( ! current_user_can( 'book_shop' ) ) :
				?>
				<h3><?php esc_html_e( '書店として登録', 'hanmoto' ); ?></h3>
				<ol>
					<?php if ( ! is_user_logged_in() ) : ?>
						<li>
							<?php
							echo wp_kses_post( sprintf(
								// translators: %1$s is URL.
								__( '<a href="%1$s">ユーザー登録</a>または<a href="%1$s">ログイン</a>をしてください。', 'hanmoto' ),
								esc_url( wc_get_page_permalink( 'myaccount' ) )
							) )
							?>
						</li>
					<?php endif; ?>
					<li>
						<?php
						echo wp_kses_post( sprintf(
							// translators: %1$s is URL.
							__( '<a href="%1$s">マイアカウント</a>の「住所 ＞ 請求先情報」を書店の住所にし、「書店として登録」にチェックを入れてください。', 'hanmoto' ),
							esc_url( wc_get_page_permalink( 'myaccount' ) )
						) )
						?>
					</li>
				</ol>
			<?php else : ?>
				<p class="description">
					<?php
					// translators: %s is customer's name.
					printf( esc_html__( 'こんにちは、%sさん。書店として登録済みです。', 'hanmoto' ), esc_html( wp_get_current_user()->display_name ) );
					?>
				</p>
			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Display notice on cart.
	 *
	 * @return void
	 */
	public function before_cart() {
		if ( current_user_can( 'book_shop' ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( '「書店注文価格を適用」をクリックすると割引価格が適用されます。', 'hanmoto' )
			);
		} else {
			printf(
				'<p><span class="description">%s &raquo; <a href="%s">%s</a></span></p>',
				esc_html__( '書店取引をご希望の方はログインして「マイアカウント ＞ 住所 ＞ 請求先情報」から登録してください。', 'hanmoto' ),
				esc_url( wc_get_page_permalink( 'myaccount' ) ),
				esc_html__( 'マイアカウント', 'hanmoto' )
			);
		}
	}

	/**
	 * If item is orderable, display.
	 *
	 * @param array  $item $product.
	 * @param string $item_key Item key.
	 * @return void
	 */
	public function item_in_cart( $item, $item_key ) {
		if ( ! current_user_can( 'book_shop' ) ) {
			return;
		}
		if ( ! $this->helper->product_can_order( $item['product_id'] ) ) {
			// This is not orderable.
			return;
		}
		$coupon = $this->order->get_shop_coupon();
		if ( ! $coupon ) {
			return;
		}
		$product = wc_get_product( $item['product_id'] );
		$rate    = min( 100, 100 - $coupon->get_amount() );
		$price   = (int) ( $product->get_price() / 100 * $rate );
		?>
		<span class="hanmoto-cart-detail"><strong>書店注文<?php echo esc_html( $rate ); ?>%</strong>（<?php echo number_format( $price ); ?>円）</span>
		<?php
	}
}
