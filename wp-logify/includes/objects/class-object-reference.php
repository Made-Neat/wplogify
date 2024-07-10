<?php
/**
 * Contains the Object_Reference class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use InvalidArgumentException;
use Exception;

/**
 * Represents a reference to an WordPress object that can be created, updated, or deleted.
 */
class Object_Reference {

	/**
	 * The type of the object, e.g. 'post', 'user', 'term'.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * The ID of the object.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * The name of the object.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * The object itself.
	 * This is a private field. To access publically, call getObject(), which will lazy-load the
	 * object as needed.
	 *
	 * @var mixed
	 */
	private mixed $object;

	/**
	 * Constructor.
	 *
	 * @param string      $type The type of the object.
	 * @param int         $id The ID of the object.
	 * @param string|bool $name The name of the object, or a bool to specify setting it automatically from the object.
	 *                          - If a string, the name will be assigned this value.
	 *                          - If true, the name will be extracted from the existing object.
	 *                          - If false, the name won't be set.
	 */
	public function __construct( string $type, int $id, string|bool $name = true ) {
		// Set the object type.
		$this->type = $type;

		// Set the object id.
		$this->id = $id;

		if ( is_string( $name ) ) {
			$this->name = $name;
		} elseif ( $name === true ) {
			$this->name = $this->get_name();
		}
	}

	/**
	 * Get the class name for the object's type.
	 */
	public function get_class(): string {
		return 'WP_Logify\\' . ucfirst( $this->type ) . 's';
	}

	/**
	 * Load an object.
	 *
	 * @throws Exception If the object cannot be loaded or if the object type is unknown.
	 */
	public function load() {
		// Check we know which object to load.
		if ( empty( $this->type ) || empty( $this->id ) ) {
			throw new Exception( 'Cannot load an object without knowing its type and ID.' );
		}

		// Call the appropriate get method.
		$method = array( self::get_class(), 'get_' . $this->type );
		return call_user_func( $method, $this->id );
	}

	/**
	 * Load the object it hasn't already been loaded.
	 *
	 * @return mixed The object.
	 */
	public function get_object() {
		if ( ! isset( $this->object ) ) {
			$this->load();
		}

		return $this->object;
	}

	/**
	 * Get the name or title of the object.
	 *
	 * @return string The name or title of the object.
	 * @throws Exception If the object type is unknown.
	 */
	public function get_name() {
		switch ( $this->type ) {
			case 'post':
				return $this->get_object()->title;

			case 'user':
				return Users::get_name( $this->id );

			case 'term':
				return $this->get_object()->name;

			default:
				throw new Exception( 'Unknown object type.' );
		}
	}

	/**
	 * Convert the object reference to a array suitable for encoding as JSON.
	 *
	 * @param Object_Reference $object_ref The Object_Reference to convert.
	 * @return array The array representation of the Object_Reference.
	 */
	public static function encode( Object_Reference $object_ref ): array {
		return array( 'Object_Reference' => (array) $object_ref );
	}

	/**
	 * Check if the value expresses a valid Object_Reference.
	 *
	 * @param object            $simple_object The value to check.
	 * @param ?Object_Reference $object_ref The Object_Reference object to populate if valid.
	 * @return bool If the JSON contains a valid date-time string.
	 * @throws InvalidArgumentException If the provided array does not represent an Object_Reference.
	 */
	public static function is_encoded_object( object $simple_object, ?Object_Reference &$object_ref ): bool {
		// Convert to an array.
		$ary = (array) $simple_object;

		// Check it looks right.
		if ( count( $ary ) !== 1 || empty( $ary['Object_Reference'] ) || ! is_object( $ary['Object_Reference'] ) ) {
			return false;
		}

		// Convert the simple object to a Object_Reference.
		$object_ref = self::__set_state( (array) $ary['Object_Reference'] );

		return true;
	}

	/**
	 * Create a new Object_Reference from an array.
	 *
	 * @param array $fields The array representation of the Object_Reference.
	 * @return Object_Reference The Object_Reference object.
	 * @throws InvalidArgumentException If the provided array does not represent an Object_Reference.
	 */
	public static function __set_state( array $fields ) {
		// Check the provided array has the right number and type of properties.
		if ( count( $fields ) !== 3 || ! key_exists( 'type', $fields ) || ! key_exists( 'id', $fields ) || ! key_exists( 'name', $fields )
			|| ! is_string( $fields['type'] ) || ! is_int( $fields['id'] ) || ! is_string( $fields['name'] ) ) {
			throw new InvalidArgumentException( 'The provided array does not represent an Object_Reference.' );
		}

		// Create the new object.
		return new self( $fields['type'], $fields['id'], $fields['name'] );
	}
}
