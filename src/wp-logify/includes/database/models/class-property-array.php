<?php
/**
 * Useful methods for working with arrays of properties.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Static methods for working with arrays of properties.
 */
class Property_Array {

	/**
	 * See if the array has a property with the given key.
	 *
	 * @param array<string, Property> $props The array of properties to search.
	 * @param string                  $key   The key to search for.
	 * @return bool True if the array has a property with the given key, false otherwise.
	 */
	public static function has( array $props, string $key ): bool {
		return key_exists( $key, $props );
	}

	/**
	 * Find a property by key.
	 *
	 * @param array<string, Property> $props The array of properties to search.
	 * @param string                  $key   The key to search for.
	 * @return ?Property The property object if found, or null if not found.
	 */
	public static function get( array $props, string $key ): ?Property {
		return $props[ $key ] ?? null;
	}

	/**
	 * Given a property's values, update a property within the array.
	 * If the property with the given key isn't in the array, create a new one and add it.
	 *
	 * @param array<string, Property> $props      The array of properties to update.
	 * @param string                  $key        The property key.
	 * @param ?string                 $table_name The name of the database table the property belongs to.
	 * @param mixed                   $val        The old or current value of the property.
	 * @param mixed                   $new_val    Optional. The new value of the property, if changed.
	 * @return void
	 */
	public static function set( array &$props, string $key, ?string $table_name, mixed $val, mixed $new_val = null ): void {
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

	/**
	 * Given a Property object, update an array of properties to include it.
	 *
	 * If a property with the given key is already in the array, replace it.
	 * Otherwise, add the given property to the array.
	 *
	 * @param array<string, Property> $props The array of properties to update.
	 * @param Property                $prop  The Property object to add into the array.
	 * @return void
	 */
	public static function add( array &$props, Property $prop ): void {
		// Add the new Property object to the array.
		$props[ $prop->key ] = $prop;
	}

	/**
	 * Remove a property in an array of properties, searching by key.
	 *
	 * @param string $key The key of the property to remove.
	 * @return void
	 */
	public static function remove( array &$props, string $key ): void {
		// Remove the property from the array.
		unset( $props[ $key ] );
	}
}
