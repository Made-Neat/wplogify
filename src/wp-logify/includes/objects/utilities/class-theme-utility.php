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
 * Provides methods for working with themes.
 */
class Theme_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

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
		$props = array();

		// Name. Make into a link if the URI is provided.
		$name = $theme->get( 'Name' );
		$uri  = $theme->get( 'ThemeURI' );
		if ( $uri ) {
			$name = "<a href='$uri' target='_blank'>$name</a>";
		}
		Property::update_array( $props, 'name', null, $name );

		// Stylesheet.
		Property::update_array( $props, 'stylesheet', null, $theme->get_stylesheet() );

		// Author. Make into a link if the URI is provided.
		$author_uri = $theme->get( 'AuthorURI' );
		$author     = $theme->get( 'Author' );
		if ( $author_uri ) {
			$author = "<a href='$author_uri ' target='_blank'>$author</a>";
		}
		Property::update_array( $props, 'author', null, $author );

		// Version.
		Property::update_array( $props, 'version', null, $theme->get( 'Version' ) );

		// Status.
		Property::update_array( $props, 'status', null, $theme->get( 'Status' ) );

		// Parent theme.
		if ( $theme->get( 'Template' ) ) {
			$parent_ref = new Object_Reference( 'theme', $theme->get( 'Template' ) );
			Property::update_array( $props, 'parent', null, $parent_ref );
		}

		return $props;
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
	public static function get_tag( int|string $stylesheet, ?string $old_name = null ): string {
		// Load the theme.
		$theme = self::load( $stylesheet );

		if ( $theme ) {
			// Get a link to the theme.
			$name = $theme->get( 'Name' );
			$url  = $theme->get( 'ThemeURI' );
			if ( $url ) {
				return "<a href='$url' class='wp-logify-object' target='_blank'>$name</a>";
			} else {
				return "<span class='wp-logify-object'>$name</span>";
			}
		}

		// Make backup name.
		if ( ! $old_name ) {
			$old_name = Strings::key_to_label( $stylesheet, true );
		}

		// Return a span with the old name.
		return "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Load a theme by its name.
	 *
	 * @param string $theme_name The name of the theme.
	 * @return ?WP_Theme The theme object or null if the theme isn't found.
	 */
	public static function load_by_name( string $theme_name ): ?WP_Theme {
		// Get all installed themes.
		$all_themes = wp_get_themes();

		// Loop through each theme and check its text domain.
		foreach ( $all_themes as $theme ) {
			// Check for a match.
			if ( $theme->get( 'Name' ) === $theme_name ) {
				return $theme;
			}
		}

		// Return null if no theme is found with the given text domain.
		return null;
	}
}
