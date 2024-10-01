<?php
/**
 * Contains the Core_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Core_Utility
 *
 * Provides methods for working with WordPress core.
 */
class Core_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if core exists.
	 *
	 * @param int|string $version The WordPress core version.
	 * @return bool Always true for core.
	 */
	public static function exists( int|string $version ): bool {
		return true;
	}

	/**
	 * Get core by key. The result will always be null. This method is N/A for core.
	 *
	 * @param int|string $version The WordPress core version.
	 * @return mixed Always null for core.
	 */
	public static function load( int|string $version ): mixed {
		return null;
	}

	/**
	 * Get a name for core. This is displayed in the tag. Example "WordPress 6.6.1"
	 *
	 * @param int|string $version The WordPress core version.
	 * @return string The name.
	 */
	public static function get_name( int|string $version ): ?string {
		return "WordPress $version";
	}

	/**
	 * Get the core properties of an object, for logging. Irrelevant for core.
	 *
	 * @param int|string $version The WordPress core version.
	 * @return ?Property[] The core properties of the object, or null if not found.
	 */
	public static function get_core_properties( int|string $version ): ?array {
		$props = array();

		// Link.
		Property_Array::set( $props, 'link', null, self::get_tag( $version ) );

		return $props;
	}

	/**
	 * Return a link to the page in the WordPress documentation about the specified version.
	 *
	 * @param int|string $version  The WordPress core version.
	 * @param ?string    $old_name The name of the object at the time of the event.
	 * @return string The link HTML.
	 */
	public static function get_tag( int|string $version, ?string $old_name = null ): string {
		// Get the URL to the documentation page about this version.
		$version_with_hyphens = str_replace( '.', '-', $version );
		$url                  = "https://wordpress.org/documentation/wordpress-version/version-$version_with_hyphens/";

		// Get the link text.
		$name = self::get_name( $version );

		// Return the link.
		return "<a href='$url' class='wp-logify-object' target='_blank'>$name</a>";
	}
}
