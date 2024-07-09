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
class Entity {

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
	 * @param string           $type The type of the object.
	 * @param int              $id The ID of the object.
	 * @param null|string|bool $name The name of the object.
	 *                         If a string, the name will be assigned this value.
	 *                         If true, the name will be copied from the existing object.
	 *                         If false or null, the name won't be set.
	 */
	public function __construct( string $type, int $id, null|string|bool $name = null ) {
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

		$method = array( self::get_class(), "get_{$this->type}" );
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
	 * @throws Exception If the object type is invalid.
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
	 * Get the edit URL for the object.
	 *
	 * @return string The URL.
	 */
	public function get_edit_url(): string {
		$method = array( self::get_class(), 'get_edit_url' );
		return call_user_func( $method, $this->id );
	}

	/**
	 * Get the HTML for the link to the object's edit page.
	 *
	 * @return string The link HTML.
	 */
	public function get_edit_link() {
		$url   = $this->get_edit_url();
		$name  = $this->get_name();
		$class = "wp-logify-{$this->type}-link";
		return "<a href='$url' class='wp-logify-object-link $class'>$name</a>";
	}

	/**
	 * If the object hasn't been deleted, get a link to its edit page; otherwise, get a deleted label.
	 */
	public function get_element() {
		try {
			$this->get_object();
			return $this->get_edit_link();
		} catch ( Exception $e ) {
			// Make a 'deleted' span.
			$name = empty( $this->name ) ? 'Unknown' : $this->name;
			return "<span class='wp-logify-deleted-label'>$name (deleted)</span>";
		}
	}

	/**
	 * Convert the Object_Reference to an array.
	 *
	 * @return array The array representation of the Object_Reference.
	 */
	public function to_array(): array {
		return array(
			'type' => $this->type,
			'id'   => $this->id,
			'name' => $this->name,
		);
	}

	/**
	 * Convert the Object_Reference to a JSON string.
	 */
	public function to_json(): string {
		return Json::encode( $this->to_array() );
	}

	/**
	 * Create an Object_Reference from a JSON string.
	 *
	 * @param string $json The JSON string.
	 * @return Entity The new Object_Reference object.
	 */
	public static function from_json( string $json ): self {
		$fields = Json::decode( $json );
		return new self( $fields['type'], $fields['id'], $fields['name'] );
	}
}
