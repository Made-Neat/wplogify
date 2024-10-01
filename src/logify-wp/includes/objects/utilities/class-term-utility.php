<?php
/**
 * Contains the Term_Utility class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use WP_Term;

/**
 * Class Logify_WP\Term_Utility
 *
 * Provides methods for working with terms.
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
	 * @return ?Property[] The core properties of the term, or null if not found.
	 */
	public static function get_core_properties( int|string $term_id ): ?array {
		global $wpdb;

		// Load the term.
		$term = self::load( $term_id );

		// Handle error if the term could not be retrieved.
		if ( ! $term ) {
			return null;
		}

		// Start building the properties array.
		$props = array();

		// Name.
		Property_Array::set( $props, 'name', $wpdb->terms, Object_Reference::new_from_wp_object( $term ) );

		// ID.
		Property_Array::set( $props, 'term_id', $wpdb->terms, $term->term_id );

		// Taxonomy.
		Property_Array::set( $props, 'taxonomy', $wpdb->term_taxonomy, $term->taxonomy );

		// Slug.
		Property_Array::set( $props, 'slug', $wpdb->terms, $term->slug );

		// Parent.
		if ( $term->parent ) {
			$parent = new Object_Reference( 'term', $term->parent );
			Property_Array::set( $props, 'parent', $wpdb->term_taxonomy, $parent );
		}

		return $props;
	}

	/**
	 * If the term hasn't been deleted, get a link to its edit page; otherwise, get a span with
	 * the old title as the link text.
	 *
	 * @param int|string $term_id  The ID of the term.
	 * @param ?string    $old_name The old name of the term.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $term_id, ?string $old_name = null ): string {
		// Load the term.
		$term = self::load( $term_id );

		if ( $term ) {

			// Construct a link to the term's edit page.
			if ( $term->taxonomy === 'nav_menu' ) {
				$url = admin_url( "nav-menus.php?action=edit&menu=$term_id" );
			} else {
				// Categories, tags, perhaps others.
				$url = admin_url( "term.php?taxonomy={$term->taxonomy}&tag_ID=$term_id" );
			}

			return "<a href='$url' class='logify-wp-object'>$term->name</a>";
		}

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = "Term $term_id";
		}

		// The term no longer exists. Construct the 'deleted' span element.
		return "<span class='logify-wp-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Extracts and returns term properties for logging.
	 *
	 * @param WP_Term|int $term The term object or ID.
	 * @return ?Property[] An associative array of term properties.
	 */
	public static function get_properties( WP_Term|int $term ): ?array {
		global $wpdb;

		// Load the term if necessary.
		if ( is_int( $term ) ) {
			$term = self::load( $term );

			// Handle term not found.
			if ( ! $term ) {
				return null;
			}
		}

		// Start building the properties array.
		$props = array();

		// Add the base properties.
		foreach ( $term as $key => $value ) {
			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			Property_Array::set( $props, $key, $wpdb->terms, $value );
		}

		// Add the meta properties.
		$termmeta = get_term_meta( $term->term_id );
		foreach ( $termmeta as $key => $value ) {
			// Check for single.
			self::reduce_to_single( $value );

			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			Property_Array::set( $props, $key, $wpdb->termmeta, $value );
		}

		return $props;
	}

	/**
	 * Get a term ID, given a term_taxonomy_id.
	 *
	 * @param int $term_taxonomy_id The term taxonomy ID.
	 * @return ?int The term ID on success, null on failure.
	 */
	public static function get_term_id_from_term_taxonomy_id( $term_taxonomy_id ): ?int {
		global $wpdb;

		// Retrieve the term ID from the term_taxonomy_id.
		$sql     = $wpdb->prepare( 'SELECT term_id FROM %i WHERE term_taxonomy_id = %d', $wpdb->term_taxonomy, $term_taxonomy_id );
		$term_id = $wpdb->get_var( $sql );

		// Return the term ID or null if the term couldn't be found.
		return $term_id ? (int) $term_id : null;
	}

	/**
	 * Get a WP_Term object, given a term_taxonomy_id.
	 *
	 * @param int $term_taxonomy_id The term taxonomy ID.
	 * @return ?WP_Term WP_Term object on success, null on failure.
	 */
	public static function get_by_term_taxonomy_id( $term_taxonomy_id ): ?WP_Term {
		// Get the term ID.
		$term_id = self::get_term_id_from_term_taxonomy_id( $term_taxonomy_id );

		// If no term ID was found, return null.
		if ( ! $term_id ) {
			return null;
		}

		// Retrieve the WP_Term object using the term ID.
		$term = get_term( $term_id );

		// Return the WP_Term object or null if the term couldn't be found.
		return $term instanceof WP_Term ? $term : null;
	}
}
