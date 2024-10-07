<?php
/**
 * Contains the Property class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

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
	 * The name of the database table the property comes from, e.g. 'wp_posts'.
	 * This can be null if the property is not related to a database table.
	 *
	 * @var ?string
	 */
	public ?string $table_name;

	/**
	 * The old or current value of the property. This should not be null.
	 *
	 * @var mixed
	 */
	public mixed $val = null;

	/**
	 * The new value of the property, if changed. This will be null if the value wasn't changed.
	 *
	 * @var mixed
	 */
	public mixed $new_val = null;

	/**
	 * Property constructor.
	 *
	 * @param string  $key        The property name.
	 * @param ?string $table_name The name of the database table the property belongs to (e.g.
	 *                            'posts'), which could be null if the property is unrelated to a
	 *                            database table.
	 * @param mixed   $val        The old or current value of the property.
	 * @param mixed   $new_val    Optional. The new value of the property, if changed.
	 */
	public function __construct( string $key, ?string $table_name = null, mixed $val = null, mixed $new_val = null ) {
		$this->key        = $key;
		$this->table_name = $table_name;
		$this->val        = $val;
		$this->new_val    = $new_val;
	}

	/**
	 * Given a property's values, update a property within an array of properties.
	 * If the property with the given key isn't in the array, create a new one and add it.
	 *
	 * @param array<string, Property> $props      The array of properties to update.
	 * @param string                  $key        The property key.
	 * @param ?string                 $table_name The name of the database table the property belongs to.
	 * @param mixed                   $val        The old or current value of the property.
	 * @param mixed                   $new_val    Optional. The new value of the property, if changed.
	 * @return void
	 */
	public static function update_array( array &$props, string $key, ?string $table_name, mixed $val, mixed $new_val = null ): void {
		if ( key_exists( $key, $props ) ) {
			// If the array already contains a property with this key, update it with the new values.
			$props[ $key ]->table_name = $table_name;
			$props[ $key ]->val        = $val;
			$props[ $key ]->new_val    = $new_val;
		} else {
			// If it doesn't, create a new Property object and add it to the array.
			$props[ $key ] = new Property( $key, $table_name, $val, $new_val );
		}
	}
}
