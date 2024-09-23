<?php
/**
 * Contains the Object_Reference class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;
use Throwable;
use WP_Comment;
use WP_Post;
use WP_Taxonomy;
use WP_Term;
use WP_Theme;
use WP_User;
use WP_Widget;

/**
 * Represents a reference to an WordPress object that can be created, updated, or deleted.
 */
class Object_Reference {

	/**
	 * The type of the object, e.g. 'post', 'user', 'term', etc.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * The ID of the object.
	 *
	 * This will be:
	 * - an integer for object types with an integer ID, like posts, users, terms, and comments
	 * - a string for object types identified by a string key, like taxonomies, plugins, themes, and widgets
	 * - null for options
	 *
	 * @var null|int|string
	 */
	public null|int|string $key = null;

	/**
	 * The display name or title of the object.
	 *
	 * @var ?string
	 */
	public ?string $name = null;

	/**
	 * The object itself.
	 * This is a private field. To access publically, call get_object(), which will lazy-load the
	 * object as needed.
	 *
	 * @var mixed
	 */
	private mixed $object = null;

	/**
	 * Constructor.
	 *
	 * @param string           $type The type of the object.
	 * @param null|int|string  $key  The object unique identified (int or string).
	 * @param null|string|bool $name The name of the object, or a bool to specify setting it
	 *                               automatically from the object.
	 *                               - If a string, the name will be assigned this value.
	 *                               - If true, the name will be extracted from the existing object.
	 *                               - If null or false, the name won't be set.
	 */
	public function __construct( string $type, null|int|string $key, null|string|bool $name = true ) {
		// Set the object type.
		$this->type = $type;

		// Set the object id.
		$this->key = $key;

		// Set the name if provided.
		if ( is_string( $name ) ) {
			$this->name = $name;
		} elseif ( $name ) {
			// If $name is true, get the name from the object.
			$this->name = $this->get_name();
		}
	}

	/**
	 * Create a new Object_Reference from a WordPress object, or an array, in the case of plugins.
	 *
	 * @param object|array $wp_object The WordPress object or array.
	 * @return self The new Object_Reference.
	 * @throws Exception If the object type is unknown or unsupported.
	 */
	public static function new_from_wp_object( object|array $wp_object ): Object_Reference {
		$type = null;
		$key  = null;

		// Get the object type and key, based on the type of the provided object.
		if ( $wp_object instanceof WP_Comment ) {
			$type = 'comment';
			$key  = $wp_object->comment_ID;
		} elseif ( $wp_object instanceof WP_Post ) {
			$type = 'post';
			$key  = $wp_object->ID;
		} elseif ( $wp_object instanceof WP_Taxonomy ) {
			$type = 'taxonomy';
			$key  = $wp_object->name;
		} elseif ( $wp_object instanceof WP_Term ) {
			$type = 'term';
			$key  = $wp_object->term_id;
		} elseif ( $wp_object instanceof WP_Theme ) {
			$type = 'theme';
			$key  = $wp_object->get_stylesheet();
		} elseif ( $wp_object instanceof WP_User ) {
			$type = 'user';
			$key  = $wp_object->ID;
		} elseif ( $wp_object instanceof WP_Widget ) {
			$type = 'widget';
			$key  = $wp_object->id;
		} elseif ( is_array( $wp_object ) ) {
			// An array could be a plugin or a widget, which we can identify by the object_type key.
			$type = $wp_object['object_type'];
			switch ( $type ) {
				case 'plugin':
					$key = $wp_object['slug'];
					break;

				case 'widget':
					$key = $wp_object['widget_id'];
					break;

				default:
					throw new Exception( "Unknown or unsupported object type: $type" );
			}
		} else {
			throw new Exception( 'Unknown or unsupported object type: ' . get_class( $wp_object ) );
		}

		return new Object_Reference( $type, $key );
	}

	/**
	 * Load the object it hasn't already been loaded.
	 *
	 * @return mixed The object.
	 */
	public function get_object() {
		// If the object hasn't been loaded yet, load it.
		if ( ! isset( $this->object ) ) {
			$this->load();
		}

		// Return the object.
		return $this->object;
	}

	/**
	 * Get the name of the utility class for this object, with the additional check that it
	 * actually exists.
	 *
	 * @return string The utility class name.
	 * @throws Exception If the object type is unknown.
	 */
	public function get_utility_class_name() {
		// Get the fully-qualified name of the utility class for this object type.
		$class = '\WP_Logify\\' . ucfirst( $this->type ) . '_Utility';

		// If the class doesn't exist, throw an exception.
		if ( ! class_exists( $class ) ) {
			throw new Exception( "Invalid object type: $this->type" );
		}

		return $class;
	}

	/**
	 * Call a method on the utility class for this object.
	 *
	 * @param string $method The method to call.
	 * @param mixed  ...$params The parameters to pass to the method.
	 * @return mixed The result of the method call.
	 */
	private function call_utility_method( string $method, mixed ...$params ): mixed {
		// Handle null key.
		if ( ! $this->key ) {
			return null;
		}

		// Get the name of the utility class.
		$utility_class = $this->get_utility_class_name();

		try {
			// Call the method on the utility class.
			return $utility_class::$method( $this->key, ...$params );
		} catch ( Throwable $e ) {
			debug( "EXCEPTION calling $utility_class::$method({$this->key})", $e->getMessage() );
			return null;
		}
	}

	/**
	 * Check if the object exists.
	 *
	 * @return bool True if the object exists, false otherwise.
	 */
	public function exists(): bool {
		return $this->call_utility_method( 'exists' ) ?? false;
	}

	/**
	 * Load the object.
	 *
	 * @return mixed The object or null if not found.
	 */
	public function load(): mixed {
		// Call the method on the utility class.
		return $this->call_utility_method( 'load' );
	}

	/**
	 * Get the name or title of the object.
	 *
	 * @return ?string The name or title of the object, or null if not found.
	 */
	public function get_name(): ?string {
		// Call the method on the utility class.
		return $this->call_utility_method( 'get_name' );
	}

	/**
	 * Get the core properties of the object.
	 *
	 * @return ?array The core properties of the object or null if not found.
	 * @throws Exception If the object type is unknown.
	 */
	public function get_core_properties(): ?array {
		// Call the method on the utility class.
		return $this->call_utility_method( 'get_core_properties' );
	}

	/**
	 * Gets the link or span element showing the object name.
	 *
	 * @return string The HTML for the link or span element.
	 * @throws Exception If the object type is invalid or the object ID is null.
	 */
	public function get_tag(): string {
		// For options, show the option name(s).
		if ( ! $this->key && $this->type === 'option' ) {
			return (string) $this->name;
		}

		// Call the method on the utility class.
		return $this->call_utility_method( 'get_tag', $this->name ) ?? '';
	}
}
