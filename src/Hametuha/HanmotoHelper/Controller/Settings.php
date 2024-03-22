<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * Setting Controller.
 *
 * @package hanmoto
 * @property-read string $post_type        Post type. Default 'books'
 * @property-read bool   $sync_post_type   Should sync book infromation?
 * @property-read bool   $create_post_type Is post type to be created?
 */
class Settings extends Singleton {

	/**
	 * @inheritDoc
	 */
	protected function init() {
		add_action( 'admin_init', [ $this, 'admin_setting' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
	}

	/**
	 * Register menu.
	 */
	public function admin_menu() {
		$title = __( '版元機能設定', 'hanmoto' );
		add_options_page( $title, $title, 'manage_options', 'hanmoto-helper', [ $this, 'admin_render' ] );
	}

	/**
	 * Render admin menu.
	 */
	public function admin_render() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ISBN Setting', 'hanmoto' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( 'hanmoto-helper' );
				do_settings_sections( 'hanmoto-helper' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Setting fields.
	 */
	public function admin_setting() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		foreach ( $this->get_settings() as $section_id => $section ) {
			// Register section
			$section_id = 'hanmoto-helper-' . $section_id;
			add_settings_section( $section_id, $section['label'], function() use ( $section ) {
				if ( ! empty( $section['desc'] ) ) {
					printf( '<p class="description">%s</p>', esc_html( $section['desc'] ) );
				}
			}, 'hanmoto-helper' );
			foreach ( $section['options']  as $setting ) {
				$option_id = 'hanmoto_' . $setting['id'];
				add_settings_field( $option_id, $setting['label'], function() use ( $option_id, $setting ) {
					$value      = $this->get_setting( $setting['id'], true );
					$predefined = $this->get_predefined( $setting['id'] );
					switch ( $setting['type'] ) {
						case 'text':
							printf(
								'<input name="%s" type="text" value="%s" placeholder="%s" class="regular-text" />',
								esc_attr( $option_id ),
								esc_attr( $value ),
								esc_attr( $setting['placeholder'] )
							);
							break;
						case 'textarea':
							printf(
								'<textarea name="%s" placeholder="%s" class="widefat" style="box-sizing: border-box" rows="3">%s</textarea>',
								esc_attr( $option_id ),
								esc_attr( $setting['placeholder'] ),
								esc_textarea( $value )
							);
							break;
						case 'radio':
							foreach ( $setting['choices'] as $choice_value => $choice_label ) {
								printf(
									'<p><label><input type="radio" name="%s" value="%s" %s/> %s</label></p>',
									esc_attr( $option_id ),
									esc_attr( $choice_value ),
									checked( $choice_value, $value, false ),
									esc_html( $choice_label )
								);
							}
							break;
					}
					if ( ! empty( $setting['help'] ) ) {
						printf(
							'<p class="description">%s</p>',
							esc_html( $setting['help'] )
						);
					}
				}, 'hanmoto-helper', $section_id );
				register_setting( 'hanmoto-helper', $option_id );
			}
		}
	}

	/**
	 * Setting fields.
	 *
	 * @return array[]
	 */
	protected function get_settings() {
		return [
			'publisher' => [
				'label'   => __( '版元情報', 'hanmoto' ),
				'desc'    => '',
				'options' => [
					[
						'id'          => 'publisher_id',
						'label'       => __( '出版社記号', 'hanmoto' ),
						'type'        => 'textarea',
						'placeholder' => 'e.g. 12345678',
						'help'        => __( '出版社記号を各行に1つ入れてください。', 'hanmoto' ),
					],
					[
						'id'      => 'create_post_type',
						'label'   => __( 'カスタム投稿タイプ', 'hanmoto' ),
						'type'    => 'radio',
						'help'    => __( '有効にすると非公開の投稿タイプ "books" が作成されます。', 'hanmoto' ),
						'choices' => [
							''  => __( '無効', 'hanmoto' ),
							'1' => __( '有効にしてカスタム投稿タイプを作成', 'hanmoto' ),
						],
					],
					[
						'id'      => 'sync_post_type',
						'label'   => __( '投稿タイプを同期する', 'hanmoto' ),
						'type'    => 'radio',
						'help'    => __( '有効にすると、書誌情報が定期的に保存されます。', 'hanmoto' ),
						'choices' => [
							''  => __( '同期しない', 'hanmoto' ),
							'1' => __( '自動で同期する', 'hanmoto' ),
						],
					],
					[
						'id'          => 'post_type',
						'label'       => __( '同期する投稿タイプ', 'hanmoto' ),
						'type'        => 'text',
						'placeholder' => 'books',
						'help'        => __( '初期値は books です。既存の投稿タイプを入力すると、書誌情報がその投稿タイプに同期されます。', 'hanmoto' ),
					],
					[
						'id'          => 'associate_id',
						'label'       => __( 'Amazon アソシエイト ID', 'hanmoto' ),
						'type'        => 'text',
						'placeholder' => 'hametuha-22',
					],
					[
						'id'          => 'rakuten_app_id',
						'label'       => __( '楽天アプリID', 'hanmoto' ),
						'type'        => 'text',
						'placeholder' => '1051183079836014250',
						'help'        => __( 'If set, generate link to Rakuten Books.', 'hanmoto' ),
					],
					[
						'id'          => 'rakuten_affiliate_id',
						'label'       => __( '楽天アフィリエイト ID', 'hanmoto' ),
						'type'        => 'text',
						'placeholder' => '0e9cde67.8fb388cd.0e9cde68.6632f7db',
					],
				],
			],
			'order'     => [
				'label'   => __( '注文管理', 'hanmoto' ),
				'desc'    => __( '注文管理機能に関する設定です。', 'hanmoto' ),
				'options' => [
					[
						'id'      => 'use_order_manager',
						'label'   => __( '注文管理', 'hanmoto' ),
						'type'    => 'radio',
						'help'    => __( '有効にすると、注文管理機能が有効になります。', 'hanmoto' ),
						'choices' => [
							''  => __( '注文を管理しない', 'hanmoto' ),
							'1' => __( '注文管理機能を使う', 'hanmoto' ),
						],
					],
					[
						'id'          => 'issued_by',
						'label'       => __( '納品者', 'hanmoto' ),
						'type'        => 'text',
						'placeholder' => '',
					],
					[
						'id'          => 'issue_owner',
						'label'       => __( '納品担当者', 'hanmoto' ),
						'type'        => 'text',
						'placeholder' => '',
					],
				],
			],
		];
	}

	/**
	 * Get option value.
	 *
	 * @param string $id  ID of option.
	 * @param false  $raw If true, only returns saved value.
	 * @return mixed
	 */
	public function get_setting( $id, $raw = false ) {
		if ( ! $raw ) {
			$predefined = $this->get_predefined( $id );
			if ( ! is_null( $predefined ) ) {
				return $predefined;
			}
		}
		return get_option( 'hanmoto_' . $id );
	}

	/**
	 * Get publisher ids.
	 *
	 * @return string[]
	 */
	public function get_publisher_ids() {
		$value = $this->get_setting( 'publisher_id' );
		if ( empty( $value ) ) {
			return [];
		}
		return array_values( array_filter( preg_split( "/(\r\n|\r|\n)/u", $value ) ) );
	}

	/**
	 * Get predefined values
	 *
	 * @param string $id ID.
	 *
	 * @return mixed|null
	 */
	protected function get_predefined( $id ) {
		return apply_filters( 'hanmoto_predefined_values', null, $id );
	}

	/**
	 * Getter
	 *
	 * @param string $name Option name.
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'post_type':
				$post_type = $this->get_setting( 'post_type' );
				return $post_type ?: 'books';
			case 'sync_post_type':
			case 'create_post_type':
				return (bool) $this->get_setting( $name );
			default:
				return null;
		}
	}
}
