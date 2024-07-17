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
	 * The property name or key.
	 *
	 * @var string
	 */
	public string $key;

	/**
	 * The type of database table the property belongs to: 'base', 'meta', or 'other'.
	 *
	 * @var string
	 */
	public string $type;

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
	 * @param string  $property_key The property name.
	 * @param ?string $property_type The type of database table the property belongs to: 'base', 'meta', or null for none.
	 * @param mixed   $old_value The old or current value of the property.
	 * @param mixed   $new_value Optional. The new value of the property, if changed.
	 */
	public function __construct( string $property_key, ?string $property_type, mixed $old_value = null, mixed $new_value = null ) {
		$this->key       = $property_key;
		$this->type      = $property_type;
		$this->old_value = $old_value;
		$this->new_value = $new_value;
	}
}
