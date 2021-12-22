<?php

namespace Hametuha\HanmotoHelper\Services;


use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * WooCommerce Setting class.
 */
class WooCommerceSetting extends Singleton {

	/**
	 * @var int Default capture days.
	 */
	protected static $default_capture_days = 30;

	/**
	 * @inheritDoc
	 */
	protected function init() {
		add_filter( 'woocommerce_get_sections_products', [ $this, 'add_sections' ] );
		add_filter( 'woocommerce_get_settings_products', [ $this, 'setting_section' ], 10, 2 );
	}

	/**
	 * Add a new section.
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	public function add_sections( $sections ) {
		$sections['hanmoto'] = __( '版元設定', 'hanmoto' );
		return $sections;
	}

	/**
	 * Register settings.
	 *
	 * @param array $sections Sections.
	 * @return array
	 */
	public function setting_section( $settings, $current_section ) {
		if ( 'hanmoto' === $current_section ) {
			$coupons = [
				'' => __( '指定しない', 'hanmoto' ),
			];
			foreach ( get_posts( [
				'post_type'      => 'shop_coupon',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			] ) as $coupon ) {
				$coupons[ $coupon->ID ] = get_the_title( $coupon );
			}
			$settings = [
				[
					'title' => __( '版元設定', 'woocommerce' ),
					'type'  => 'title',
					'desc'  => '',
					'id'    => 'hanmoto_setting',
				],
				[
					'title'   => __( 'クーポン', 'hanmoto' ),
					'id'      => 'hanmoto_book_shop_coupon',
					'type'    => 'select',
					'default' => '',
					'options' => $coupons,
					'desc'    => __( '指定されたクーポンは書店だけが利用できるようになります。', 'hanmoto' ),
				],
				[
					'title'       => __( '請求予定日', 'hanmoto' ),
					'id'          => 'hanmoto_capture_date',
					'type'        => 'number',
					'placeholder' => self::$default_capture_days,
					'desc'        => __( '注文品受付のあと、指定日数が経過するとクレジットカードに請求をかけます。', 'hanmoto' ),
				],
				[
					'title'       => __( '外部注文サイト名称', 'hanmoto' ),
					'id'          => 'hanmoto_retail_external_label',
					'type'        => 'text',
					'desc_tip'    => __( '入力しない場合は「外部注文サイト」が表示されます。', 'hanmoto' ),
					'placeholder' => __( '外部注文サイト', 'hanmoto' ),
				],
				[
					'title'       => __( '外部注文サイトURL', 'hanmoto' ),
					'id'          => 'hanmoto_retail_external_url',
					'type'        => 'url',
					'desc'        => __( '書店用外部注文サイトがある場合は入力してください。 空白の場合は無視されます。', 'hanmoto' ),
					'placeholder' => 'e.g. https://example.com',
				],
				[
					'title'             => __( '注文に関する注記', 'hanmoto' ),
					'id'                => 'hanmoto_retail_desc',
					'type'              => 'textarea',
					'desc_tip'          => __( '外部URLでの注文や注文取引に対する注記を記載してください。', 'hanmoto' ),
					'custom_attributes' => [
						'rows' => 5,
					],
				],
				[
					'title'       => __( '注文説明URL', 'hanmoto' ),
					'id'          => 'hanmoto_retail_desc_url',
					'type'        => 'url',
					'desc'        => __( 'より詳細なURLがある場合は入力してください。', 'hanmoto' ),
				],
				[
					'type' => 'sectionend',
					'id'   => 'hanmoto_setting',
				],
			];
		}
		return $settings;
	}

	/**
	 * Get capture days.
	 *
	 * @return int
	 */
	public static function capture_days() {
		return max( 1, (int) get_option( 'hanmoto_capture_date', self::$default_capture_days ) );
	}
}
