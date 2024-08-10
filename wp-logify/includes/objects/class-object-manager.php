<?php
/**
 * Contains the Object_Manager class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;

/**
 * Class WP_Logify\Object_Manager
 *
 * Base class for Manager classes, which provide event handlers and utility methods for different
 * WordPress object types, such as posts, users, terms, plugins, etc.
 */
abstract class Object_Manager {

	/**
	 * Array to remember properties between different events.
	 *
	 * @var array
	 */
	protected static $properties = array();

	/**
	 * Array to remember metadata between different events.
	 *
	 * @var array
	 */
	protected static $eventmetas = array();

	/**
	 * Set up hooks for the events we want to log.
	 */
	abstract public static function init();

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
	 * @return Property[] The core properties of the object.
	 * @throws Exception If the object doesn't exist.
	 */
	abstract public static function get_core_properties( int|string $object_key ): array;

	/**
	 * Return HTML referencing an object.
	 * If the object hasn't been deleted, get a link to the object's edit or view page.
	 * If it has ben deleted, get a span with the old name or title as the span text.
	 *
	 * @param int|string $object_key The object key.
	 * @param ?string    $old_name   The name of the object at the time of the event.
	 * @return string The link or span HTML tag.
	 */
	abstract public static function get_tag( int|string $object_key, ?string $old_name ): string;
}
