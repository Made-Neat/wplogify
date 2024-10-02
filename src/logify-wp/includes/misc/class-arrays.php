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
	 * Checks if a value is a list.
	 *
	 * A value is considered a list if it's an array with keys that are sequential integers starting from 0.
	 *
	 * This function is included in PHP 8.1 as array_is_list().
	 * However, the plugin is designed to run on PHP 8.0 and later.
	 *
	 * @see https://www.php.net/manual/en/function.array-is-list.php
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if the value is a list, false otherwise.
	 */
	public static function is_list( mixed $value ): bool {
		// Check if the value is an array.
		if ( ! is_array( $value ) ) {
			return false;
		}

		// Use the array_is_list() function (introduced in PHP 8.1) if it exists.
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $value );
		}

		// Check if the array is empty.
		if ( empty( $value ) ) {
			return true;
		}

		// Check if the keys are sequential integers starting from 0.
		$expected_key = 0;
		foreach ( $value as $key => $_ ) {
			if ( $key !== $expected_key ) {
				return false;
			}
			++$expected_key;
		}
		return true;
	}

	/**
	 * Checks if a value is a map.
	 *
	 * A value is considered a map if it's an array but not a list.
	 *
	 * @see Arrays::is_list()
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if the value is a map, false otherwise.
	 */
	public static function is_map( mixed $value ): bool {
		return is_array( $value ) && ! self::is_list( $value );
	}
}
