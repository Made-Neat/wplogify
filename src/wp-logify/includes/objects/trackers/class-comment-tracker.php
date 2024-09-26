<?php
/**
 * Contains the Comment_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Comment;
use WP_Error;

/**
 * Class WP_Logify\Comment_Tracker
 *
 * Provides tracking of events related to comments.
 */
class Comment_Tracker {

	/**
	 * Array to remember properties between different events.
	 *
	 * @var array
	 */
	protected static $properties = array();

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Add comment.
		add_action( 'wp_insert_comment', array( __CLASS__, 'on_wp_insert_comment' ), 10, 2 );

		// Edit comment.
		add_filter( 'wp_update_comment_data', array( __CLASS__, 'on_wp_update_comment_data' ), 10, 3 );
		add_action( 'edit_comment', array( __CLASS__, 'on_edit_comment' ), 10, 2 );

		// Delete comment.
		add_action( 'delete_comment', array( __CLASS__, 'on_delete_comment' ), 10, 2 );

		// Change to comment status.
		add_action( 'transition_comment_status', array( __CLASS__, 'on_transition_comment_status' ), 10, 3 );
		add_action( 'trashed_post_comments', array( __CLASS__, 'on_trashed_post_comments' ), 10, 2 );
		add_action( 'untrash_post_comments', array( __CLASS__, 'on_untrash_post_comments' ), 10, 1 );
	}

	/**
	 * Fires immediately after a comment is inserted into the database.
	 *
	 * @param int        $id      The comment ID.
	 * @param WP_Comment $comment Comment object.
	 */
	public static function on_wp_insert_comment( int $id, WP_Comment $comment ) {
		// debug( 'on_wp_insert_comment', $id, $comment );

		Logger::log_event( 'Comment Added', $comment );
	}

	/**
	 * Filters the comment data immediately before it is updated in the database.
	 *
	 * Note: data being passed to the filter is already unslashed.
	 *
	 * NOTE: Records changes to properties for logging in on_edit_comment().
	 * We could log it here but filter hooks aren't supposed to have side effects.
	 *
	 * @param array|WP_Error $data       The new, processed comment data, or WP_Error.
	 * @param array          $comment    The old, unslashed comment data.
	 * @param array          $commentarr The new, raw comment data.
	 * @return array|WP_Error The new, processed comment data, or WP_Error.
	 */
	public static function on_wp_update_comment_data( array|WP_Error $data, array $comment, array $commentarr ): array|WP_Error {
		// If the data is an error, ignore.
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		global $wpdb;

		// Record changes in the properties.
		foreach ( $comment as $key => $val ) {
			// Ignore post fields.
			if ( $key === 'post_fields' ) {
				continue;
			}

			// Process values.
			$val     = Types::process_database_value( $key, $val );
			$new_val = Types::process_database_value( $key, $commentarr[ $key ] );

			// Check for difference.
			$diff = Types::get_diff( $val, $new_val );

			// If there's a difference, update the properties.
			if ( $diff ) {
				Property_Array::set( self::$properties, $key, $wpdb->comments, $val, $new_val );
			}
		}

		return $data;
	}

	/**
	 * Fires immediately after a comment is updated in the database.
	 *
	 * The hook also fires immediately before comment status transition hooks are fired.
	 *
	 * NOTE: Relies on properties being set in on_wp_update_comment_data().
	 *
	 * @param int   $comment_id The comment ID.
	 * @param array $data       Comment data.
	 */
	public static function on_edit_comment( int $comment_id, array $data ) {
		// debug( 'on_edit_comment', $comment_id, $data );

		// Load the comment.
		$comment = Comment_Utility::load( $comment_id );

		// Log the event.
		Logger::log_event( 'Comment Edited', $comment, null, self::$properties );
	}

	/**
	 * Fires immediately before a comment is deleted from the database.
	 *
	 * @param string     $comment_id The comment ID as a numeric string.
	 * @param WP_Comment $comment    The comment to be deleted.
	 */
	public static function on_delete_comment( string $comment_id, WP_Comment $comment ) {
		// debug( 'on_delete_comment', $comment_id, $comment );

		// Get all the comment properties in case we need to restore it.
		$props = Comment_Utility::get_properties( $comment );

		// Log the event.
		Logger::log_event( 'Comment Deleted', $comment, null, $props );
	}

	/**
	 * Fires when the comment status is in transition.
	 *
	 * @param int|string $new_status The new comment status.
	 * @param int|string $old_status The old comment status.
	 * @param WP_Comment $comment    Comment object.
	 */
	public static function on_transition_comment_status( int|string $new_status, int|string $old_status, WP_Comment $comment ) {
		// debug( 'on_transition_comment_status', $new_status, $old_status, $comment );

		// Ignore delete events.
		if ( $new_status === 'delete' ) {
			return;
		}

		// Get the event type.
		if ( $old_status === 'trash' ) {
			$verb = 'Restored';
		} else {
			// Get the event type.
			$verb = match ( $new_status ) {
				'approved'     => 'Approved',
				'unapproved'   => 'Pending',
				'spam'         => 'Marked as Spam',
				'trash'        => 'Trashed',
				default        => 'Status Changed',
			};
		}
		$event_type = "Comment $verb";

		// Store the old and new status in a property.
		$props = array();
		Property_Array::set( $props, 'status', null, $old_status, $new_status );

		// Log the event.
		Logger::log_event( $event_type, $comment, null, $props );
	}

	/**
	 * Fires after comments are sent to the Trash.
	 *
	 * We use this event handler to make note of any comments that were trashed along with the post,
	 * as these changes aren't picked up by on_transition_comment_status().
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $statuses Array of comment statuses.
	 */
	public static function on_trashed_post_comments( int $post_id, array $statuses ) {
		// debug( 'on_trashed_post_comments', $post_id, $statuses );

		// Get the post reference.
		$post_ref = new Object_Reference( 'post', $post_id );

		// Get the comments attached to this post, which have now been trashed with the post.
		foreach ( $statuses as $comment_id => $approved ) {
			// Load the comment.
			$comment = Comment_Utility::load( $comment_id );

			// Store some additional properties.
			$props      = array();
			$old_status = Comment_Utility::approved_to_status( $approved );
			Property_Array::set( $props, 'status', null, $old_status, 'post-trashed' );
			Property_Array::set( $props, 'post', null, $post_ref );

			// Log the event.
			Logger::log_event( 'Comment Trashed with Post', $comment, null, $props );
		}
	}

	/**
	 * Fires before comments are restored for a post from the Trash.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_untrash_post_comments( int $post_id ) {
		// debug( 'on_untrash_post_comments', $post_id );

		// Get the original statuses of the comments that were trashed with the post, if any.
		$statuses = get_post_meta( $post_id, '_wp_trash_meta_comments_status', true );

		// No statuses, nothing to do.
		if ( ! $statuses ) {
			return true;
		}

		// Get the post reference.
		$post_ref = new Object_Reference( 'post', $post_id );

		foreach ( $statuses as $comment_id => $comment_status ) {
			// Load the comment.
			$comment = Comment_Utility::load( $comment_id );

			// Get the restored status.
			$new_status = Comment_Utility::approved_to_status( $comment_status );

			// Store some additional properties.
			$props = array();
			Property_Array::set( $props, 'status', null, 'post-trashed', $new_status );
			Property_Array::set( $props, 'post', null, $post_ref );

			// Log the event.
			Logger::log_event( 'Comment Restored', $comment, null, $props );
		}
	}
}
