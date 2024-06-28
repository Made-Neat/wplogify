<?php
/**
 * Contains the Posts class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;
use WP_Post;

/**
 * Class WP_Logify\Posts
 *
 * Provides tracking of events related to posts.
 */
class Posts {

	/**
	 * Changes to a post.
	 *
	 * @var array
	 */
	private static $post_changes = array();

	/**
	 * Link the events we want to log to methods.
	 */
	public static function init() {
		// Track post creation and update.
		add_action( 'save_post', array( __CLASS__, 'track_post_save' ), 10, 3 );
		add_action( 'post_updated', array( __CLASS__, 'track_post_update' ), 10, 3 );
		add_action( 'update_post_meta', array( __CLASS__, 'track_post_meta_update' ), 10, 4 );

		// Track post status changes.
		add_action( 'draft_to_publish', array( __CLASS__, 'track_post_publish' ), 10, 1 );
		add_action( 'publish_to_draft', array( __CLASS__, 'track_post_unpublish' ), 10, 1 );
		add_action( 'trashed_post', array( __CLASS__, 'track_post_trash' ), 10, 2 );
		add_action( 'untrashed_post', array( __CLASS__, 'track_post_untrash' ), 10, 2 );

		// Track post deletion.
		add_action( 'delete_post', array( __CLASS__, 'track_post_delete' ), 10, 2 );
	}

	// =============================================================================================
	// Tracking methods.

	/**
	 * Log the creation and update of a post.
	 *
	 * @param int     $post_id The ID of the post being saved.
	 * @param WP_Post $post The post object.
	 * @param bool    $update Whether this is an update or a new post.
	 */
	public static function track_post_save( $post_id, $post, $update ) {
		// Ignore updates. We track post updates by tracking the creation of revisions, which
		// enables us to link to the compare revisions page.
		if ( $update ) {
			return;
		}

		// Check if a new post revision was created (meaning the post was updated).
		$is_revision = wp_is_post_revision( $post_id ) !== false;
		if ( $is_revision ) {
			// Load the parent object.
			$post_id = $post->post_parent;
			$post    = get_post( $post_id );
		}

		// Collect details.
		$details = self::get_post_details( $post );

		// Modify post changes as needed.
		if ( ! empty( self::$post_changes ) ) {
			// Replaces changes to the content with a link to the compare revisions page.
			if ( ! empty( self::$post_changes['post_content'] ) ) {
				$revisions                          = wp_get_post_revisions( $post );
				$most_recent_revision               = array_shift( $revisions );
				self::$post_changes['post_content'] = "<a href='" . admin_url( "/revision.php?revision={$most_recent_revision->ID}" ) . "'>Compare revisions</a>";
			}

			// Remove dates from changes. The relevant dates are shown in the event details.
			unset( self::$post_changes['post_date'] );
			unset( self::$post_changes['post_date_gmt'] );
			unset( self::$post_changes['post_modified'] );
			unset( self::$post_changes['post_modified_gmt'] );
		}

		// Get the event type.
		$event_type = self::get_post_type_singular_name( $details['Post type'] ) . ( $is_revision ? ' Updated' : ' Created' );

		// Log the event.
		Logger::log_event( $event_type, 'post', $post_id, $post->post_title, $details, self::$post_changes );
	}

	/**
	 * Track post update.
	 *
	 * @param int     $post_id      Post ID.
	 * @param WP_Post $post_after   Post object following the update.
	 * @param WP_Post $post_before  Post object before the update.
	 */
	public static function track_post_update( $post_id, $post_after, $post_before ) {
		// Compare values.
		foreach ( $post_before as $key => $value ) {
			$old_value = value_to_string( $value );
			$new_value = value_to_string( $post_after->{$key} );

			if ( $old_value !== $new_value ) {
				self::$post_changes[ $key ] = array( $old_value, $new_value );
			}
		}
	}

	/**
	 * Track post meta update.
	 *
	 * @param int    $meta_id    The ID of the meta data.
	 * @param int    $post_id    The ID of the post.
	 * @param string $meta_key   The key of the meta data.
	 * @param mixed  $meta_value The new value of the meta data.
	 */
	public static function track_post_meta_update( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ) {
		// Get the current value.
		$current_value = get_post_meta( $post_id, $meta_key, true );

		// Get the old and new values as strings for comparison and display.
		$old_value = value_to_string( $current_value );
		$new_value = value_to_string( $meta_value );

		// Track the change, if any.
		if ( $old_value !== $new_value ) {
			self::$post_changes[ $meta_key ] = array( $old_value, $new_value );
		}
	}

	/**
	 * Log a post status change.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @param string      $event_type_verb The verb to use in the event type.
	 * @param string      $old_status The old status of the post.
	 * @param string      $new_status The new status of the post.
	 */
	private static function track_post_status_change( WP_Post|int $post, string $event_type_verb, string $old_status, string $new_status = null ) {
		// Ensure this is not a revision.
		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}

