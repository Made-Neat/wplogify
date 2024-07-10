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
	public int $property_id;

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
	public string $property_key;

	/**
	 * The type of database table the property belongs to: 'base', 'meta', or 'none'.
	 *
	 * @var string
	 */
	public string $property_type;

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
	 * Property constructor.
	 *
	 * Initializes an empty Property object.
	 */
	public function __construct() {
		// Empty constructor.
	}

	/**
	 * Creates a new property object.
	 *
	 * @param ?int    $event_id The event ID if known.
	 * @param string  $property_key The property name.
	 * @param ?string $property_type The type of database table the property belongs to: 'base', 'meta', or null for none.
	 * @param mixed   $old_value The old or current value of the property.
	 * @param mixed   $new_value Optional. The new value of the property, if changed.
	 * @return Property The new property object.
	 */
	public static function create( ?int $event_id, string $property_key, ?string $property_type, mixed $old_value = null, mixed $new_value = null ): self {
		$property                = new self();
		$property->event_id      = $event_id;
		$property->property_key  = $property_key;
		$property->property_type = $property_type;
		$property->old_value     = $old_value;
		$property->new_value     = $new_value;
		return $property;
	}
}
