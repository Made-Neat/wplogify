<?php
/**
 * Contains the abstract Object_Utility class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class Logify_WP\Object_Utility
 *
 * Base class for object-type-specific utility classes such as Post_Utility, User_Utility, etc.,
 * which provide a variety of useful methods for different WordPress object types.
 *
 * This class specifies the methods that must be implemented by all object utility classes.
 */
abstract class Object_Utility {

	// =============================================================================================
	// Abstract methods.

	/**
	 * Check if an object exists.
	 *
	 * @param int|string $object_key The object key.
	 * @return bool True if the object exists, false otherwise.
	 */
	abstract public static function exists( int|string $object_key ): bool;

	/**
	 * Get a object by key.
	 *
	 * If the object isn't found, null will be returned. An exception will not be thrown.
	 *
	 * @param int|string $object_key The object key.
	 * @return mixed The object or null if not found.
	 */
	abstract public static function load( int|string $object_key ): mixed;

	/**
	 * Get an object's name. This is displayed in the tag.
	 *
	 * Source of the name by object type:
	 *   - posts: title
	 *   - users: display_name
	 *   - terms: name
	 *   - plugins: name
	 *   - option: option_name
	 *   - theme: theme_name
	 *   - comments: snippet
	 *
	 * @param int|string $object_key The object key.
	 * @return string The name, or null if the object could not be found.
	 */
	abstract public static function get_name( int|string $object_key ): ?string;

	/**
	 * Get the core properties of a object, for logging.
	 *
	 * @param int|string $object_key The object key.
	 * @return ?Property[] The core properties of the object, or null not found.
	 */
	abstract public static function get_core_properties( int|string $object_key ): ?array;

	/**
	 * Return HTML referencing an object.
	 * If the object hasn't been deleted, get a link to the object's edit or view page.
	 * If it has ben deleted, get a span with the old name or title as the span text.
	 *
	 * @param int|string $object_key The object key.
	 * @param ?string    $old_name   The name of the object at the time of the event, or null if unknown.
	 * @return string The link or span HTML tag.
	 */
	abstract public static function get_tag( int|string $object_key, ?string $old_name = null ): string;

	// =============================================================================================
	// Concrete methods.

	/**
	 * Reduce an array to a single value if it contains only one element.
	 * This is useful for handling metadata values.
	 *
	 * @param mixed $value The value to reduce.
	 */
	public static function reduce_to_single( mixed &$value ) {
		if ( is_array( $value ) && count( $value ) === 1 && isset( $value[0] ) ) {
			$value = $value[0];
		}
	}

	/**
	 * Extract a value from an array of metadata and process into the correct type.
	 *
	 * @param array  $metadata Array of metadata.
	 * @param string $key      The meta key.
	 * @return mixed The meta value or null if not found.
	 */
	public static function extract_meta( array $metadata, string $key ): mixed {
		// See if the array of metadata contains this key.
		if ( ! isset( $metadata[ $key ] ) ) {
			return null;
		}

		// Get the value.
		$value = $metadata[ $key ];

		// Check for single.
		self::reduce_to_single( $value );

		// Process the value into the correct type.
		return Types::process_database_value( $key, $value );
	}
}
