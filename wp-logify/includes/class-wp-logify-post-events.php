<?php
/**
 * Class WP_Logify_Post_Events
 *
 * This class provides basic tracking functionalities for WordPress.
 * It tracks changes to posts and user logins.
 */
class WP_Logify_Post_Events {
	/**
	 * Link the events we want to log to methods.
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'log_post_created' ) );
		add_action( 'post_updated', array( __CLASS__, 'log_post_updated' ) );
		add_action( 'deleted_post', array( __CLASS__, 'log_post_deleted' ) );
	}

	/**
	 * Log the creation of a new post.
	 *
	 * @param int     $post_id The ID of the post that was created.
	 * @param WP_Post $post The post object that was created.
	 * @param bool    $update Whether this is an update or a new post.
	 */
	public function log_post_created( int $post_id, WP_Post $post, bool $update ) {
		// This method is only for creating new posts.
		if ( $update ) {
			return;
		}

		// Collect the required information.
		$event_type = 'Post Created';
		$details    = array(
			'Post ID' => $post_id,
			'Author'  => get_edit_user_link( $post->post_author ),
			'Title'   => $post->title,
			'Content' => $post->content,
		);

		WP_Logify_Logger::log_event( $event_type, $details );
	}

	/**
	 * Log the update of an existing post.
	 *
	 * @param int     $post_id The ID of the post that was updated.
	 * @param WP_Post $post_after The post object after the update.
	 * @param WP_Post $post_before The post object before the update.
	 */
	public static function log_post_updated( int $post_id, WP_Post $post_after, WP_Post $post_before ) {
		$event_type = 'Post Updated';

		$details = array(
			'Post ID' => $post_id,
			'Author'  => get_edit_user_link( $post_after->post_author ),
		);

		if ( $post_before->title !== $post_after->title ) {
			$details['Original title'] = $post_before->title;
			$details['Updated title']  = $post_after->title;
		} else {
			$detaild['Title'] = $post_after->title;
		}

		if ( $post_before->content !== $post_after->content ) {
			$details['Original content'] = $post_before->content;
			$details['Updated content']  = $post_after->content;
		} else {
			$detaild['Content'] = $post_after->content;
		}

		WP_Logify_Logger::log_event( $event_type, $details );
	}

	/**
	 * Log the deletion of an existing post.
	 *
	 * @param int     $post_id The ID of the post that was deleted.
	 * @param WP_Post $post The post object that was deleted.
	 */
	public static function log_post_deleted( int $post_id, WP_Post $post ) {
		$event_type = 'Post Deleted';
		$details    = array(
			'Post ID' => $post_id,
			'Author'  => get_edit_user_link( $post->post_author ),
			'Title'   => $post->title,
			'Content' => $post->content,
		);

		WP_Logify_Logger::log_event( $event_type, $details );
	}
}
