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
	 * @param int|string $object_key The object key.
	 * @return bool True if the object exists, false otherwise.
	 */
	public static function exists( int|string $object_key ): bool {
		return true;
	}

	/**
	 * Get core by key. The result will always be null. This method is N/A for core.
	 *
	 * @param int|string $object_key The object key.
	 * @return mixed The object or null if not found.
	 */
	public static function load( int|string $object_key ): mixed {
		return null;
	}

	/**
	 * Get a name for core. This is displayed in the tag. Example "WordPress 6.6.1"
	 *
	 * @param int|string $object_key The object key (irrelevant for core).
	 * @return string The name.
	 */
	public static function get_name( int|string $object_key ): ?string {
		$version = get_bloginfo( 'version' );
		return "WordPress $version";
	}

	/**
	 * Get the core properties of an object, for logging. Irrelevant for core.
	 *
	 * @param int|string $object_key The object key.
	 * @return Property[] The core properties of the object.
	 */
	public static function get_core_properties( int|string $object_key ): array {
		return array();
	}

	/**
	 * Return HTML referencing core.
	 *
	 * @param int|string $object_key The object key.
	 * @param ?string    $old_name   The name of the object at the time of the event.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $object_key, ?string $old_name ): string {
		return admin_url( 'about.php' );
	}
}