		// Get the event type.
		$event_type = self::get_post_type_singular_name( $post->post_type ) . ' ' . $event_type_verb;

		// Get the new status if unspecified.
		if ( $new_status === null ) {
			$new_status = $post->post_status;
		}

		// Collect details.
		$details           = self::get_post_details( $post );
		$details['Status'] = $new_status;

		// Get the object changes.
		$changes['post_status'] = array( $old_status, $new_status );

		// Log the event.
		Logger::log_event( $event_type, 'post', $post->ID, $post->post_title, $details, $changes );
	}

	/**
	 * Log the publishing of a post.
	 *
	 * @param WP_Post $post The post object that was published.
	 */
	public static function track_post_publish( WP_Post $post ) {
		self::track_post_status_change( $post, 'Published', 'draft' );
	}

	/**
	 * Log the unpublishing of a post.
	 *
	 * @param WP_Post $post The post object that was published.
	 */
	public static function track_post_unpublish( WP_Post $post ) {
		self::track_post_status_change( $post, 'Unpublished', 'publish' );
	}

	/**
	 * Log the trashing of a post.
	 *
	 * @param int    $post_id The ID of the post that was deleted.
	 * @param string $previous_status The previous status of the post.
	 */
	public static function track_post_trash( int $post_id, string $previous_status ) {
		self::track_post_status_change( $post_id, 'Trashed', $previous_status );
	}

	/**
	 * Log the restoring of a post.
	 *
	 * @param int    $post_id The ID of the post that was deleted.
	 * @param string $previous_status The previous status of the post.
	 */
	public static function track_post_untrash( int $post_id, string $previous_status ) {
		self::track_post_status_change( $post_id, 'Restored', 'trash' );
	}

	/**
	 * Log the deletion of a post.
	 *
	 * @param int     $post_id The ID of the post that was deleted.
	 * @param WP_Post $post The post object that was deleted.
	 */
	public static function track_post_delete( int $post_id, WP_Post $post ) {
		self::track_post_status_change( $post, 'Deleted', $post->post_status, 'deleted' );
	}

	// =============================================================================================

	/**
	 * Get the singular name of a custom post type.
	 *
	 * @param string $post_type The post type.
	 * @return string The singular name of the post type.
	 */
	public static function get_post_type_singular_name( string $post_type ): string {
		$post_type_object = get_post_type_object( $post_type );
		if ( $post_type_object && isset( $post_type_object->labels->singular_name ) ) {
			return $post_type_object->labels->singular_name;
		}
		return '';
	}

	/**
	 * Get the datetime a post was created.
	 *
	 * This function ignores the post_date and post_date_gmt fields in the parent post record, which
	 * seem to show the last time the post was updated, not the time it was created.
	 *
	 * @param WP_Post $post The post object.
	 * @return DateTime The datetime the post was created.
	 */
	public static function get_post_created_datetime( WP_Post $post ): DateTime {
		global $wpdb;
		$table_name       = $wpdb->prefix . 'posts';
		$sql              = $wpdb->prepare(
			"SELECT MIN(post_date) FROM %i WHERE (ID=%d OR post_parent=%d) AND post_date != '0000-00-00 00:00:00'",
			$table_name,
			$post->ID,
			$post->ID
		);
		$created_datetime = $wpdb->get_var( $sql );
		return DateTimes::create_datetime( $created_datetime );
	}

	/**
	 * Get the datetime a post was last modified.
	 *
	 * @param WP_Post $post The post object.
	 * @return DateTime The datetime the post was last modified.
	 */
	public static function get_post_last_modified_datetime( WP_Post $post ): DateTime {
		global $wpdb;
		$table_name             = $wpdb->prefix . 'posts';
		$sql                    = $wpdb->prepare(
			"SELECT MAX(post_modified) FROM %i WHERE (ID=%d OR post_parent=%d) AND post_modified != '0000-00-00 00:00:00'",
			$table_name,
			$post->ID,
			$post->ID
		);
		$last_modified_datetime = $wpdb->get_var( $sql );
		return DateTimes::create_datetime( $last_modified_datetime );
	}

	/**
	 * Get the details of a post to show in the log.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return array The details of the post.
	 */
	private static function get_post_details( WP_Post|int $post ): array {
		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}

		// Create the details array.
		return array(
			'Post ID'       => $post->ID,
			'Post type'     => $post->post_type,
			'Author'        => Users::get_user_profile_link( $post->post_author ),
			'Status'        => $post->post_status,
			'Created'       => DateTimes::format_datetime_site( self::get_post_created_datetime( $post ), true ),
			'Last modified' => DateTimes::format_datetime_site( self::get_post_last_modified_datetime( $post ), true ),
		);
	}
}
