<?php
/**
 * Contains the Arrays class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Miscellaneous static methods for working with arrays.
 */
class Arrays {

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
