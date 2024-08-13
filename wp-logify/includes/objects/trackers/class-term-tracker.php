<?php
/**
 * Contains the Term_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;
use WP_Term;

/**
 * Class WP_Logify\Term_Tracker
 *
 * Provides tracking of events related to terms.
 */
class Term_Tracker extends Object_Tracker {

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		add_action( 'created_term', array( __CLASS__, 'on_created_term' ), 10, 4 );
		add_action( 'edit_terms', array( __CLASS__, 'on_edit_terms' ), 10, 3 );
		add_action( 'pre_delete_term', array( __CLASS__, 'on_pre_delete_term' ), 10, 2 );
	}

	// =============================================================================================
	// Event handlers.

	/**
	 * Track the creation of a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_insert_term().
	 */
	public static function on_created_term( int $term_id, int $tt_id, string $taxonomy, array $args ) {
		// Load the term.
		$term = Term_Utility::load( $term_id );

		// Get the event type.
		$taxonomy_name = Term_Utility::get_taxonomy_singular_name( $taxonomy );
		$event_type    = "$taxonomy_name Created";

		// Log the event.
		Logger::log_event( $event_type, $term );
	}

	/**
	 * Track the edit of a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	public static function on_edit_terms( int $term_id, string $taxonomy, array $args ) {
		global $wpdb;

		// Load the term.
		$term = Term_Utility::load( $term_id );

		// Compare values.
		$changed = false;
		foreach ( $term->data as $key => $value ) {
			// Get the old and new values.
			$val     = Types::process_database_value( $key, $value );
			$new_val = Types::process_database_value( $key, $args[ $key ] );

			if ( ! Types::are_equal( $val, $new_val ) ) {

				// For parent, change to object references.
				if ( $key === 'parent' ) {
					$val     = $val ? new Object_Reference( 'term', $val ) : null;
					$new_val = $new_val ? new Object_Reference( 'term', $new_val ) : null;
				}

				// Update the property's before and after values.
				Property::update_array( self::$properties, $key, $wpdb->terms, $val, $new_val );

				// Note there were changes.
				$changed = true;
			}
		}

		if ( $changed ) {
			// Get the event type.
			$taxonomy_name = Term_Utility::get_taxonomy_singular_name( $taxonomy );
			$event_type    = "$taxonomy_name Updated";

			// Log the event.
			Logger::log_event( $event_type, $term, null, self::$properties );
		}
	}

	/**
	 * Track the deletion of a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @throws Exception If the term could not be retrieved.
	 */
	public static function on_pre_delete_term( int $term_id, string $taxonomy ) {
		global $wpdb;

		// Get the term.
		$term = Term_Utility::load( $term_id );

		// Get the event type.
		$taxonomy_name = Term_Utility::get_taxonomy_singular_name( $taxonomy );
		$event_type    = "$taxonomy_name Deleted";

		// Find all posts tagged with this term, in case we need to restore the term.
		$post_ids = get_objects_in_term( $term_id, $taxonomy );

		// Handle error if the posts could not be retrieved.
		if ( is_wp_error( $post_ids ) ) {
			throw new Exception( "Failed to retrieve posts with term ID $term_id in taxonomy $taxonomy." );
		}

		// Convert the array of post IDs to an array of Object_Reference objects.
		$post_ids = array_map( fn( $post_id ) => new Object_Reference( 'post', $post_id ), $post_ids );

		// Add to properties.
		$metas = array();
		Eventmeta::update_array( $metas, 'attached_posts', $post_ids );

		// Log the event.
		Logger::log_event( $event_type, $term, $metas );
	}
}
