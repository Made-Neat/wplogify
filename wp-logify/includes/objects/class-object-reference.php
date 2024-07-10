<?php
/**
 * Contains the Object_Reference class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

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
	 * Get the string representation of the Object_Reference.
	 *
	 * @return string The string representation of the Object_Reference.
	 */
	public function __toString() {
		return $this->type . '|' . $this->id . '|' . $this->name;
	}

	/**
	 * Convert the object reference to a array suitable for encoding as JSON.
	 *
	 * @param Object_Reference $object_ref The Object_Reference to convert.
	 */
	public static function encode( Object_Reference $object_ref ): array {
		// Store DateTimes in ATOM/W3C format.
		return array( 'Object_Reference' => (string) $object_ref );
	}

	/**
	 * Check if the value expresses a valid Object_Reference.
	 *
	 * @param mixed            $value    The value to check.
	 * @param Object_Reference $object_ref The Object_Reference object to populate if valid.
	 * @return bool    If the JSON contains a valid date-time string.
	 */
	public static function is_encoded_object_reference( mixed $value, Object_Reference &$object_ref ): bool {
		$result = false;

		// Check if the value is an object or an array.
		if ( ! is_object( $value ) && ! is_array( $value ) ) {
			return false;
		}

		// Convert to an array if necessary.
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		// Check it looks right.
		if ( count( $value ) !== 1 || empty( $value['Object_Reference'] ) ) {
			return false;
		}

		// Try to convert the string to a Object_Reference.
		try {
			list( $type, $id, $name ) = explode( '|', $value['Object_Reference'] );
			$object_ref               = new self( $type, (int) $id, $name );
			$result                   = true;
		} catch ( Exception $ex ) {
			debug( 'Invalid Object_Reference encoding', $value['Object_Reference'], $ex->getMessage() );
		}

		return $result;
	}
}
