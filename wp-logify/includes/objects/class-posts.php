<?php
/**
 * Contains the Posts class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;
use Exception;
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
	private static $changes = array();

	// ---------------------------------------------------------------------------------------------

	/**
	 * Link the events we want to log to methods.
	 */
	public static function init() {
		// Track post creation and update.
		add_action( 'save_post', array( __CLASS__, 'track_save' ), 10, 3 );
		add_action( 'post_updated', array( __CLASS__, 'track_update' ), 10, 3 );
		add_action( 'update_post_meta', array( __CLASS__, 'track_meta_update' ), 10, 4 );

		// Track post status changes.
		add_action( 'draft_to_publish', array( __CLASS__, 'track_publish' ), 10, 1 );
		add_action( 'publish_to_draft', array( __CLASS__, 'track_unpublish' ), 10, 1 );
		add_action( 'trashed_post', array( __CLASS__, 'track_trash' ), 10, 2 );
		add_action( 'untrashed_post', array( __CLASS__, 'track_untrash' ), 10, 2 );

		// Track post deletion.
		add_action( 'delete_post', array( __CLASS__, 'track_delete' ), 10, 2 );
	}

	// ---------------------------------------------------------------------------------------------
	// Tracking methods.

	/**
	 * Log the creation and update of a post.
	 *
	 * @param int     $post_id The ID of the post being saved.
	 * @param WP_Post $post The post object.
	 * @param bool    $update Whether this is an update or a new post.
	 */
	public static function track_save( $post_id, $post, $update ) {
		// Ignore updates. We track post updates by tracking the creation of revisions, which
		// enables us to link to the compare revisions page.
		if ( $update ) {
			return;
		}

		// Check if we're updating or creating.
		$creating = wp_is_post_revision( $post_id ) === false;

		// If creating, get all the post's properties.
		if ( $creating ) {
			$properties = self::get_properties( $post );
		} else {
			// We're updating, so just record the changed properties.

			// TODO I think this is wrong, we need to find the ID of the most recent revision.
			// We also need to handle the case of the revision being deleted. So this needs to be an object reference.
			$revision_id = $post_id;

			// Load the parent object.
			$post_id = $post->post_parent;
			$post    = self::get_post( $post_id );

			// Copy changes to the properties array.
			$properties = self::$changes;
			if ( ! empty( $properties['post_content'] ) ) {
				// For the old value, link to the revision (or show a deleted tag).
				$properties['post_content']->old_value = "<a href='" . admin_url( "/revision.php?revision={$revision_id}" ) . "'>View revision</a>";
				// For the new value, link to the edit page (or show a deleted tag).
				$properties['post_content']->new_value = new Object_Reference( 'post', $post_id, $post->post_title );
			}
		}

		// Get the event type.
		$event_type = self::get_post_type_singular_name( $post->post_type ) . ( $creating ? ' Created' : ' Updated' );

		// Log the event.
		Logger::log_event( $event_type, 'post', $post_id, $post->post_title, null, $properties );
	}

	/**
	 * Track post update.
	 *
	 * @param int     $post_id      Post ID.
	 * @param WP_Post $post_after   Post object following the update.
	 * @param WP_Post $post_before  Post object before the update.
	 */
	public static function track_update( $post_id, $post_after, $post_before ) {
		// Compare values.
		foreach ( $post_before as $key => $value ) {
			// Process values into correct types.
			$old_value = process_database_value( $key, $value );
			$new_value = process_database_value( $key, $post_after->{$key} );

			// Compare old and new values.
			if ( ! are_equal( $old_value, $new_value ) ) {
				// Record change.
				self::$changes[ $key ] = array( $old_value, $new_value );
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
	public static function track_meta_update( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ) {
		// Get the current value.
		$current_value = get_post_meta( $post_id, $meta_key, true );

		// Process values into correct types.
		$old_value = process_database_value( $meta_key, $current_value );
		$new_value = process_database_value( $meta_key, $meta_value );

		// Note the change, if any.
		if ( ! are_equal( $old_value, $new_value ) ) {
			self::$changes[ $meta_key ] = array( $old_value, $new_value );
		}
	}

	/**
	 * Log a post status change.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @param string      $event_type_verb The verb to use in the event type.
	 * @param string      $old_status The old status of the post.
	 * @param bool        $deleted Whether the post was deleted.
	 */
	private static function track_status_change( WP_Post|int $post, string $event_type_verb, string $old_status, bool $deleted = false ) {
		// Ensure this is not a revision.
		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = self::get_post( $post );
		}

		// Get the event type.
		$event_type = self::get_post_type_singular_name( $post->post_type ) . ' ' . $event_type_verb;

		// Get the post's properties.
		$properties = self::get_properties( $post );

		// Modify the properties to properly show the status change.
		$properties['post_status']->old_value = $old_status;
		if ( ! $deleted ) {
			$properties['post_status']->new_value = $post->post_status;
		}

		// Log the event.
		Logger::log_event( $event_type, 'post', $post->ID, $post->post_title, null, $properties );
	}

	/**
	 * Log the publishing of a post.
	 *
	 * @param WP_Post $post The post object that was published.
	 */
	public static function track_publish( WP_Post $post ) {
		self::track_status_change( $post, 'Published', 'draft' );
	}

	/**
	 * Log the unpublishing of a post.
	 *
	 * @param WP_Post $post The post object that was published.
	 */
	public static function track_unpublish( WP_Post $post ) {
		self::track_status_change( $post, 'Unpublished', 'publish' );
	}

	/**
	 * Log the trashing of a post.
	 *
	 * @param int    $post_id The ID of the post that was deleted.
	 * @param string $previous_status The previous status of the post.
	 */
	public static function track_trash( int $post_id, string $previous_status ) {
		self::track_status_change( $post_id, 'Trashed', $previous_status );
	}

	/**
	 * Log the restoring of a post.
	 *
	 * @param int    $post_id The ID of the post that was deleted.
	 * @param string $previous_status The previous status of the post.
	 */
	public static function track_untrash( int $post_id, string $previous_status ) {
		self::track_status_change( $post_id, 'Restored', 'trash' );
	}

	/**
	 * Log the deletion of a post.
	 *
	 * @param int     $post_id The ID of the post that was deleted.
	 * @param WP_Post $post The post object that was deleted.
	 */
	public static function track_delete( int $post_id, WP_Post $post ) {
		self::track_status_change( $post, 'Deleted', $post->post_status, true );
	}

	// ---------------------------------------------------------------------------------------------
	// Get post information.

	/**
	 * Check if a post exists.
	 *
	 * @param int $post_id The ID of the post.
	 * @return bool True if the post exists, false otherwise.
	 */
	public static function post_exists( int $post_id ): bool {
		global $wpdb;
		$sql   = $wpdb->prepare( 'SELECT COUNT(ID) FROM %i WHERE ID = %d', $wpdb->posts, $post_id );
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}

	/**
	 * Get a post by ID.
	 *
	 * @param int $post_id The ID of the post.
	 * @return WP_Post The post object.
	 * @throws Exception If the post could not be loaded.
	 */
	public static function get_post( int $post_id ): WP_Post {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new Exception( "Post $post_id could not be loaded." );
		}
		return $post;
	}

	/**
	 * Get the URL for a post's edit page.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return string The edit page URL.
	 */
	public static function get_edit_url( WP_Post|int $post ) {
		$post_id = is_int( $post ) ? $post : $post->ID;
		return admin_url( "post.php?post=$post_id&action=edit" );
	}

	/**
	 * Get the HTML for the link to the object's edit page.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return string The link HTML tag.
	 */
	public static function get_edit_link( WP_Post|int $post ) {
		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = self::get_post( $post );
		}

		// Get the URL for the post's edit page.
		$url = self::get_edit_url( $post );

		// Return the link.
		return "<a href='$url' class='wp-logify-post-link'>$post->post_title</a>";
	}

	/**
	 * If the post hasn't been deleted, get a link to its edit page; otherwise, get a span with
	 * the old title as the link text.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @param string      $old_title The old title of the post.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( WP_Post|int $post, string $old_title ) {
		// Check if the post exists.
		if ( self::post_exists( $post ) ) {

			// Load the post if necessary.
			if ( is_int( $post ) ) {
				$post = self::get_post( $post );
			}

			// If the post is trashed, we can't reach its edit page, so instead we'll link to the list of trashed posts.
			if ( $post->post_status === 'trash' ) {
				$url = admin_url( 'edit.php?post_status=trash&post_type=post' );
			} else {
				$url = self::get_edit_url( $post );
			}

			return "<a href='$url' class='wp-logify-post-link'>$post->post_title</a>";
		}

		// The post no longer exists. Construct the 'deleted' span element.
		$post_id = is_int( $post ) ? $post : $post->ID;
		$title   = empty( $old_title ) ? "Post $post_id" : $old_title;
		return "<span class='wp-logify-deleted-object'>$title (deleted)</span>";
	}

	/**
	 * Get the singular name of a custom post type.
	 *
	 * @param string $post_type The post type.
	 * @return string The singular name of the post type.
	 */
	public static function get_post_type_singular_name( string $post_type ): string {
		// Get the post type object.
		$post_type_object = get_post_type_object( $post_type );

		// Return the singular name if set.
		if ( $post_type_object && isset( $post_type_object->labels->singular_name ) ) {
			return $post_type_object->labels->singular_name;
		}

		// Otherwise default to the key.
		return $post_type_object->name;
	}

	/**
	 * Get the datetime a post was created.
	 *
	 * This function ignores the post_date and post_date_gmt fields in the parent post record, which
	 * seem to show the last time the post was updated, not the time it was created.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return DateTime The datetime the post was created.
	 */
	public static function get_created_datetime( WP_Post|int $post ): DateTime {
		global $wpdb;

		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = self::get_post( $post );
		}

		// Construct the SQL.
		$table_name = $wpdb->prefix . 'posts';
		$sql        = $wpdb->prepare(
			"SELECT MIN(post_date) FROM %i WHERE (ID=%d OR post_parent=%d) AND post_date != '0000-00-00 00:00:00'",
			$table_name,
			$post->ID,
			$post->ID
		);

		// Get the created datetime.
		$created_datetime = $wpdb->get_var( $sql );
		return DateTimes::create_datetime( $created_datetime );
	}

	/**
	 * Get the datetime a post was last modified.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return DateTime The datetime the post was last modified.
	 */
	public static function get_last_modified_datetime( WP_Post|int $post ): DateTime {
		global $wpdb;

		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = self::get_post( $post );
		}

		// Construct the SQL.
		$table_name = $wpdb->prefix . 'posts';
		$sql        = $wpdb->prepare(
			"SELECT MAX(post_modified) FROM %i WHERE (ID=%d OR post_parent=%d) AND post_modified != '0000-00-00 00:00:00'",
			$table_name,
			$post->ID,
			$post->ID
		);

		// Get the last modified datetime.
		$last_modified_datetime = $wpdb->get_var( $sql );
		return DateTimes::create_datetime( $last_modified_datetime );
	}

	/**
	 * Get the properties of a post.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return array The properties of the post.
	 */
	private static function get_properties( WP_Post|int $post ): array {
		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = self::get_post( $post );
		}

		// Start building the properties array.
		$properties = array();

		// Add the base properties.
		foreach ( $post as $key => $value ) {
			// Process meta values into correct types.
			$value = process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			$properties[ $key ] = new Property( $key, 'base', $value );
		}

		// Add the meta properties.
		$postmeta = get_post_meta( $post->ID );
		foreach ( $postmeta as $key => $value ) {
			// Process meta values into correct types.
			$value = process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			$properties[ $key ] = new Property( $key, 'meta', $value );
		}

		return $properties;
	}
}
