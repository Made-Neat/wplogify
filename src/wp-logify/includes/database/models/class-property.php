<?php
/**
 * Contains the Property class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;

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
	 * Find a property in an array of properties, searching by key.
	 *
	 * @param array  $props An array of properties to search.
	 * @param string $key   The key to search for.
	 * @return ?Property The property object if found, or null if not found.
	 */
	public static function get_from_array( array $props, string $key ): ?Property {
		foreach ( $props as $prop ) {
			if ( $prop->key === $key ) {
				return $prop;
			}
		}
		return null;
	}

	/**
	 * Add a property to, or update a property in, an array of properties.
	 *
	 * @param array   $props      The array of properties to update.
	 * @param string  $key        The property key.
	 * @param ?string $table_name The name of the database table the property belongs to.
	 * @param mixed   $val        The old or current value of the property.
	 * @param mixed   $new_val    Optional. The new value of the property, if changed.
	 */
	public static function update_array( array &$props, string $key, ?string $table_name, mixed $val, mixed $new_val = null ) {
		// See if the array already contains a property with this key.
		$prop = self::get_from_array( $props, $key );

		if ( $prop ) {
			// If it does, update the Property object with the new values.
			$prop->table_name = $table_name;
			$prop->val        = $val;
			$prop->new_val    = $new_val;
		} else {
			// If it doesn't, create a new Property object and add it to the array.
			$props[] = new Property( $key, $table_name, $val, $new_val );
		}
	}

	/**
	 * Update an array of properties from a Property object.
	 *
	 * @param array $props The array of properties to update.
	 * @param self  $prop  The Property object to copy into the array.
	 */
	public static function update_array_from_prop( array &$props, self $prop ) {
		self::update_array( $props, $prop->key, $prop->table_name, $prop->val, $prop->new_val );
	}

	/**
	 * Remove a property in an array of properties, searching by key.
	 *
	 * @param array  $props The array of properties to update.
	 * @param string $key   The key to search for.
	 * @return bool True if the property was found and removed, otherwise false.
	 */
	public static function remove_from_array( array &$props, string $key ): bool {
		$props2 = array(); // Temporary array to hold properties that do not match the key.
		$result = false;   // Flag to indicate if the property was found and removed.

		foreach ( $props as $prop ) {
			if ( $prop->key === $key ) {
				// Found the property to remove.
				$result = true;
				// Do not add it to $props2; this effectively removes it.
			} else {
				// Keep the property as it doesn't match the key to remove.
				$props2[] = $prop;
			}
		}

		if ( $result ) {
			// Update the original $props array with the filtered properties.
			$props = $props2;
		}

		// Return true if the property was found and removed; otherwise, return false.
		return $result;
	}
}
