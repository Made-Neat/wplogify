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
	 * Array to remember properties between different events.
	 *
	 * @var array
	 */
	protected static $properties = array();

	private static $update_media_event = null;

	private static $is_new = false;

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Add attachment.
		add_action( 'add_attachment', array( __CLASS__, 'on_add_attachment' ), 10, 1 );
		add_action( 'add_post_meta', array( __CLASS__, 'on_add_post_meta' ), 10, 3 );
		add_action( 'edit_attachment', array( __CLASS__, 'on_edit_attachment' ), 10, 3 );
		add_action( 'attachment_updated', array( __CLASS__, 'on_attachment_updated' ), 10, 3 );
		// wp_ajax_save-attachment

		// Media upload.
		add_action( 'media_upload_image', array( __CLASS__, 'on_media_upload_image' ), 10, 0 );
		add_action( 'media_upload_audio', array( __CLASS__, 'on_media_upload_audio' ), 10, 0 );
		add_action( 'media_upload_video', array( __CLASS__, 'on_media_upload_video' ), 10, 0 );
		add_action( 'media_upload_file', array( __CLASS__, 'on_media_upload_file' ), 10, 0 );

		// Shutdown.
		add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 10, 0 );
	}

	/**
	 * Fires once an attachment has been added.
	 *
	 * NOTE: Not sure if this is actually being triggered. It may depend on how the attachment is added.
	 *
	 * @param int $post_id Attachment ID.
	 */
	public static function on_add_attachment( int $post_id ) {
		global $wpdb;

		debug( 'on_add_attachment' );

		// We are adding a new attachment.
		self::$is_new = true;

		// Get the media type and event type.
		$media_type = Post_Utility::get_media_type( $post_id );

		// If there's no media type, it's not an attachment. Shouldn't happen.
		if ( ! $media_type ) {
			return;
		}

		// Get the event type.
		$event_type = ucfirst( $media_type ) . ' Added';

		// Check if an event has been created already.
		if ( ! self::$update_media_event ) {
			// Create the event.
			$post                     = Post_Utility::load( $post_id );
			self::$update_media_event = Event::create( $event_type, $post );
		} else {
			// Update the event type.
			self::$update_media_event->event_type = $event_type;

			// Remove old property values.
			foreach ( self::$update_media_event->properties as $prop ) {
				if ( ! $prop->val && $prop->new_val ) {
					$prop->val     = $prop->new_val;
					$prop->new_val = null;
				}
			}
		}
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

		// Get the media type and event type.
		$media_type = Post_Utility::get_media_type( $post_id );

		// If there's no media type, it's not an attachment.
		if ( ! $media_type ) {
			return;
		}

		debug( 'on_add_post_meta', $media_type );

		// If no event has been created yet, create one now.
		if ( ! self::$update_media_event ) {
			$event_type               = ucfirst( $media_type ) . ' Updated';
			$post                     = Post_Utility::load( $post_id );
			self::$update_media_event = Event::create( $event_type, $post );
		}

		// Process the database value so it's displayed properly.
		$new_val = Types::process_database_value( $meta_key, $meta_value );

		// Add the metadata.
		if ( self::$is_new ) {
			// Log the new value.
			self::$update_media_event->set_prop( $meta_key, $wpdb->postmeta, $new_val );
		} else {
			// Get the current value of this metadata.
			$current_val = get_post_meta( $post_id, $meta_key, true );
			$val         = Types::process_database_value( $meta_key, $current_val );

			// Log the changed value.
			self::$update_media_event->set_prop( $meta_key, $wpdb->postmeta, $val, $new_val );
		}
	}

	/**
	 * Fires once an existing attachment has been updated.
	 *
	 * @param int $post_id Attachment ID.
	 */
	public static function on_edit_attachment( int $post_id ) {
		// debug( 'on_edit_attachment' );
	}

	/**
	 * Fires once an existing attachment has been updated.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object following the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public static function on_attachment_updated( int $post_id, WP_Post $post_after, WP_Post $post_before ) {
		debug( 'on_attachment_updated' );

		// Get the media type and event type.
		$media_type = Post_Utility::get_media_type( $post_id );

		// If there's no media type, it's not an attachment.
		if ( ! $media_type ) {
			return;
		}

		debug( 'media type', $media_type );

		// If no event has been created yet, create one now.
		if ( ! self::$update_media_event ) {
			$event_type               = ucfirst( $media_type ) . ' Updated';
			self::$update_media_event = Event::create( $event_type, $post_after );
		}

		// Get the changes.
		$properties = Post_Utility::get_changes( $post_before, $post_after );

		// Update the name of the caption property.
		// TODO This may only be relevant for images, need to test.
		$prop = Property::get_from_array( $properties, 'post_excerpt' );
		if ( $prop ) {
			$prop->key = 'caption';
		}

		// Add the changes to the event.
		self::$update_media_event->set_props( $properties );
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

	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown() {
		// Save the media updated or added event, if it exists.
		if ( self::$update_media_event && ( self::$is_new || self::$update_media_event->num_changed_props() > 0 ) ) {
			self::$update_media_event->save();
		}
	}
}
