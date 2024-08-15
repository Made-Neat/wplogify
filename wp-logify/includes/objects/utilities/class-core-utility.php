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
 * Provides tracking of events related to WordPress core.
 */
class Core_Utility {

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
	 * @return Property[] The core properties of the object.
	 */
	public static function get_core_properties( int|string $version ): array {
		return array();
	}

	/**
	 * Return HTML referencing core.
	 *
	 * @param int|string $version The WordPress core version.
	 * @param ?string    $old_name   The name of the object at the time of the event.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $version, ?string $old_name ): string {
		// Get the page about the release.
		$url = Urls::get_wp_release_url( $version );

		// If we couldn't find the release page, fall back to the about page.
		if ( ! $url ) {
			$url = admin_url( 'about.php' );

			// The about page doesn't require a target.
			$target = '';
		} else {
			// Open up the release page in a new tab.
			$target = 'target="_blank"';
		}

		// Get the link text.
		$name = self::get_name( $version );

		// Return the link.
		return "<a href='$url' class='wp-logify-object' $target>$name</a>";
	}
}
