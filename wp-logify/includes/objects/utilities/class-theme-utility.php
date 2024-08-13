<?php
/**
 * Contains the Theme_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;
use WP_Theme;

/**
 * Class WP_Logify\Theme_Utility
 *
 * Provides event handlers and utility methods related to themes.
 */
class Theme_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Theme install.
		// Theme activate.
		// Theme deactivate.
		// Theme uninstall/delete.
	}

	/**
	 * Check if a theme exists.
	 *
	 * @param int|string $stylesheet The theme's unique identifier.
	 * @return bool True if the theme exists, false otherwise.
	 */
	public static function exists( int|string $stylesheet ): bool {
		// Get the theme by stylesheet.
		$theme = wp_get_theme( $stylesheet );

		// Return true if the theme exists, false otherwise.
		return $theme->exists();
	}

	/**
	 * Get a theme by stylesheet.
	 *
	 * If the theme isn't found, null will be returned. An exception will not be thrown.
	 *
	 * @param int|string $stylesheet The theme stylesheet.
	 * @return ?WP_Theme The theme or null if not found.
	 */
	public static function load( int|string $stylesheet ): ?WP_Theme {
		// Get the theme by stylesheet.
		$theme = wp_get_theme( $stylesheet );

		// Return the theme or null if it doesn't exist.
		return $theme->exists() ? $theme : null;
	}

	/**
	 * Get an theme's name.
	 *
	 * @param int|string $stylesheet The theme stylesheet.
	 * @return string The theme name, or null if the theme could not be found.
	 */
	public static function get_name( int|string $stylesheet ): ?string {
		// Load the theme.
		$theme = self::load( $stylesheet );

		// Return the theme name or null if the theme doesn't exist.
		return $theme ? $theme->get( 'Name' ) : null;
	}

	/**
	 * Get the core properties of a object, for logging.
	 *
	 * @param int|string $stylesheet The theme stylesheet.
	 * @return Property[] The core properties of the theme.
	 * @throws Exception If the theme doesn't exist.
	 */
	public static function get_core_properties( int|string $stylesheet ): array {
		// Load the theme.
		$theme = self::load( $stylesheet );

		// Handle the case where the theme no longer exists.
		if ( ! $theme ) {
			throw new Exception( "Theme '$stylesheet' not found." );
		}

		// Build the array of properties.
		$properties = array();

		// Name.
		Property::update_array( $properties, 'name', null, $theme->get( 'Name' ) );

		// Stylesheet.
		Property::update_array( $properties, 'stylesheet', null, $theme->get_stylesheet() );

		// Template.
		Property::update_array( $properties, 'template', null, $theme->get_template() );

		// Version.
		Property::update_array( $properties, 'version', null, $theme->get( 'Version' ) );

		// Author.
		$author = new Object_Reference( 'user', $theme->get( 'Author' ) );
		Property::update_array( $properties, 'author', null, $author );

		// Parent theme.
		if ( $theme->get( 'Parent' ) ) {
			Property::update_array( $properties, 'parent', null, $theme->get( 'Parent' ) );
		}

		return $properties;
	}

	/**
	 * Return HTML referencing an theme.
	 * If the theme hasn't been deleted, get a link to the theme's edit or view page.
	 * If it has ben deleted, get a span with the old name or title as the span text.
	 *
	 * @param int|string $stylesheet The theme stylesheet.
	 * @param ?string    $old_name   The name of the theme at the time of the event.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $stylesheet, ?string $old_name ): string {
		// Load the theme.
		$theme = self::load( $stylesheet );

		// If the theme exists, get a link.
		if ( $theme ) {
			// Get the theme name.
			$name = $theme->get( 'Name' );

			// Get the theme edit URL.
			$edit_url = admin_url( 'themes.php?page=theme-editor&file=' . $theme->get_stylesheet() );

			// Return the link.
			return "<a href='$edit_url' class='wp-logify-object'>$name</a>";
		}

		// If the theme doesn't exist, return a span.
		$name = $old_name ? $old_name : Types::make_key_readable( $stylesheet, true );
		return "<span class='wp-logify-deleted-object'>$name (deleted)</span>";
	}
}
