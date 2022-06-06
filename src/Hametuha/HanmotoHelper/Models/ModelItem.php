<?php

namespace Hametuha\HanmotoHelper\Models;


use Hametuha\HanmotoHelper\Pattern\Model;

/**
 * Item model
 *
 * @package hanmoto
 */
class ModelItem extends Model {

	protected $version = '0.0.3';

	/**
	 * {@inheritdoc}
	 */
	protected function create_sql() {
		$charset_collate = $this->db->get_charset_collate();
		$sql             = <<<SQL
CREATE TABLE `{$this->table}` (
  id mediumint(9) NOT NULL PRIMARY KEY,
  title VARCHAR(256) NOT NULL,
  parent INT DEFAULT 0,
  registered DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
  updated DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
  index parent_id (parent)
) ENGINE=InnoDB {$charset_collate};
SQL;
		return $sql;
	}

	/**
	 * Register item.
	 *
	 * @param string $id     Unique ID. e.g. ISBN.
	 * @param string $title  Title.
	 * @param int    $parent Parent ID.
	 *
	 * @return string|\WP_Error
	 */
	public function register( $id, $title, $parent = 0 ) {
		$now            = current_time( 'mysql', true );
		$parent_post_id = 0;
		if ( $parent ) {
			$parent_post = get_post( $parent );
			if ( ! $parent_post ) {
				return $this->bad_request( __( 'No parent nexsits.', 'hanmoto' ) );
			}
			$parent_post_id = $parent_post->ID;
		}
		$result = $this->db->insert( $this->table, [
			'id'         => $id,
			'title'      => $title,
			'parent'     => $parent_post_id,
			'registered' => $now,
			'updated'    => $now,
		], [ '%s', '%s', '%d', '%s', '%s' ] );
		if ( ! $result ) {
			return $this->insert_error();
		}
		return $id;
	}

	/**
	 * Get items.
	 *
	 * @param array $args Query arguments.
	 * @return \stdClass[]
	 */
	public function get_items( $args = [] ) {
		$args           = wp_parse_args( $args, [
			'paged'          => 1,
			'posts_per_page' => 20,
			's'              => '',
			'orderby'        => 'registered',
			'order'          => 'DESC',
		] );
		$posts_per_page = max( 1, $args['posts_per_page'] );
		$offset         = max( 0, $args['paged'] - 1 );
		$orderby        = in_array( $args['orderby'], [ 'registered', 'updated', 'title' ], true ) ? $args['orderby'] : 'registered';
		$order          = ( 'DESC' === $args['order'] ) ? 'DESC' : 'ASC';
		$wheres         = $args['s'] ? "WHERE title LIKE ':::%s:::'" : '';
		$query          = <<<SQL
			SELECT SQL_CALC_FOUND_ROWS * FROM {$this->table}
			{$wheres}
			ORDER BY %s %s
			LIMIT %d, %d
SQL;
		$query          = $this->db->prepare( $query, $orderby, $order, ( $offset * $posts_per_page ), $posts_per_page );
		if ( $args['s'] ) {
			$query = str_replace( ':::', '%', $query );
		}
		return $this->db->get_results( $query );
	}
}
