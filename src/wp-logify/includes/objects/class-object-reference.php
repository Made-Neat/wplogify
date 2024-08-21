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
	 * This will be an integer for object types with an integer ID, like posts, users, terms, and
	 * comments, and a string for those object types identified by a unique string value like a name
	 * or filename, such as taxonomy, plugin, and theme. In the case of options, it will be null.
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
	 * Create a new Object_Reference from:
	 *   - a WordPress object
	 *   - an array, in the case of plugins
	 *   - a string, in the case of options
	 *
	 * @param object|array|string $wp_object The WordPress object, array, or string.
	 * @return self The new Object_Reference.
	 * @throws Exception If the object type is unknown or unsupported.
	 */
	public static function new_from_wp_object( object|array|string $wp_object ): Object_Reference {
		$type = null;
		$key  = null;
		$name = null;

		if ( $wp_object instanceof WP_Comment ) {
			$type = 'comment';
			$key  = $wp_object->comment_ID;
			$name = Comment_Utility::get_name( $key );
		} elseif ( is_string( $wp_object ) ) {
			$type = 'option';
			$key  = $wp_object;
			$name = Option_Utility::get_name( $wp_object );
		} elseif ( is_array( $wp_object ) ) {
			$type = 'plugin';
			$key  = $wp_object['Slug'];
			$name = $wp_object['Name'];
		} elseif ( $wp_object instanceof WP_Post ) {
			$type = 'post';
			$key  = $wp_object->ID;
			$name = $wp_object->post_title;
		} elseif ( $wp_object instanceof WP_Taxonomy ) {
			$type = 'taxonomy';
			$key  = $wp_object->name;
			$name = $wp_object->label;
		} elseif ( $wp_object instanceof WP_Term ) {
			$type = 'term';
			$key  = $wp_object->term_id;
			$name = $wp_object->name;
		} elseif ( $wp_object instanceof WP_Theme ) {
			$type = 'theme';
			$key  = $wp_object->get_stylesheet();
			$name = $wp_object->name;
		} elseif ( $wp_object instanceof WP_User ) {
			$type = 'user';
			$key  = $wp_object->ID;
			$name = User_Utility::get_name( $key );
		} else {
			throw new Exception( 'Unknown or unsupported object type.' );
		}

		return new Object_Reference( $type, $key, $name );
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
		// Get the name of the utility class.
		$utility_class = $this->get_utility_class_name();

		// Call the method on the utility class.
		return $utility_class::$method( ...$params );
	}

	/**
	 * Check if the object exists.
	 *
	 * @return bool True if the object exists, false otherwise.
	 */
	private function exists(): bool {
		try {
			// Call the method on the utility class.
			return $this->call_utility_method( 'exists', $this->key );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Load the object.
	 *
	 * @return mixed The object or null if not found.
	 */
	private function load(): mixed {
		try {
			// Call the method on the utility class.
			return $this->call_utility_method( 'load', $this->key );
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * Get the name or title of the object.
	 *
	 * @return string The name or title of the object, or Unknown if not found.
	 */
	public function get_name() {
		try {
			// Call the method on the utility class.
			return $this->call_utility_method( 'get_name', $this->key );
		} catch ( Throwable $e ) {
			return '';
		}
	}

	/**
	 * Get the core properties of the object.
	 *
	 * @return array The core properties of the object.
	 * @throws Exception If the object type is unknown.
	 */
	public function get_core_properties(): ?array {
		try {
			// Call the method on the utility class.
			return $this->call_utility_method( 'get_core_properties', $this->key );
		} catch ( Throwable $e ) {
			return array();
		}
	}

	/**
	 * Gets the link or span element showing the object name.
	 *
	 * @return string The HTML for the link or span element.
	 * @throws Exception If the object type is invalid or the object ID is null.
	 */
	public function get_tag() {
		try {
			// Call the method on the utility class.
			return $this->call_utility_method( 'get_tag', $this->key, $this->name );
		} catch ( Throwable $e ) {
			return '';
		}
	}
}
