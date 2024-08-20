<?php
/**
 * Contains the Taxonomy_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;
use WP_Taxonomy;

/**
 * Class WP_Logify\Taxonomy_Utility
 *
 * Provides tracking of events related to taxonomies.
 */
class Taxonomy_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if a taxonomy exists.
	 *
	 * @param int|string $taxonomy The name of the taxonomy.
	 * @return bool True if the taxonomy exists, false otherwise.
	 */
	public static function exists( int|string $taxonomy ): bool {
		return taxonomy_exists( $taxonomy );
	}

	/**
	 * Get a taxonomy by ID.
	 *
	 * @param int|string $taxonomy The name of the taxonomy.
	 * @return ?WP_Taxonomy The taxonomy object if it exists, null otherwise.
	 */
	public static function load( int|string $taxonomy ): ?WP_Taxonomy {
		// Load the taxonomy.
		$taxonomy_obj = get_taxonomy( $taxonomy );
		return $taxonomy_obj ? $taxonomy_obj : null;
	}

	/**
	 * Get a taxonomy's label.
	 * This is usually plural and starts with an upper-case letter, e.g. 'Categories', 'Tags'.
	 *
	 * @param int|string $taxonomy The name (lower-case key) of the taxonomy.
	 * @return ?string The taxonomy label or null if not found.
	 */
	public static function get_name( int|string $taxonomy ): ?string {
		// Load the taxonomy object.
		$taxonomy_obj = self::load( $taxonomy );

		// Return the taxonomy label.
		return $taxonomy_obj?->label;
	}

	/**
	 * Extracts and returns a taxonomy's core properties for logging.
	 *
	 * @param int|string $taxonomy The name of the taxonomy.
	 * @return Property[] An associative array of a taxonomy's core properties.
	 * @throws Exception If the taxonomy could not be retrieved.
	 */
	public static function get_core_properties( int|string $taxonomy ): array {
		global $wpdb;

		// Load the taxonomy.
		$taxonomy_obj = self::load( $taxonomy );

		// Handle error if the taxonomy could not be retrieved.
		if ( ! $taxonomy_obj ) {
			throw new Exception( "Taxonomy $taxonomy not found." );
		}

		// Start building the properties array.
		$props = array();

		// Name.
		Property::update_array( $props, 'name', $wpdb->taxonomies, $taxonomy_obj->name );

		// Label.
		Property::update_array( $props, 'label', $wpdb->taxonomies, $taxonomy_obj->label );

		// Public.
		Property::update_array( $props, 'public', $wpdb->taxonomies, $taxonomy_obj->public );

		// Show UI.
		Property::update_array( $props, 'show_ui', $wpdb->taxonomies, $taxonomy_obj->show_ui );

		return $props;
	}

	/**
	 * If the taxonomy hasn't been unregistered, get a link to its admin page; otherwise, get a span
	 * with the old name as the link text.
	 *
	 * @param int|string $taxonomy  The ID of the taxonomy.
	 * @param ?string    $old_name The old name of the taxonomy.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $taxonomy, ?string $old_name ): string {
		// Load the taxonomy.
		$taxonomy_obj = self::load( $taxonomy );

		if ( $taxonomy_obj ) {
			if ( $taxonomy_obj->show_ui && current_user_can( 'manage_categories' ) && current_user_can( $taxonomy_obj->cap->manage_terms ) ) {
				// Get a link to the taxonomy's admin page.
				$url = admin_url( "edit-tags.php?taxonomy=$taxonomy" );
				return "<a href='$url' class='wp-logify-object'>$taxonomy_obj->label</a>";
			} else {
				// Just show the name.
				return "<span class='wp-logify-object'>$taxonomy_obj->label</span>";
			}
		}

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = "Taxonomy $taxonomy";
		}

		// The taxonomy no longer exists. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Methods for getting information about taxonomies.

	/**
	 * Get the singular name of a taxonomy.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return string The singular name of the taxonomy.
	 */
	public static function get_singular_name( string $taxonomy ) {
		// Get the taxonomy object.
		$taxonomy_obj = get_taxonomy( $taxonomy );

		// Return the taxonomy singular name.
		return $taxonomy_obj->labels->singular_name;
	}
}
