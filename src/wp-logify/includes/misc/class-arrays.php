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

	/**
	 * Checks if an array is a list.
	 *
	 * An array is considered a list if its keys are sequential integers starting from 0.
	 *
	 * This function is included in PHP 8.1 as array_is_list().
	 * However, the plugin is designed to run on PHP 8.0 and later.
	 *
	 * @see https://www.php.net/manual/en/function.array-is-list.php
	 *
	 * @param array $array The array to check.
	 * @return bool True if the array is a list, false otherwise.
	 */
	public static function is_list( array $array ): bool {
		// Check if the array is empty.
		if ( empty( $array ) ) {
			return true;
		}

		// Get the keys of the array.
		$keys = array_keys( $array );

		// Check if the keys are sequential integers starting from 0.
		foreach ( $keys as $index => $key ) {
			if ( $index !== $key ) {
				return false;
			}
		}

		return true;
	}
}
