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
	 * Given a property's values, update a property within an array of properties.
	 * If the property with the given key isn't in the array, create it.
	 *
	 * @param array   $props      The array of properties to update.
	 * @param string  $key        The property key.
	 * @param ?string $table_name The name of the database table the property belongs to.
	 * @param mixed   $val        The old or current value of the property.
	 * @param mixed   $new_val    Optional. The new value of the property, if changed.
	 * @return bool True if a new property was created, false if an existing one was updated.
	 */
	public static function update_array( array &$props, string $key, ?string $table_name, mixed $val, mixed $new_val = null ) {
		// See if the array already contains a property with this key.
		$prop = self::get_from_array( $props, $key );

		if ( $prop ) {
			// If it does, update the Property object with the new values.
			$prop->table_name = $table_name;
			$prop->val        = $val;
			$prop->new_val    = $new_val;
			return false;
		} else {
			// If it doesn't, create a new Property object and add it to the array.
			$props[] = new Property( $key, $table_name, $val, $new_val );
			return true;
		}
	}

	/**
	 * Given a Property object, update an array of properties to include it.
	 *
	 * If a property with the given key is already in the array, replace it.
	 * Otherwise, add the given property to the array.
	 *
	 * @param array $props The array of properties to update.
	 * @param self  $prop  The Property object to add into the array.
	 * @return bool True if the new property was added, false if an existing one was replaced.
	 */
	public static function add_to_array( array &$props, self $prop ): bool {
		// Look for an existing property with a matching key.
		for ( $i = 0; $i < count( $props ); $i++ ) {
			if ( $props[ $i ]->key === $prop->key ) {
				// Found a match. Replace the existing property with the new one.
				$props[ $i ] = $prop;
				// We're done.
				return false;
			}
		}

		// If no macthing existing property was found, add the new Property object to the array.
		$props[] = $prop;
		return true;
	}

	/**
	 * Remove a property in an array of properties, searching by key.
	 *
	 * @param array  $props The array of properties to update.
	 * @param string $key   The key to search for.
	 * @return ?Property The property if it was found, otherwise null.
	 */
	public static function remove_from_array( array &$props, string $key ): ?Property {
		// Temporary array to hold properties that do not match the key.
		$props2 = array();

		// The removed property, if found.
		$removed_prop = null;

		foreach ( $props as $prop ) {
			if ( $prop->key === $key ) {
				// Found the property to remove.
				$removed_prop = $prop;
				// Do not add it to $props2; this effectively removes it.
			} else {
				// Keep the property as it doesn't match the key to remove.
				$props2[] = $prop;
			}
		}

		// Check if we found the property to remove.
		if ( $removed_prop !== null ) {
			// Update the original $props array with the filtered properties.
			$props = $props2;
		}

		// Return the removed property.
		return $removed_prop;
	}
}
