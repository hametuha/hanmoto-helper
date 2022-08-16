<?php

namespace Hametuha\HanmotoHelper\Models;


use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\BookSelector;

/**
 * Suppolier.
 *
 * @package hanmoto
 */
class ModelSupplier extends Singleton {

	use BookSelector;

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_action( 'init', [ $this, 'register_taxonomies' ] );
		// Save information.
		add_action( 'edited_term', [ $this, 'save_term' ], 10, 3 );
		// Render fields.
		add_action( 'supplier_edit_form_fields', [ $this, 'term_form' ], 10, 2 );
	}

	/**
	 * Register taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		register_taxonomy( 'supplier', [ 'inventory', ModelOrder::post_type() ], [
			'public'            => false,
			'show_ui'           => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'label'             => __( '取引先', 'hanmoto' ),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'meta_box_cb'       => $this->hierarchical_radio( 'supplier', __( '取引先', 'hanmoto' ) ),
		] );
	}

	/**
	 * Save term meta.
	 *
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Term taxonomy id.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return void
	 */
	public function save_term( $term_id, $tt_id, $taxonomy ) {
		if ( 'supplier' !== $taxonomy ) {
			return;
		}
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_habmotononce' ), 'update_supplier' ) ) {
			return;
		}
		foreach ( [ 'address', 'in_charge', 'tel', 'fax', 'mail', 'wholesaler', 'line_code', 'shop_code' ] as $key ) {
			update_term_meta( $term_id, $key, filter_input( INPUT_POST, $key ) );
		}
	}

	/**
	 * Render form field.
	 *
	 * @param \WP_Term $tag      Term object.
	 * @param string   $taxonomy No need to check.
	 *
	 * @return void
	 */
	public function term_form( \WP_Term $tag, $taxonomy ) {
		foreach ( [
			[ 'in_charge', __( '担当者', 'hanmoto' ), __( '例・版元太郎', 'hanmoto' ) ],
			[ 'tel', __( '電話', 'hanmoto' ), __( '例・03-1234-5678', 'hanmoto' ) ],
			[ 'fax', __( 'FAX', 'hanmoto' ), __( '例・03-1234-5678', 'hanmoto' ) ],
			[ 'mail', __( 'メール', 'hanmoto' ), __( '例・mail@example.com', 'hanmoto' ) ],
			[ 'wholesaler', __( '取次', 'hanmoto' ), __( '例・日販', 'hanmoto' ) ],
			[ 'line_code', __( '番線', 'hanmoto' ), __( '例・12A34', 'hanmoto' ) ],
			[ 'shop_code', __( '書店コード', 'hanmoto' ), __( '例・123456', 'hanmoto' ) ],
		] as list( $key, $label, $placeholder ) ) :
			?>
			<tr>
				<th>
					<label for="hanmoto-<?php echo esc_attr( $key ) ?>">
						<?php echo esc_html( $label ); ?>
					</label>
				</th>
				<td>
					<input class="widefat" type="text" id="hanmoto-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_term_meta( $tag->term_id, $key, true ) ); ?>"
						placeholder="<?php echo esc_attr( $placeholder ); ?>" />
				</td>
			</tr>

		<?php endforeach; ?>
		<tr>
			<th><label for="hanmoto-address"><?php esc_html_e( '住所', 'hanmoto' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'update_supplier', '_habmotononce', false ); ?>
				<textarea style="box-sizing: border-box" class="widefat" rows="10" id="hanmoto-address" name="address"><?php echo esc_textarea( get_term_meta( $tag->term_id, 'address', true ) ); ?></textarea>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get term by line code.
	 *
	 * @param string $wholesaler Wholesaler.
	 * @param string $line_code  Line code.
	 * @param string $shop_code  Shop code.
	 *
	 * @return \WP_Term|null
	 */
	public function get_shop_by_codes( $wholesaler, $line_code, $shop_code ) {
		$query = new \WP_Term_Query( [
			'taxonomy'   => 'supplier',
			'number'     => 1,
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'   => 'wholesaler',
					'value' => $wholesaler,
				],
				[
					'key'   => 'line_code',
					'value' => $line_code,
				],
				[
					'key'   => 'shop_code',
					'value' => $shop_code,
				],
			],
		] );
		$terms = $query->get_terms();
		return empty( $terms ) ? null : $terms[0];
	}


	public function save_shop( $title, $wholesaler, $line_code, $shop_code, $args = [] ) {

	}
}
