<?php

namespace Hametuha\HanmotoHelper\Rest;

/**
 * REST controller for post types which must not be readable by the public.
 *
 * Core's WP_REST_Posts_Controller lets anyone read posts in `publish` status
 * as long as the post type has `show_in_rest`, even if the post type itself is
 * `public => false`. This controller keeps the REST routes for the block editor
 * but requires edit capability for every read.
 *
 * @package hanmoto
 */
class PrivatePostsController extends \WP_REST_Posts_Controller {

	/**
	 * Only users who can edit the post type can list posts.
	 *
	 * Checked before the query so that X-WP-Total does not leak the count.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$post_type = get_post_type_object( $this->post_type );
		if ( ! $post_type || ! current_user_can( $post_type->cap->edit_posts ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'この投稿を閲覧する権限がありません。', 'hanmoto' ), [
				'status' => rest_authorization_required_code(),
			] );
		}
		return parent::get_items_permissions_check( $request );
	}

	/**
	 * A post is readable only by users who can edit it, whatever its status.
	 *
	 * Used by get_item_permissions_check(), get_items() and embedding.
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool
	 */
	public function check_read_permission( $post ) {
		return current_user_can( 'edit_post', $post->ID ) && parent::check_read_permission( $post );
	}
}
