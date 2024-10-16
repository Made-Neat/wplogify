<?php
/**
 * Contains the Taxonomy_Utility class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use WP_Taxonomy;

/**
 * Class Logify_WP\Taxonomy_Utility
 *
 * Provides methods for working with taxonomies.
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
	 * Get a taxonomy's plural name.
	 *
	 * @param int|string $taxonomy The name (lower-case key) of the taxonomy.
	 * @return ?string The taxonomy's plural name or null if not found.
	 */
	public static function get_name( int|string $taxonomy ): ?string {
		$names = self::get_names( (string) $taxonomy );
		return $names['plural'] ?? null;
	}

	/**
	 * Extracts and returns a taxonomy's core properties for logging.
	 *
	 * @param int|string $taxonomy The name of the taxonomy.
	 * @return ?Property[] The core properties of the taxonomy, or null if not found.
	 */
	public static function get_core_properties( int|string $taxonomy ): ?array {
		// Load the taxonomy.
		$taxonomy_obj = self::load( $taxonomy );

		// Handle error if the taxonomy could not be retrieved.
		if ( ! $taxonomy_obj ) {
			return null;
		}

		// Start building the properties array.
		$props = array();

		// Name. This will be a link if there's an admin page accessible to the user, otherwise it
		// will be the label (usually plural).
		Property::update_array( $props, 'name', null, Object_Reference::new_from_wp_object( $taxonomy_obj ) );

		// Slug.
		Property::update_array( $props, 'slug', null, $taxonomy_obj->name );

		// Show UI.
		Property::update_array( $props, 'show_ui', null, $taxonomy_obj->show_ui );

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
	public static function get_tag( int|string $taxonomy, ?string $old_name = null ): string {
		// Load the taxonomy.
		$taxonomy_obj = self::load( $taxonomy );

		if ( $taxonomy_obj ) {
			if ( self::can_access_admin_page( $taxonomy_obj ) ) {
				// Get a link to the taxonomy's admin page.
				$url = admin_url( "edit-tags.php?taxonomy=$taxonomy" );
				return "<a href='$url' class='logify-wp-object'>$taxonomy_obj->label</a>";
			} elseif ( $taxonomy === 'nav_menu' && current_theme_supports( 'menus' ) ) {
				$url = admin_url( 'nav-menus.php' );
				return "<a href='$url' class='logify-wp-object'>$taxonomy_obj->label</a>";
			} else {
				// Just show the name.
				return "<span class='logify-wp-object'>$taxonomy_obj->label</span>";
			}
		}

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = "Taxonomy $taxonomy";
		}

		// The taxonomy no longer exists. Construct the 'deleted' span element.
		return "<span class='logify-wp-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Get the singular and plural names of a taxonomy.
	 *
	 * @param string|WP_Taxonomy $taxonomy The taxonomy slug or object.
	 * @return ?string[] The singular and plural names of the taxonomy, or null if the taxonomy is not found.
	 */
	public static function get_names( string|WP_Taxonomy $taxonomy ): ?array {
		// Get the taxonomy slug and object.
		if ( is_string( $taxonomy ) ) {
			$taxonomy_slug = $taxonomy;
			$taxonomy_obj  = get_taxonomy( $taxonomy );
			if ( ! $taxonomy_obj ) {
				return null;
			}
		} else {
			$taxonomy_obj  = $taxonomy;
			$taxonomy_slug = $taxonomy_obj->name;
		}

		// Get the singular name.
		// Special handling for taxonomies with names that clash with the core "Categories" and
		// "Tags" taxonomies, and others with irregular names.
		// I realise this is a kludge but it's the best I can come up with for now.
		if ( $taxonomy_slug === 'product_cat' ) {
			$singular = 'Product Category';
		} elseif ( $taxonomy_slug === 'product_tag' ) {
			$singular = 'Product Tag';
		} elseif ( $taxonomy_slug === 'seopress_404_cat' ) {
			$singular = 'SEOPress Category';
		} elseif ( $taxonomy_slug === 'product_shipping_class' ) {
			$singular = 'Product Shipping Class';
		} elseif ( $taxonomy_slug === 'pa_colour' ) {
			$singular = 'Product Colour';
		} elseif ( isset( $taxonomy_obj->labels->singular_name ) ) {
			$singular = ucwords( $taxonomy_obj->labels->singular_name );
		} else {
			$singular = Strings::key_to_label( $taxonomy_slug, true );
		}

		// Get the plural form from the taxonomy object.
		if ( $taxonomy_obj->labels->name ) {
			$plural = ucwords( $taxonomy_obj->labels->name );
		} elseif ( $taxonomy_obj->label ) {
			$plural = ucwords( $taxonomy_obj->label );
		} else {
			$plural = '';
		}

		// Make a plural from the singular.
		$words       = explode( ' ', $singular );
		$i           = count( $words ) - 1;
		$words[ $i ] = ucfirst( Strings::pluralize( strtolower( $words[ $i ] ) ) );
		$plural2     = implode( ' ', $words );

		// Choose the longer one.
		if ( strlen( $plural2 ) > strlen( $plural ) ) {
			$plural = $plural2;
		}

		return array(
			'singular' => $singular,
			'plural'   => $plural,
		);
	}

	/**
	 * Get the singular name of a taxonomy.
	 *
	 * @param string|WP_Taxonomy $taxonomy The taxonomy slug or object.
	 * @return ?string The singular name of the taxonomy, or null if the taxonomy is not found.
	 */
	public static function get_singular_name( string|WP_Taxonomy $taxonomy ): ?string {
		$names = self::get_names( $taxonomy );
		return $names['singular'] ?? null;
	}

	/**
	 * Check if there is an accessible admin page for a taxonomy, and that the user has permission
	 * to access it.
	 *
	 * @param WP_Taxonomy $taxonomy_obj The taxonomy object.
	 * @return bool True if the user can access the taxonomy's admin page, false otherwise.
	 */
	public static function can_access_admin_page( WP_Taxonomy $taxonomy_obj ): bool {
		return $taxonomy_obj->show_ui && current_user_can( $taxonomy_obj->cap->manage_terms );
	}

	/**
	 * Extracts and returns a taxonomy's core properties for logging.
	 *
	 * @param array $taxonomy_info Core properties remembered about the taxonomy.
	 * @return Property[] An associative array of a taxonomy's core properties.
	 */
	public static function get_core_properties_from_array( array $taxonomy_info ): array {
		// Start building the properties array.
		$props = array();

		// Name. This will be a link if there's an admin page accessible to the user, otherwise it
		// will be the label (usually plural).
		$slug = $taxonomy_info['name'];
		$name = self::get_name( $slug ) ?? $taxonomy_info['label'];
		Property::update_array( $props, 'name', null, new Object_Reference( 'taxonomy', $slug, $name ) );

		// Slug.
		Property::update_array( $props, 'slug', null, $slug );

		// Show UI.
		Property::update_array( $props, 'show_ui', null, $taxonomy_info['show_ui'] );

		return $props;
	}

	/**
	 * Get the core properties for the current registered taxonomies.
	 *
	 * @return array An associative array of the core properties of the current taxonomies.
	 */
	public static function get_current_taxonomies_core_properties(): array {
		// Get the current taxonomies as objects.
		$current_taxonomy_objects = get_taxonomies( array(), 'objects' );
		// Debug::info( $current_taxonomy_objects );

		// Convert to an array containing just the core properties we want to show in the log.
		$current_taxonomies = array();
		foreach ( $current_taxonomy_objects as $taxonomy_obj ) {
			// Debug::info( $taxonomy_obj->name, self::get_names( $taxonomy_obj ) );
			$current_taxonomies[ $taxonomy_obj->name ] = array(
				'name'    => $taxonomy_obj->name,
				'label'   => $taxonomy_obj->label,
				'show_ui' => $taxonomy_obj->show_ui,
			);
		}

		return $current_taxonomies;
	}
}
