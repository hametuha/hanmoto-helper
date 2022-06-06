<?php

namespace Hametuha\HanmotoHelper\Pattern;


use http\Encoding\Stream\Inflate;

/**
 * Db model.
 *
 * @package hanmoto
 * @property-read \wpdb  $db          DB object.
 * @property-read string $table       Table name.
 * @property-read string $version_key Current db version.
 */
abstract class Model extends Singleton {

	protected $table_name = '';

	protected $version = '0.0.0';

	/**
	 * {@inheritdoc}
	 */
	protected function init() {
		add_action( 'admin_init', [ $this, 'create_table' ] );
	}

	/**
	 * Is table require db delta?
	 *
	 * @return bool
	 */
	protected function require_update() {
		$current_version = get_option( $this->version_key );
		return version_compare( $this->version, $current_version, '>' );
	}

	/**
	 * Create custom db.
	 *
	 * @return void
	 */
	public function create_table() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}
		if ( ! $this->require_update() ) {
			return;
		}
		$sql = $this->create_sql();
		if ( ! $sql ) {
			return;
		}
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		// Save current version.
		update_option( $this->version_key, $this->version );
		// Display message.
		add_action( 'admin_notices', function() {
			printf(
				'<div class="updated"><p>%s</p></div>',
				sprintf(
					// translators: %s is
					esc_html__( 'データベース %s が更新されました。', 'hanmoto' ),
					esc_html( $this->table )
				)
			);
		} );
	}

	/**
	 * Get create SQL.
	 *
	 * @return string
	 */
	protected function create_sql() {
		return '';
	}

	/**
	 * Genterge WP_Error.
	 *
	 * @param string $message Error message.
	 * @param int    $status  Status code.
	 * @param string $code    Error code.
	 *
	 * @return \WP_Error
	 */
	protected function bad_request( $message, $status = 400, $code = 'invalid_request' ) {
		return new \WP_Error( $code, $message, [
			'status'   => $status,
			'response' => $status,
		] );
	}

	/**
	 * Get found rows.
	 *
	 * @return int
	 */
	public function found_rows() {
		return (int) $this->db->get_var( 'SELECT FOUND_ROWS()' );
	}

	/**
	 * Insert error.
	 *
	 * @return \WP_Error
	 */
	protected function insert_error() {
		return $this->bad_request( __( 'Failed to insert or update data.', 'hanmoto' ), 500 );
	}

	/**
	 * Getter
	 *
	 * @param string $name Property name.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'db':
				global $wpdb;
				return $wpdb;
			case 'table':
				if ( $this->table_name ) {
					return $this->db->prefix . 'hanmoto_' . $this->table_name;
				}
				$class_name = explode( '\\', get_called_class() );
				$class_name = $class_name[ count( $class_name ) - 1 ];
				return $this->db->prefix . 'hanmoto_' . strtolower( preg_replace( '/^_/u', '', preg_replace_callback( '/[A-Z]/u', function( $matches ) {
					return '_' . $matches[0];
				}, $class_name ) ) );
			case 'version_key':
				return 'hanmoto_db_' . $this->table_name;
			default:
				return null;

		}
	}
}
