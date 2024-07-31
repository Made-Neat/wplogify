<?php
/**
 * Contains the Terms class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;
use WP_Term;

/**
 * Class WP_Logify\Posts
 *
 * Provides tracking of events related to terms.
 */
class Terms {

	/**
	 * Current event under construction.
	 *
	 * @var array
	 */
	private static Event $new_event;

	/**
	 * Link the events we want to log to methods.
	 */
	public static function init() {
		// CRUD operations.
		// add_action( 'created_term', array( __CLASS__, 'on_created_term' ), 10, 4 );
		// add_action( 'edit_terms', array( __CLASS__, 'on_edit_terms' ), 10, 3 );
		// add_action( 'pre_delete_term', array( __CLASS__, 'on_pre_delete_term' ), 10, 2 );

		// // Track changes to term taxonomies.
		// add_action( 'edit_term_taxonomies', array( __CLASS__, 'on_edit_term_taxonomies' ), 10, 1 );
	}

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
	public static function on_edit_terms( int $term_id, string $taxonomy, array $args ) {
		debug( func_get_args() );

		// Load the term.
		$term = self::get_term( $term_id );

		// Get the term properties.
		$properties = self::get_properties( $term );

		// Compare values.
		$changed = false;
		foreach ( $term->data as $key => $value ) {
			// Get the old and new values.
			$val     = Types::process_database_value( $key, $value );
			$new_val = Types::process_database_value( $key, $args[ $key ] );

			if ( ! Types::are_equal( $val, $new_val ) ) {
				// Update the property's before and after values.
				$properties[ $key ]->val     = $val;
				$properties[ $key ]->new_val = $new_val;

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
	public static function on_pre_delete_term( int $term_id, string $taxonomy ) {
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

		// Find all posts tagged with this term, in case we need to restore the term.
		$post_ids = get_objects_in_term( $term_id, $taxonomy );

		// Handle error if the posts could not be retrieved.
		if ( is_wp_error( $post_ids ) ) {
			throw new Exception( "Failed to retrieve posts with term ID $term_id in taxonomy $taxonomy." );
		}

		// The function returns an array of strings for some reason, so let's convert them to ints.
		$post_ids = array_map( fn( $post_id ) => (int) $post_id, $post_ids );

		// Add to properties.
		$properties['attached_posts'] = new Property( 'attached_posts', null, $post_ids );

		// Log the event.
		Logger::log_event( $event_type, 'term', $term_id, $term->name, null, $properties );
	}

	/**
	 * Track the edit of a term.
	 *
	 * @param array $edit_tt_ids An array of term taxonomy IDs for the given term.
	 */
	public static function on_edit_term_taxonomies( array $edit_tt_ids ) {
		debug( 'on_edit_term_taxonomies', func_get_args() );
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
		$sql   = $wpdb->prepare( 'SELECT COUNT(term_id) FROM %i WHERE term_id = %d', $wpdb->terms, $term_id );
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
	public static function get_properties( WP_Term|int $term ) {
		global $wpdb;

		// Load the term if necessary.
		if ( is_int( $term ) ) {
			$term = self::get_term( $term );
		}

		// Start building the properties array.
		$properties = array();

		// Add the base properties.
		foreach ( $term as $key => $value ) {
			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			$properties[ $key ] = new Property( $key, $wpdb->terms, $value );
		}

		// Add the meta properties.
		$termmeta = get_term_meta( $term->term_id );
		foreach ( $termmeta as $key => $value ) {
			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			$properties[ $key ] = new Property( $key, $wpdb->termmeta, $value );
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

	/**
	 * Get the HTML for the link to the term's edit page.
	 *
	 * @param WP_Term|int $term The term object or ID.
	 * @return string The link HTML tag.
	 */
	public static function get_edit_link( WP_Term|int $term ) {
		// Load the term if necessary.
		if ( is_int( $term ) ) {
			$term = self::get_term( $term );
		}

		// Get the URL for the term's edit page.
		$url = self::get_edit_url( $term );

		// Return the link.
		return "<a href='$url' class='wp-logify-term-link'>$term->name</a>";
	}

	/**
	 * If the term hasn't been deleted, get a link to its edit page; otherwise, get a span with
	 * the old title as the link text.
	 *
	 * @param WP_Term|int $term The term object or ID.
	 * @param string      $old_name The old name of the term.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( WP_Term|int $term, string $old_name ) {
		// If the term exists, return a link to its edit page.
		if ( self::term_exists( $term ) ) {
			return self::get_edit_link( $term );
		}

		// The term no longer exists. Construct the 'deleted' span element.
		$term_id = is_int( $term ) ? $term : $term->term_id;
		$name    = empty( $old_name ) ? "Term $term_id" : $old_name;
		return "<span class='wp-logify-deleted-object'>$name (deleted)</span>";
	}

	/**
	 * Get a term (as a WP_Term object) from a term_taxonomy_id.
	 *
	 * @param int $term_taxonomy_id The term taxonomy ID.
	 * @return WP_Term|null WP_Term object on success, null on failure.
	 */
	public static function get_by_term_taxonomy_id( $term_taxonomy_id ) {
		global $wpdb;

		// Retrieve the term ID from the term_taxonomy_id.
		$sql     = $wpdb->prepare( 'SELECT term_id FROM %i WHERE term_taxonomy_id = %d', $wpdb->term_taxonomy, $term_taxonomy_id );
		$term_id = $wpdb->get_var( $sql );

		// If no term ID was found, return false.
		if ( ! $term_id ) {
			return null;
		}

		// Retrieve the WP_Term object using the term ID.
		$term = get_term( $term_id );

		// Return the WP_Term object or false if the term couldn't be found.
		return ( ! is_wp_error( $term ) && $term ) ? $term : null;
	}
}
