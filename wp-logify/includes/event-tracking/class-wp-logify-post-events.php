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
		// By default we aren't updating.
		$_SESSION['post event logged'] = false;

		// This should be triggered only when updating a post.
		add_action( 'edit_post_post', array( __CLASS__, 'track_post_updated' ) );

		// This will be triggered when creating a new post or updating an existing one.
		add_action( 'save_post_post', array( __CLASS__, 'track_post_created' ) );

		// Fired before the deletion. It might be better to log the event just prior to the actual
		// deletion in case we want to extract some details from the post to include in the log,
		// before it's actually deleted.
		add_action( 'delete_post', array( __CLASS__, 'track_post_deleted' ) );
	}

	/**
	 * Log the creation of a new post.
	 *
	 * @param int $post_id The ID of the post that was created.
	 */
	public static function track_post_created( int $post_id ) {
		if ( $_SESSION['post event logged'] ) {
			return;
		}

		// Load the post.
		$post = get_post( $post_id );

		// Get the event type.
		$event_type = 'Post Created';

		// Collect details.
		$details = array(
			'Post ID'   => $post_id,
			'Post type' => $post->post_type,
			'Author'    => '/?author=' . $post->post_author,
			'Title'     => $post->title,
			'Content'   => $post->content,
		);

		WP_Logify_Logger::log_event( $event_type, 'post', $post_id, $details );

		$_SESSION['post event logged'] = true;
	}

	/**
	 * Log the update of an existing post.
	 *
	 * @param int $post_id The ID of the post that was created or updated.
	 */
	public static function track_post_updated( int $post_id ) {
		if ( $_SESSION['post event logged'] ) {
			return;
		}

		debug_log( $_POST );

		// Load the post.
		$post = get_post( $post_id );

		debug_log( $post );

		// Get the event type.

		$event_type = 'Post Updated';

		// Collect details.
		$details = array(
			'Post ID'      => $post_id,
			'Post type'    => $post->post_type,
			'Author'       => '/?author=' . $post->post_author,
			'Title'        => $post->title,
			'View post'    => '/?p=' . $post_id,
			'View changes' => '/wp-admin/revision.php?revision=' . $post_id,
		);

		// // Get a link to compare the revisions.
		// $revisions = wp_get_post_revisions( $post_id );

		// if ( ! empty( $revisions ) ) {
		// Get the latest two revisions.
		// $revision_ids = array_keys( $revisions );

		// if ( count( $revision_ids ) >= 2 ) {
		// The current revision (most recent update).
		// $current_revision_id = $revision_ids[0];

		// The previous revision (before the most recent update).
		// $previous_revision_id = $revision_ids[1];

		// Construct the Compare Revisions URL.
		// $compare_url = add_query_arg(
		// array(
		// 'action' => 'diff',
		// 'right'  => $current_revision_id,
		// 'left'   => $previous_revision_id,
		// ),
		// admin_url( 'revision.php' )
		// );

		// Add the Compare Revisions URL to the details array.
		// $details['Compare Revisions'] = '<a href="' . esc_url( $compare_url ) . '">Compare Revisions</a>';
		// }
		// }

		WP_Logify_Logger::log_event( $event_type, 'post', $post_id, $details );

		$_SESSION['post event logged'] = true;
	}

	/**
	 * Log the deletion of an existing post.
	 *
	 * @param int     $post_id The ID of the post that was deleted.
	 * @param WP_Post $post The post object that was deleted.
	 */
	public static function track_post_deleted( int $post_id ) {
		// Load the post.
		$post = get_post( $post_id );

		$event_type = 'Post Deleted';

		$details = array(
			'Post ID' => $post_id,
			'Author'  => '/?author=' . $post->post_author,
			'Title'   => $post->title,
		);

		WP_Logify_Logger::log_event( $event_type, 'post', $post_id, $details );
	}
}
