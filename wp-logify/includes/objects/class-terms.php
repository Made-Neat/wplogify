<?php
/**
 * Contains the Terms class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;
use InvalidArgumentException;
use WP_Term;

/**
 * Class WP_Logify\Posts
 *
 * Provides tracking of events related to terms.
 */
class Terms {

	/**
	 * Changes to a term.
	 *
	 * @var array
	 */
	private static $term_changes = array();

	/**
	 * Link the events we want to log to methods.
	 */
	public static function init() {
		add_action( 'created_term', array( __CLASS__, 'track_create' ), 10, 4 );
		add_action( 'edit_terms', array( __CLASS__, 'track_edit' ), 10, 3 );
		add_action( 'pre_delete_term', array( __CLASS__, 'track_delete' ), 10, 2 );
		add_action( 'edit_term_taxonomies', array( __CLASS__, 'track_edit_term_taxonomy' ), 10, 1 );
	}

	/**
	 * Track the creation of a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_insert_term().
	 */
	public static function track_create( int $term_id, int $tt_id, string $taxonomy, array $args ) {
		// Load the term.
		$term = self::get_term( $term_id );

		// Get the event type.
		$event_type = ucwords( $taxonomy ) . ' Created';

		// Get the term properties.
		$properties = self::get_properties( $term );

		// Log the event.
		Logger::log_event( $event_type, 'term', $term_id, $term->name, null, $properties );
	}

	/**
	 * Track the edit of a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	public static function track_edit( int $term_id, string $taxonomy, array $args ) {
		debug( func_get_args() );

		// Load the term.
		$term = self::get_term( $term_id );

		// Get the term properties.
		$properties = self::get_properties( $term );

		// Compare values.
		$changed = false;
		foreach ( $term->data as $key => $value ) {
			if ( value_to_string( $value ) !== value_to_string( $args[ $key ] ) ) {
				// Update the property's before and after values.
				$properties[ $key ]->old_value = $value;
				$properties[ $key ]->new_value = $args[ $key ];

				// Note there were changes.
				$changed = true;
			}
		}

		if ( $changed ) {
			// Get the event type.
			$event_type = ucwords( $taxonomy ) . ' Updated';

			// Log the event.
			Logger::log_event( $event_type, 'term', $term_id, $term->name, null, $properties );
		}
	}

	/**
	 * Track the deletion of a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @throws Exception If the term could not be retrieved.
	 */
	public static function track_delete( int $term_id, string $taxonomy ) {
		// Get the term.
		$term = get_term( $term_id, $taxonomy );

		// Handle error if the term could not be retrieved.
		if ( is_wp_error( $term ) ) {
			throw new Exception( "Failed to retrieve term with ID $term_id in taxonomy $taxonomy." );
		}

		// Get the event type.
		$event_type = ucwords( $taxonomy ) . ' Deleted';

		// Get the term's properties.
		$properties = self::get_properties( $term );

		// Record all posts tagged with this term, in case we need to restore the term.
		$post_ids = get_objects_in_term( $term_id, $taxonomy );

		// Handle error if the posts could not be retrieved.
		if ( is_wp_error( $post_ids ) ) {
			throw new Exception( "Failed to retrieve posts with term ID $term_id in taxonomy $taxonomy." );
		}

		// The function returns an array of strings for some reason, so let's convert them to ints.
		$post_ids = array_map( fn( $post_id ) => (int) $post_id, $post_ids );

		// Add to the term's properties.
		$properties['posts'] = Property::create( null, 'posts', null, $post_ids );

		// Log the event.
		Logger::log_event( $event_type, 'term', $term_id, $term->name, null, $properties );
	}

	/**
	 * Track the edit of a term.
	 *
	 * @param array $edit_tt_ids An array of term taxonomy IDs for the given term.
	 */
	public static function track_edit_term_taxonomy( array $edit_tt_ids ) {
		debug( 'track_edit_term_taxonomy', func_get_args() );
	}

	// ---------------------------------------------------------------------------------------------
	// Methods for getting term information.

	/**
	 * Check if a term exists.
	 *
	 * @param int $term_id The ID of the term.
	 * @return bool True if the term exists, false otherwise.
	 */
	public static function term_exists( int $term_id ): bool {
		global $wpdb;
		$sql   = $wpdb->prepare( 'SELECT COUNT(ID) FROM %i WHERE ID = %d', $wpdb->terms, $term_id );
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}

	/**
	 * Get a term by ID.
	 *
	 * @param int $term_id The ID of the term.
	 * @return WP_Term The term object.
	 * @throws Exception If the term could not be loaded.
	 */
	public static function get_term( int $term_id ): WP_Term {
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			throw new Exception( "Term {$term_id} could not be loaded." );
		}
		return $term;
	}

	/**
	 * Extracts and returns term properties for logging.
	 *
	 * @param WP_Term|int $term The term object or ID.
	 * @return array An associative array of term properties.
	 */
	private static function get_properties( WP_Term|int $term ) {
		// Load the term if necessary.
		if ( is_int( $term ) ) {
			$term = self::get_term( $term );
		}

		// Start building the properties array.
		$properties = array();

		// Add the base properties.
		foreach ( $term as $key => $value ) {
			$properties[ $key ] = Property::create( null, $key, 'base', $value );
		}

		// Add the meta properties.
		$termmeta = get_term_meta( $term->term_id );
		foreach ( $termmeta as $key => $value ) {
			$properties[ $key ] = Property::create( null, $key, 'meta', $value );
		}

		return $properties;
	}

	/**
	 * Get the URL for a term's edit page.
	 *
	 * @param WP_Term|int $term The term object or ID.
	 * @return string The edit page URL.
	 */
	public static function get_edit_url( WP_Term|int $term ) {
		// Load the term if necessary.
		if ( is_int( $term ) ) {
			$term = self::get_term( $term );
		}

		return admin_url( "term.php?taxonomy={$term->taxonomy}&tag_ID={$term->term_id}" );
	}
}
