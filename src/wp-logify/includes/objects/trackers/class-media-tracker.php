<?php
/**
 * Contains the Media_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Post;

/**
 * Class WP_Logify\Media_Tracker
 *
 * Provides tracking of events related to media.
 */
class Media_Tracker {

	/**
	 * Event for creating or updating a media object.
	 *
	 * @var ?Event
	 */
	private static ?Event $update_media_event = null;

	/**
	 * If creating or updating a media event.
	 *
	 * @var bool
	 */
	private static bool $creating = false;

	/**
	 * The media type of the object being created or updated.
	 *
	 * @var ?string
	 */
	private static ?string $media_type;

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Add or update media.
		add_action( 'add_attachment', array( __CLASS__, 'on_add_attachment' ), 10, 1 );
		add_action( 'add_post_meta', array( __CLASS__, 'on_add_post_meta' ), 10, 3 );
		add_action( 'update_post_meta', array( __CLASS__, 'on_update_post_meta' ), 10, 4 );
		add_action( 'edit_attachment', array( __CLASS__, 'on_edit_attachment' ), 10, 3 );
		add_action( 'attachment_updated', array( __CLASS__, 'on_attachment_updated' ), 10, 3 );

		// Media upload.
		add_action( 'media_upload_image', array( __CLASS__, 'on_media_upload_image' ), 10, 0 );
		add_action( 'media_upload_audio', array( __CLASS__, 'on_media_upload_audio' ), 10, 0 );
		add_action( 'media_upload_video', array( __CLASS__, 'on_media_upload_video' ), 10, 0 );
		add_action( 'media_upload_file', array( __CLASS__, 'on_media_upload_file' ), 10, 0 );

		// Delete media.
		add_action( 'delete_attachment', array( __CLASS__, 'on_delete_attachment' ), 10, 2 );

