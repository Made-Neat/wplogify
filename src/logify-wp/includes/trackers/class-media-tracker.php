<?php
/**
 * Contains the Media_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use WP_Post;

/**
 * Class Logify_WP\Media_Tracker
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
	 * The media type of the object being created or updated.
	 *
	 * @var ?string
	 */
	private static ?string $media_type;

	/**
	 * Whether the event being developed is for creating a media object, or updating it.
	 *
	 * @var bool
	 */
	private static bool $creating = false;

	/**
	 * Whether we are re-using a previous "Attachment Updated" event.
	 *
	 * @var bool
	 */
	private static bool $reusing = false;

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		
		// Add or update media.
		add_action( 'add_attachment', [__NAMESPACE__.'\Async_Tracker','async_add_attachment'], 10, 1 );
		add_action( 'middle_add_attachment', array( __CLASS__, 'on_add_attachment' ), 10, 1 );
		
		add_action( 'add_post_meta', [__NAMESPACE__.'\Async_Tracker','async_add_post_meta'], 10, 3 );
		add_action( 'middle_add_post_meta', array( __CLASS__, 'on_add_post_meta' ), 10, 3 );
		
		add_action( 'update_post_meta', [__NAMESPACE__.'\Async_Tracker','async_update_post_meta'], 10, 4 );
		add_action( 'middle_update_post_meta', array( __CLASS__, 'on_update_post_meta' ), 10, 4 );
		
		add_action( 'attachment_updated', [__NAMESPACE__.'\Async_Tracker','async_attachment_updated'], 10, 3 );
		add_action( 'middle_attachment_updated', array( __CLASS__, 'on_attachment_updated' ), 10, 3 );
		
		// Delete media.
		add_action( 'delete_attachment', [__NAMESPACE__.'\Async_Tracker','async_delete_attachment'], 10, 2 );
		add_action( 'middle_delete_attachment', array( __CLASS__, 'on_delete_attachment' ), 10, 2 );
		
		// Shutdown.
		add_action( 'shutdown', [__NAMESPACE__.'\Async_Tracker','async_shutdown_media'], 10, 0 );
		add_action( 'middle_shutdown_media', array( __CLASS__, 'on_shutdown' ), 10, 0 );
	}

	/**
	 * Get the update or create media event. If it hasn't been created yet, do it now.
	 *
	 * @param int|WP_Post $post The attachment post the event is about.
	 * @return ?Event The event, if it could be found or created.
	 */
	private static function get_update_media_event( int|WP_Post $post ): ?Event {

		// $post = unserialize($s_post);
		// If we already have an event, return it.
		if ( self::$update_media_event ) {
			return self::$update_media_event;
		}

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

		// Get the user's most recent event. If it's equivalent, and was created within the past
		// few minutes, we'll add to it instead of creating a new one.
		$event = Event_Repository::get_most_recent_event();

		// Check if we can re-use this event.
		self::$reusing = $event &&
			$event->event_type === $event_type &&
			$event->object_type === 'post' &&
			$event->object_key === $post_id &&
			$event->when_happened->getTimestamp() > time() - 300;

		if ( ! self::$reusing ) {
			// Create a new event.
			$event = Event::create( $event_type, $post );

			// If the event could not be created, return null.
			if ( ! $event ) {
				return null;
			}
		}

		// Keep a reference to the event.
		self::$update_media_event = $event;

		return $event;
	}

	/**
	 * Fires once an attachment has been added.
	 *
	 * NOTE: Not sure if this is actually being triggered. It may depend on how the attachment is added.
	 *
	 * @param int $post_id Attachment ID.
	 */
	public static function on_add_attachment( int $post_id ) {
		
		Debug::info( __CLASS__, __FUNCTION__ );

		// Get the event.
		$event = self::get_update_media_event( $post_id );
		if ( ! $event ) {
			return;
		}

		// Update the event type. If this method is called, we're creating.
		$event->event_type = ucfirst( self::$media_type ) . ' Added';

		// Note that we're adding an attachment, rather than updating one.
		self::$creating = true;

		// It's possible some property changes have already been added to the event, for example 'filepath'.
		// We don't want these to be considered changes, as they're just the initial values.
		foreach ( $event->properties as $prop ) {
			if ( ! $prop->val && $prop->new_val ) {
				$prop->val     = $prop->new_val;
				$prop->new_val = null;
			}
		}
	}

	/**
	 * Fires immediately before a post meta is added.
	 *
	 * @param int    $post_id    ID of the post the metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value.
	 */
	public static function on_add_post_meta( int $post_id, string $meta_key, mixed $meta_value ) {
		Debug::info( __CLASS__, __FUNCTION__ );

		return self::on_update_post_meta( 0, $post_id, $meta_key, $meta_value );
	}

	/**
	 * Fires immediately before a post meta is updated.
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

		Debug::info( __CLASS__, __FUNCTION__, $media_type );

		// Get the event.
		$event = self::get_update_media_event( $post_id );
		if ( ! $event ) {
			return;
		}

		// Process the new value.
		$new_val = Types::process_database_value( $meta_key, $meta_value );

		if ( self::$creating ) {
			// If we're adding a new attachment, add the new value as the current value.
			$event->set_prop( $meta_key, $wpdb->postmeta, $new_val );
		} else {
			// Get the old or current value.
			$prop = $event->get_prop( $meta_key );
			if ( $prop ) {
				$val = $prop->val;
			} else {
				$meta_val = get_post_meta( $post_id, $meta_key, true );
				$val      = Types::process_database_value( $meta_key, $meta_val );
			}

			if ( ! Types::are_equal( $val, $new_val ) ) {
				// If there is a change, log the changed value.
				if ( $prop ) {
					$prop->new_val = $new_val;
				} else {
					$event->set_prop( $meta_key, $wpdb->postmeta, $val, $new_val );
				}
			} elseif ( $prop ) {
				// If there's now no change, remove the property from the event, if there is one.
				$event->remove_prop( $meta_key );
			}
		}
	}

	/**
	 * Fires once an existing attachment has been updated.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object following the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public static function on_attachment_updated( int $post_id, $serialize_post_after, $serialize_post_before ) {

		//unserialize post objects
		$post_after = unserialize($serialize_post_after);
		$post_before = unserialize($serialize_post_before);
		
		// Get the media type.

		$media_type = Media_Utility::get_media_type( $post_id );

		// This method is only for media.
		if ( ! $media_type ) {
			return;
		}

		Debug::info( __CLASS__, __FUNCTION__, $media_type );

		// Get the changes.
		// This will find both core and meta property changes, but no meta changes should be found
		// since they are handled separately in on_add_post_meta() and on_update_post_meta().
		$changed_props = Post_Utility::get_changes( $post_before, $post_after );

		// If there are no changes, we're done.
		if ( ! $changed_props ) {
			Debug::info( 'No changed properties found.' );
			return;
		}

		// Get the event.
		$event = self::get_update_media_event( $post_id );
		if ( ! $event ) {
			return;
		}

		if ( ! self::$reusing ) {
			// Add the changed properties.
			$event->add_props( $changed_props );
			return;
		}

		// The event already existede, so update the properties with the new changes.
		foreach ( $changed_props as $changed_prop ) {
			$key = $changed_prop->key;

			// Get the current/old value.
			// If we are re-using an event and there was already a property for this key, use
			// that value. Otherwise, use the changed value from this event.
			$existing_prop = self::$update_media_event->get_prop( $key );
			$val           = $existing_prop ? $existing_prop->val : $changed_prop->val;

			// Get the new value.
			$new_val = $changed_prop->new_val;

			// Check if there's a difference between the original value and the new one.
			if ( ! Types::are_equal( $val, $new_val ) ) {
				// They are different, so update/add the property.
				$event->set_prop( $key, $changed_prop->table_name, $val, $new_val );
			} else {
				// There is no difference now, so remove the property, if it's not a core property.
				if ( ! Post_Utility::is_core_property( $key ) ) {
					$event->remove_prop( $key );
				} else {
					$event->set_prop_new_val( $key, null );
				}
			}
		}
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
	public static function on_delete_attachment( int $post_id, $serialize_post ) {
		// This method is only for attachments.
		$post = unserialize($serialize_post);

		if ( get_post_type( $post_id ) !== 'attachment' ) {
			return;
		}

		// Get the media type.
		$media_type = Media_Utility::get_media_type( $post_id );

		// This method is only for media.
		if ( ! $media_type ) {
			return;
		}

		Debug::info( __CLASS__, __FUNCTION__, $media_type );

		// Get the event type.
		$event_type = ucfirst( $media_type ) . ' Deleted';

		// Create the event.
		$event = Event::create( $event_type, $post );

		// If the event could not be created, we aren't tracking this user.
		if ( ! $event ) {
			return;
		}

		// Add all the object's properties (including metadata), in case we want to restore it later.
		// NOTE: This will probably have to change when we update the data model to distinguish
		// NOTE: between properties we want to show for user information, vs. those we want to keep
		// NOTE: for undo actions.
		$event->add_props( Post_Utility::get_properties( $post ) );

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
		if ( self::$update_media_event ) {
			if ( self::$creating || self::$update_media_event->has_changes() ) {
				// If adding a new media object, or the event includes changes, save the event.
				self::$update_media_event->save();
			} elseif ( ! self::$update_media_event->is_new() && ! self::$update_media_event->has_changes() ) {
				// If this is an existing event, but there are no changes anymore, delete the event.
				self::$update_media_event->delete();
			}
		}
	}
}
