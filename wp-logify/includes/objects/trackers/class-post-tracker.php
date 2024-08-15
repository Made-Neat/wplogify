<?php
/**
 * Contains the Post_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Post;

/**
 * Class WP_Logify\Post_Tracker
 *
 * Provides tracking of events related to posts.
 */
class Post_Tracker extends Object_Tracker {

	/**
	 * Keep track of terms added to a post in a single request.
	 *
	 * @var array
	 */
	private static array $terms = array();

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Track post creation and update.
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 10, 3 );
		add_action( 'pre_post_update', array( __CLASS__, 'on_pre_post_update' ), 10, 2 );
		add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
		add_action( 'update_post_meta', array( __CLASS__, 'on_update_post_meta' ), 10, 4 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 10, 3 );

		// Track post deletion.
		add_action( 'before_delete_post', array( __CLASS__, 'on_before_delete_post' ), 10, 2 );
		add_action( 'delete_post', array( __CLASS__, 'on_delete_post' ), 10, 2 );

		// Track attachment of terms and posts.
		add_action( 'added_term_relationship', array( __CLASS__, 'on_added_term_relationship' ), 10, 3 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_wp_after_insert_post' ), 10, 4 );
		add_action( 'deleted_term_relationships', array( __CLASS__, 'on_deleted_term_relationships' ), 10, 3 );
	}

	/**
	 * Log the creation and update of a post.
	 *
	 * @param int     $post_id The ID of the post being saved.
	 * @param WP_Post $post The post object.
	 * @param bool    $update Whether this is an update or a new post.
	 */
	public static function on_save_post( int $post_id, WP_Post $post, bool $update ) {
		global $wpdb;

		// Ignore updates. We track post updates by tracking the creation of revisions, which
		// enables us to link to the compare revisions page.
		if ( $update ) {
			return;
		}

		// debug( 'on_save_post' );

		// Check if we're updating or creating.
		$creating = wp_is_post_revision( $post_id ) === false;

		// If we're updating, the $post variable refers to the new revision rather than the parent post.
		if ( ! $creating ) {
			// Record the ID and title of the new revision.
			$revision_id    = $post_id;
			$revision_title = $post->post_title;

			// Load the parent object.
			$post_id = $post->post_parent;
			$post    = Post_Utility::load( $post_id );

			// Replace changed content with object references.
			if ( ! empty( self::$properties['post_content'] ) ) {
				// For the old value, link to the revision (or show a deleted tag).
				self::$properties['post_content']->val = new Object_Reference( 'post', $revision_id, $revision_title );
				// For the new value, link to the edit page (or show a deleted tag).
				self::$properties['post_content']->new_val = new Object_Reference( 'post', $post_id, $post->post_title );
			}
		}

		// Check if we need to use the 'Created' verb.
		$created = $creating || $post->post_status === 'auto-draft';

		// Get the event type.
		$post_type  = Post_Utility::get_post_type_singular_name( $post->post_type );
		$event_type = $post_type . ( $created ? ' Created' : ' Updated' );

		// Log the event.
		Logger::log_event( $event_type, $post, null, self::$properties );
	}

	/**
	 * Make a note of the last modified datetime before the post is updated.
	 *
	 * @param int   $post_id The ID of the post being updated.
	 * @param array $data    The data for the post.
	 */
	public static function on_pre_post_update( int $post_id, array $data, ) {
		// debug( 'on_pre_post_update' );

		global $wpdb;

		// Record the current last modified date.
		Property::update_array( self::$properties, 'post_modified', $wpdb->posts, Post_Utility::get_last_modified_datetime( $post_id ) );
	}

	/**
	 * Track post update. This handler allows us to capture changed properties before the
	 * save_post handler is called, as that hook doesn't provide us with the before state.
	 *
	 * @param int     $post_id      Post ID.
	 * @param WP_Post $post_after   Post object following the update.
	 * @param WP_Post $post_before  Post object before the update.
	 */
	public static function on_post_updated( int $post_id, WP_Post $post_after, WP_Post $post_before ) {
		// debug( 'on_post_updated' );

		global $wpdb;

		// Add changes.
		foreach ( $post_before as $key => $value ) {
			// Skip the dates in the posts table, they're incorrect.
			if ( in_array( $key, array( 'post_date', 'post_date_gmt', 'post_modified_gmt' ), true ) ) {
				continue;
			}

			// Process old value into the correct type.
			$old_val = Types::process_database_value( $key, $value );

			// Special handling for the last modified datetime.
			if ( $key === 'post_modified' ) {
				$new_val = Post_Utility::get_last_modified_datetime( $post_id );
			} else {
				$new_val = Types::process_database_value( $key, $post_after->{$key} );
			}

			// Compare old and new values.
			if ( ! Types::are_equal( $old_val, $new_val ) ) {
				// Record change.
				Property::update_array( self::$properties, $key, $wpdb->posts, $old_val, $new_val );
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
	public static function on_update_post_meta( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ) {
		// debug( 'on_update_post_meta' );

		global $wpdb;

		// Get the current value.
		$current_value = get_post_meta( $post_id, $meta_key, true );

		// Process values into correct types.
		$val     = Types::process_database_value( $meta_key, $current_value );
		$new_val = Types::process_database_value( $meta_key, $meta_value );

		// Note the change, if any.
		if ( ! Types::are_equal( $val, $new_val ) ) {
			Property::update_array( self::$properties, $meta_key, $wpdb->postmeta, $val, $new_val );
		}
	}

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_transition_post_status( string $new_status, string $old_status, WP_Post $post ) {
		global $wpdb;

		// Ensure this is not a revision.
		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		// This event is triggered even when the status hasn't change, but we only need to log an
		// event if the status has changed.
		if ( $new_status === $old_status ) {
			return;
		}

		// Some status changes we don't care about.
		if ( in_array( $new_status, array( 'auto-draft', 'inherit' ), true ) ) {
			return;
		}

		// debug( 'on_transition_post_status' );

		// Get the event type.
		$post_type  = Post_Utility::get_post_type_singular_name( $post->post_type );
		$verb       = Post_Utility::get_status_transition_verb( $old_status, $new_status );
		$event_type = "$post_type $verb";

		// Update the properties to correctly show the status change.
		$props = array();
		Property::update_array( $props, 'post_status', $wpdb->posts, $old_status, $new_status );

		// If the post is scheduled for the future, let's show this information.
		$metas = array();
		if ( $new_status === 'future' ) {
			$scheduled_publish_datetime = DateTimes::create_datetime( $post->post_date );
			Eventmeta::update_array( $metas, 'when_to_publish', $scheduled_publish_datetime );
		}

		// Log the event.
		Logger::log_event( $event_type, $post, $metas, $props );
	}

	/**
	 * Fires before a post is deleted, at the start of wp_delete_post().
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post   Post object.
	 */
	public static function on_before_delete_post( int $post_id, WP_Post $post ) {
		// Ignore revisions.
		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		// debug( 'on_before_delete_post' );

		// Get the attached terms.
		$attached_terms = Post_Utility::get_attached_terms( $post_id );

		// If there weren't any, bail.
		if ( empty( $attached_terms ) ) {
			return;
		}

		// Add them to the eventmetas. One for each taxonomy.
		foreach ( $attached_terms as $taxonomy => $term_refs ) {
			// Get the taxonomy object.
			$taxonomy_obj = get_taxonomy( $taxonomy );

			// Create and add the event meta.
			$meta_key = 'attached_' . strtolower( $taxonomy_obj->labels->name );
			Eventmeta::update_array( self::$eventmetas, $meta_key, $term_refs );
		}
	}

	/**
	 * Log the deletion of a post.
	 *
	 * This method is called immediately before the post is deleted from the database.
	 *
	 * @param int     $post_id The ID of the post that was deleted.
	 * @param WP_Post $post The post object that was deleted.
	 */
	public static function on_delete_post( int $post_id, WP_Post $post ) {
		// Ensure this is not a revision.
		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		// debug( 'on_delete_post' );

		// Get the event type.
		$event_type = Post_Utility::get_post_type_singular_name( $post->post_type ) . ' Deleted';

		// Get all the post's properties, including metadata.
		$props = Post_Utility::get_properties( $post );

		// Log the event.
		Logger::log_event( $event_type, $post, self::$eventmetas, $props );
	}

	/**
	 * Fires immediately after an object-term relationship is added.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $tt_id     Term taxonomy ID.
	 * @param string $taxonomy  Taxonomy slug.
	 */
	public static function on_added_term_relationship( int $post_id, int $tt_id, string $taxonomy ) {
		// Ignore revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// debug( 'on_added_term_relationship' );

		// Remember the newly attached term.
		$term                                = Term_Utility::get_by_term_taxonomy_id( $tt_id );
		self::$terms[ $taxonomy ]['added'][] = Object_Reference::new_from_wp_object( $term );
	}

	/**
	 * Fires immediately after an object-term relationship is added.
	 *
	 * @param int    $post_id  The post ID.
	 * @param array  $tt_ids   An array of term taxonomy IDs.
	 * @param string $taxonomy The taxonomy slug.
	 */
	public static function on_deleted_term_relationships( int $post_id, array $tt_ids, string $taxonomy ) {
		// Ignore revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// debug( 'on_deleted_term_relationships' );

		// Convert the term_taxonomy IDs to Object_Reference objects.
		foreach ( $tt_ids as $tt_id ) {
			$term                                  = Term_Utility::get_by_term_taxonomy_id( $tt_id );
			self::$terms[ $taxonomy ]['removed'][] = Object_Reference::new_from_wp_object( $term );
		}
	}

	/**
	 * Fires immediately after an object-term relationship is added.
	 *
	 * @param int      $post_id     Post ID.
	 * @param WP_Post  $post        Post object.
	 * @param bool     $update      Whether this is an existing post being updated.
	 * @param ?WP_Post $post_before Null for new posts, the WP_Post object prior to the update for updated posts.
	 */
	public static function on_wp_after_insert_post( int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before ) {
		// Ignore revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// debug( 'on_wp_after_insert_post' );

		// Log the addition or removal of any taxonomy terms.
		if ( self::$terms ) {
			// Loop through the taxonomies and create a log entry for each.
			foreach ( self::$terms as $taxonomy => $term_changes ) {
				// Get some useful information.
				$terms_were_added   = empty( $term_changes['added'] ) ? 0 : count( $term_changes['added'] );
				$terms_were_removed = empty( $term_changes['removed'] ) ? 0 : count( $term_changes['removed'] );
				$total              = $terms_were_added + $terms_were_removed;

				// Get the taxonomy object and name.
				$taxonomy_obj  = get_taxonomy( $taxonomy );
				$taxonomy_name = $total === 1 ? $taxonomy_obj->labels->singular_name : $taxonomy_obj->labels->name;

				// Get event type verb.
				if ( $terms_were_added && $terms_were_removed ) {
					$verb = 'Updated';
				} elseif ( $terms_were_added ) {
					$verb = 'Added';
				} elseif ( $terms_were_removed ) {
					$verb = 'Removed';
				} else {
					// This shouldn't occur.
					continue;
				}

				// Get the event type.
				$post_type  = Post_Utility::get_post_type_singular_name( $post->post_type );
				$event_type = "$post_type $taxonomy_name $verb";

				// Collect eventmetas.
				$metas = array();

				// Show the added terms in the eventmetas.
				if ( $terms_were_added ) {
					$meta_key = 'added_' . strtolower( $taxonomy_name );
					Eventmeta::update_array( $metas, $meta_key, $term_changes['added'] );
				}

				// Show the removed terms in the eventmetas.
				if ( $terms_were_removed ) {
					$meta_key = 'removed_' . strtolower( $taxonomy_name );
					Eventmeta::update_array( $metas, $meta_key, $term_changes['removed'] );
				}

				// Log the event.
				Logger::log_event( $event_type, $post, $metas );
			}
		}
	}
}