		// Shutdown.
		add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 10, 0 );
	}

	/**
	 * Get the update or create media event. If it hasn't been created yet, do it now.
	 *
	 * @param int|WP_Post $post The attachment post the event is about.
	 * @return Event The event.
	 */
	private static function get_update_media_event( int|WP_Post $post ): Event {
		// Create the event if not done already.
		if ( ! self::$update_media_event ) {

			// Get the post object and ID.
			if ( is_int( $post ) ) {
				$post_id = $post;
				$post    = Post_Utility::load( $post_id );
			} else {
				$post_id = (int) $post->ID;
			}

			// Get the media type and remember it.
			self::$media_type = Media_Utility::get_media_type( $post_id );

			// Get the event type.
			$event_type = ucfirst( self::$media_type ) . ' Updated';

			// Create the event.
			self::$update_media_event = Event::create( $event_type, $post );
		}

		return self::$update_media_event;
	}

	/**
	 * Fires once an attachment has been added.
	 *
	 * NOTE: Not sure if this is actually being triggered. It may depend on how the attachment is added.
	 *
	 * @param int $post_id Attachment ID.
	 */
	public static function on_add_attachment( int $post_id ) {
		debug( 'on_add_attachment' );

		// We are adding a new attachment.
		self::$creating = true;

		// Get the event.
		$event = self::get_update_media_event( $post_id );

		// Update the event type. If this method is called, we're creating.
		$event->event_type = ucfirst( self::$media_type ) . ' Added';
	}

	/**
	 * Fires immediately before meta of a specific type is added.
	 *
	 * The dynamic portion of the hook name, `$meta_type`, refers to the meta object type
	 * (post, comment, term, user, or any other type with an associated meta table).
	 *
	 * Possible hook names include:
	 *
	 *  - `add_post_meta`
	 *  - `add_comment_meta`
	 *  - `add_term_meta`
	 *  - `add_user_meta`
	 *
	 * @since 3.1.0
	 *
	 * @param int    $post_id    ID of the post the metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value.
	 */
	public static function on_add_post_meta( int $post_id, string $meta_key, mixed $meta_value ) {
		global $wpdb;

		// Some changes are uninteresting.
		if ( in_array( $meta_key, array( '_edit_lock', '_edit_last' ) ) ) {
			return;
		}

		// Get the media type.
		$media_type = Media_Utility::get_media_type( $post_id );

		// This method is only for media.
		if ( ! $media_type ) {
			return;
		}

		debug( 'on_add_post_meta', $media_type );

		// Get the event.
		$event = self::get_update_media_event( $post_id );

		// Process the database value so it's displayed properly.
		$val = Types::process_database_value( $meta_key, $meta_value );

		// Add the metadata.
		$event->set_prop( $meta_key, $wpdb->postmeta, $val );
	}

	/**
	 * Track media meta update.
	 *
	 * @param int    $meta_id    The ID of the meta data.
	 * @param int    $post_id    The ID of the post.
	 * @param string $meta_key   The key of the meta data.
	 * @param mixed  $meta_value The new value of the meta data.
	 */
	public static function on_update_post_meta( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ) {
		global $wpdb;

		// Some changes are uninteresting.
		if ( in_array( $meta_key, array( '_edit_lock', '_edit_last' ) ) ) {
			return;
		}

		// Get the media type.
		$media_type = Media_Utility::get_media_type( $post_id );

		// This method is only for media.
		if ( ! $media_type ) {
			return;
		}

		debug( 'on_update_post_meta', $media_type );

		// Get the event.
		$event = self::get_update_media_event( $post_id );

		// Get the current value of this metadata.
		$current_val = get_post_meta( $post_id, $meta_key, true );

		// Process values.
		$val     = Types::process_database_value( $meta_key, $current_val );
		$new_val = Types::process_database_value( $meta_key, $meta_value );

		// Check for a difference.
		$diff = Types::get_diff( $val, $new_val );

		// If there is a change, log the changed value.
		if ( $diff ) {
			$event->set_prop( $meta_key, $wpdb->postmeta, $val, $new_val );
		}
	}

	/**
	 * Fires once an existing attachment has been updated.
	 *
	 * @param int $post_id Attachment ID.
	 */
	public static function on_edit_attachment( int $post_id ) {
		debug( 'on_edit_attachment' );
	}

	/**
	 * Fires once an existing attachment has been updated.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object following the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public static function on_attachment_updated( int $post_id, WP_Post $post_after, WP_Post $post_before ) {
		// Get the media type.
		$media_type = Media_Utility::get_media_type( $post_id );

		// This method is only for media.
		if ( ! $media_type ) {
			return;
		}

		debug( 'on_attachment_updated', $media_type );

		// Get the event.
		$event = self::get_update_media_event( $post_id );

		// Get the changes.
		$properties = Post_Utility::get_changes( $post_before, $post_after );

		// Add the changes to the event.
		$event->set_props( $properties );
	}

	public static function on_media_upload_image() {
		debug( 'on_media_upload_image' );
		// $event = Event::create( 'Image Upload', );
	}

	public static function on_media_upload_audio() {
		// $event = Event::create( 'Audio Upload', );
	}

	public static function on_media_upload_video() {
		// $event = Event::create( 'Video Upload', );
	}

	public static function on_media_upload_file() {
		// $event = Event::create( 'File Upload', );
	}

	// =============================================================================================
	// Media deletion.

	/**
	 * Log the deletion of an attachment.
	 * This hook is triggered at the start of the deletion process.
	 *
	 * @param int     $post_id The ID of the post to be deleted.
	 * @param WP_Post $post    The post object to be deleted.
	 */
	public static function on_delete_attachment( int $post_id, WP_Post $post ) {
		// This method is only for attachments.
		if ( get_post_type( $post_id ) !== 'attachment' ) {
			return;
		}

		// Get the media type.
		$media_type = Media_Utility::get_media_type( $post_id );

		// This method is only for media.
		if ( ! $media_type ) {
			return;
		}

		debug( 'on_delete_attachment', $media_type );

		// Get the event type.
		$event_type = ucfirst( $media_type ) . ' Deleted';

		// Create the event.
		$event = Event::create( $event_type, $post );

		// Add all the object's properties (including metadata), in case we want to restore it later.
		// NOTE: This will probably have to change when we update the data model to distinguish
		// NOTE: between properties we want to show for user information, vs. those we want to keep
		// NOTE: for undo actions.
		$event->set_props( Post_Utility::get_properties( $post ) );

		// Save the event to the log.
		$event->save();
	}

	// =============================================================================================
	// Shutdown.

	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown() {
		// Save the media updated or added event, if it exists.
		if ( self::$update_media_event && ( self::$creating || self::$update_media_event->num_changed_props() > 0 ) ) {
			self::$update_media_event->save();
		}
	}
}
