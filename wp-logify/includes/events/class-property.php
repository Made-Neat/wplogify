<?php
/**
 * Contains the Property class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Represents the type and the old and new values of an object property.
 */
class Property {

	/**
	 * The property ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * The event ID.
	 *
	 * @var int
	 */
	public int $event_id;

	/**
	 * The property name.
	 *
	 * @var string
	 */
	public string $prop_name;

	/**
	 * The type of database table the property belongs to: 'base', 'meta', or 'none'.
	 *
	 * @var string
	 */
	public string $prop_type;

	/**
	 * The old or current value of the property.
	 *
	 * @var mixed
	 */
	public mixed $old_value = null;

	/**
	 * The new value of the property, if changed.
	 *
	 * @var mixed
	 */
	public mixed $new_value = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Creates a new property object.
	 *
	 * @param int    $event_id The event ID.
	 * @param string $prop_name The property name.
	 * @param string $prop_type The type of database table the property belongs to: 'base', 'meta', or 'none'.
	 * @param mixed  $old_value The old or current value of the property.
	 * @param mixed  $new_value Optional. The new value of the property, if changed.
	 * @return Property The new property object.
	 */
	public static function create( int $event_id, string $prop_name, string $prop_type, mixed $old_value = null, mixed $new_value = null ): self {
		$prop            = new self();
		$prop->event_id  = $event_id;
		$prop->prop_name = $prop_name;
		$prop->prop_type = $prop_type;
		$prop->old_value = $old_value;
		$prop->new_value = $new_value;
		return $prop;
	}
}
