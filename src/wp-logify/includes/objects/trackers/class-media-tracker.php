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
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Add or update media.
		add_action( 'add_attachment', array( __CLASS__, 'on_add_attachment' ), 10, 1 );
		add_action( 'add_post_meta', array( __CLASS__, 'on_add_post_meta' ), 10, 3 );
		add_action( 'update_post_meta', array( __CLASS__, 'on_update_post_meta' ), 10, 4 );
		add_action( 'attachment_updated', array( __CLASS__, 'on_attachment_updated' ), 10, 3 );

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

			// Get the user's most recent event. If it's equivalent, and was created within the past
			// few minutes, we'll add to it instead of creating a new one.
			$event = Event_Repository::get_most_recent_event();

			// Check if we can use this event.
			$can_use = $event &&
				$event->event_type === $event_type &&
				$event->object_type === 'post' &&
				$event->object_key === $post_id &&
				$event->when_happened->getTimestamp() > time() - 300;

			if ( $can_use ) {
				self::$update_media_event = $event;
			} else {
				// Create the event.
				self::$update_media_event = Event::create( $event_type, $post );
			}
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

		// Get the event.
		$event = self::get_update_media_event( $post_id );

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

		debug( 'on_update_post_meta', $media_type );

		// Get the event.
		$event = self::get_update_media_event( $post_id );

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

			// Check for a difference.
			$diff = Types::get_diff( $val, $new_val );

			if ( $diff ) {
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
	public static function on_attachment_updated( int $post_id, WP_Post $post_after, WP_Post $post_before ) {
		// Get the media type.
		$media_type = Media_Utility::get_media_type( $post_id );

		// This method is only for media.
		if ( ! $media_type ) {
			return;
		}

		debug( 'on_attachment_updated', $media_type );

		// Get the changes.
		// This will find both core and meta property changes, but no meta changes should be found
		// since they are handled separately in on_add_post_meta() and on_update_post_meta().
		$changed_props = Post_Utility::get_changes( $post_before, $post_after );

		// If there are no changes, we're done.
		if ( ! $changed_props ) {
			return;
		}

		// Get the event.
		$event = self::get_update_media_event( $post_id );

		// Update the properties.
		foreach ( $changed_props as $changed_prop ) {
			// Check if there's already a property for this key.
			$existing_prop = $event->get_prop( $changed_prop->key );

			if ( $existing_prop ) {
				// Check if there's a difference between the original value and the new one.
				$val     = $existing_prop->val;
				$new_val = $changed_prop->new_val;
				if ( $val !== $new_val ) {
					// There is a difference, so update the property.
					$existing_prop->new_val = $changed_prop->new_val;
				} else {
					// There is no difference now, so remove the property.
					$event->remove_prop( $changed_prop->key );
				}
			} else {
				// There is no existing property, so add the new one.
				$event->add_prop( $changed_prop );
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
