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
 * Class WP_Logify\Post_Utility
 *
 * Provides tracking of events related to terms.
 */
class Term_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if a term exists.
	 *
	 * @param int|string $term_id The ID of the term.
	 * @return bool True if the term exists, false otherwise.
	 */
	public static function exists( int|string $term_id ): bool {
		global $wpdb;
		$sql   = $wpdb->prepare( 'SELECT COUNT(term_id) FROM %i WHERE term_id = %d', $wpdb->terms, $term_id );
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}

	/**
	 * Get a term by ID.
	 *
	 * @param int|string $term_id The ID of the term.
	 * @return ?WP_Term The term object if it exists, null otherwise.
	 */
	public static function load( int|string $term_id ): ?WP_Term {
		// Load the term.
		$term = get_term( $term_id );

		// If the term could not be retrieved, return null.
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		// Return the term.
		return $term;
	}

	/**
	 * Get the name of a term.
	 *
	 * @param int|string $term_id The ID of the term.
	 * @return ?string The name of the term or null if not found.
	 */
	public static function get_name( int|string $term_id ): ?string {
		// Load the term.
		$term = self::load( $term_id );

		// Return the term name or null if the term doesn't exist.
		return $term->name ?? null;
	}

	/**
	 * Extracts and returns a term's core properties for logging.
	 *
	 * @param int|string $term_id The ID of the term.
	 * @return Property[] An associative array of a term's core properties.
	 * @throws Exception If the term could not be retrieved.
	 */
	public static function get_core_properties( int|string $term_id ): array {
		global $wpdb;

		// Load the term.
		$term = self::load( $term_id );

		// Handle error if the term could not be retrieved.
		if ( ! $term ) {
			throw new Exception( "Term $term_id not found." );
		}

		// Start building the properties array.
		$properties = array();

		// ID.
		Property::update_array( $properties, 'term_id', $wpdb->terms, $term->term_id );

		// Name.
		Property::update_array( $properties, 'name', $wpdb->terms, $term->name );

		// Slug.
		Property::update_array( $properties, 'slug', $wpdb->terms, $term->slug );

		// Taxonomy.
		Property::update_array( $properties, 'taxonomy', $wpdb->term_taxonomy, $term->taxonomy );

		// Parent.
		if ( $term->parent ) {
			$parent = new Object_Reference( 'term', $term->parent );
			Property::update_array( $properties, 'parent', $wpdb->term_taxonomy, $parent );
		}

		return $properties;
	}

	/**
	 * If the term hasn't been deleted, get a link to its edit page; otherwise, get a span with
	 * the old title as the link text.
	 *
	 * @param int|string $term_id  The ID of the term.
	 * @param ?string    $old_name The old name of the term.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $term_id, ?string $old_name ): string {
		// Load the term.
		$term = self::load( $term_id );

		if ( $term ) {
			// Constrct a link to the term's edit page.
			$url = admin_url( "term.php?taxonomy={$term->taxonomy}&tag_ID={$term->term_id}" );
			return "<a href='$url' class='wp-logify-object'>$term->name</a>";
		}

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = "Term $term_id";
		}

		// The term no longer exists. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Methods for getting information about terms.

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
			$term = self::load( $term );
		}

		// Start building the properties array.
		$properties = array();

		// Add the base properties.
		foreach ( $term as $key => $value ) {
			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			Property::update_array( $properties, $key, $wpdb->terms, $value );
		}

		// Add the meta properties.
		$termmeta = get_term_meta( $term->term_id );
		foreach ( $termmeta as $key => $value ) {
			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			Property::update_array( $properties, $key, $wpdb->termmeta, $value );
		}

		return $properties;
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

	/**
	 * Get the singular name of a taxonomy.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return string The singular name of the taxonomy.
	 */
	public static function get_taxonomy_singular_name( string $taxonomy ) {
		// Get the taxonomy object.
		$taxonomy_obj = get_taxonomy( $taxonomy );

		// Return the taxonomy singular name.
		return $taxonomy_obj->labels->singular_name;
	}
}
