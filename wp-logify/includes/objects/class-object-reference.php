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
	 * This will be null for objects identified by their name, e.g. plugins and themes.
	 *
	 * @var ?int
	 */
	public ?int $id;

	/**
	 * The name of the object.
	 *
	 * @var ?string
	 */
	public string $name;

	/**
	 * The object itself.
	 * This is a private field. To access publically, call get_object(), which will lazy-load the
	 * object as needed.
	 *
	 * @var mixed
	 */
	private mixed $object;

	/**
	 * Constructor.
	 *
	 * @param string           $type The type of the object.
	 * @param ?int             $id The ID of the object.
	 * @param null|string|true $name The name of the object, or a bool to specify setting it
	 *                               automatically from the object.
	 *                               - If a string, the name will be assigned this value.
	 *                               - If true, the name will be extracted from the existing object.
	 *                               - If null, the name won't be set.
	 */
	public function __construct( string $type, ?int $id, null|string|true $name = true ) {
		// Set the object type.
		$this->type = $type;

		// Set the object id.
		$this->id = $id;

		if ( is_string( $name ) ) {
			$this->name = $name;
		} elseif ( $name ) {
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

		// Load the object based on the type.
		switch ( $this->type ) {
			case 'post':
			case 'revision':
				$this->object = Posts::load( $this->id );
				return;

			case 'user':
				$this->object = Users::load( $this->id );
				return;

			case 'term':
				$this->object = Terms::load( $this->id );
				return;

			case 'plugin':
				$this->object = Plugins::load( $this->name );
				return;

			default:
				throw new Exception( 'Unknown object type.' );
		}
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
	 * Get the name or title of the object.
	 *
	 * @return string The name or title of the object.
	 * @throws Exception If the object type is unknown.
	 */
	public function get_name() {
		switch ( $this->type ) {
			case 'post':
			case 'revision':
				return $this->get_object()->title;

			case 'user':
				return Users::get_name( $this->id );

			case 'term':
				return $this->get_object()->name;

			case 'plugin':
				return $this->name;

			case 'theme':
				return $this->name;

			default:
				throw new Exception( 'Unknown object type.' );
		}
	}

	/**
	 * Gets the link or span element showing the object name.
	 *
	 * @return string The HTML for the link or span element.
	 * @throws Exception If the object type is invalid or the object ID is null.
	 */
	public function get_tag() {
		// Generate the link based on the object type.
		switch ( $this->type ) {
			case 'post':
				// Return the post tag.
				return Posts::get_tag( $this->id, $this->name );

			case 'revision':
				// Return the revision tag.
				return Posts::get_revision_tag( $this->id );

			case 'user':
				// Return the user tag.
				return Users::get_tag( $this->id, $this->name );

			case 'term':
				// Return the term tag.
				return Terms::get_tag( $this->id, $this->name );

			case 'comment':
				// Return the comment tag.
				return $this->name;

			case 'plugin':
				// Return the plugin tag.
				return Plugins::get_tag( $this->name );

			case 'setting':
				// Return the setting tag.
				return $this->name;

			case 'theme':
				// Return the theme tag.
				return $this->name;
				// return Themes::get_tag( $this->name );

				// Return a link to the theme.
				// return "<a href='/wp-admin/theme-editor.php?theme={$theme->stylesheet}'>{$theme->name}</a>";
		}

		// If the object type is invalid, throw an exception.
		throw new Exception( "Invalid object type: $this->type" );
	}

	/**
	 * Get the core properties of the object.
	 *
	 * @return array The core properties of the object.
	 * @throws Exception If the object type is unknown.
	 */
	// public function get_core_properties(): ?array {
	// switch ( $this->type ) {
	// case 'post':
	// return Posts::get_core_properties( $this->id );

	// case 'user':
	// return Users::get_core_properties( $this->id );

	// case 'term':
	// return null;
	// return Terms::get_core_properties( $this->id );

	// case 'comment':
	// return null;
	// return Comments::get_core_properties( $this->id );

	// case 'plugin':
	// return null;
	// return Plugins::get_core_properties( $this->id );

	// case 'theme':
	// return null;
	// return Themes::get_core_properties( $this->id );

	// default:
	// throw new Exception( 'Unknown object type.' );
	// }
	// }
}
