<?php
/**
 * Contains the Arrays class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Miscellaneous static methods for working with arrays.
 */
class Arrays {

	/**
	 * Add a value to an array if it's not already in the array.
	 *
	 * @param array $array1 The array to add to.
	 * @param mixed $value  The value to add.
	 */
	public static function add_if_new( array &$array1, mixed $value ) {
		if ( ! in_array( $value, $array1, true ) ) {
			$array1[] = $value;
		}
	}
}
