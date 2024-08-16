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
class Comment_Tracker extends Object_Tracker {

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
		foreach ( $comment as $key => $old_value ) {
			// Ignore post fields.
			if ( $key === 'post_fields' ) {
				continue;
			}

			// Compare the old and new values.
			$old_value = Types::process_database_value( $key, $old_value );
			$new_value = Types::process_database_value( $key, $commentarr[ $key ] );

			if ( ! Types::are_equal( $old_value, $new_value ) ) {
				Property::update_array( self::$properties, $key, $wpdb->comments, $old_value, $new_value );
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
		} elseif ( $old_status === 'spam' ) {
			$verb = 'Unmarked as Spam';
		} else {
			// Get the event type.
			$verb = match ( $new_status ) {
				'approved'     => 'Approved',
				'unapproved'   => 'Unapproved',
				'spam'         => 'Marked as Spam',
				'trash'        => 'Trashed',
				'post-trashed' => 'Trashed',
				'untrash'      => 'Untrashed',
				default        => 'Status Changed',
			};
		}
		$event_type = "Comment $verb";

		// Store the old and new status in a property.
		$props = array();
		Property::update_array( $props, 'status', null, $old_status, $new_status );

		Logger::log_event( $event_type, $comment, null, $props );
	}
}
