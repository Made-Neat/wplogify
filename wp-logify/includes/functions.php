<?php
/**
 * Contains utility functions.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Check if a string looks like a null.
 *
 * @param string $value The value to check.
 * @return bool Whether the value looks like a null.
 */
function looks_like_null( string $value ): bool {
	return $value === 'null';
}

/**
 * Check if a string looks like a boolean.
 *
 * @param string $value The value to check.
 * @return bool Whether the value looks like a boolean.
 */
function looks_like_bool( string $value ): bool {
	return $value === 'true' || $value === 'false';
}

/**
 * Check if a string looks like an integer.
 *
 * @param string $value The value to check.
 * @return bool Whether the value looks like an integer.
 */
function looks_like_int( string $value ): bool {
	return $value === (string) (int) $value;
}

/**
 * Check if a string looks like a floating point value.
 *
 * @param string $value The value to check.
 * @return bool Whether the value looks like a floating point value.
 */
function looks_like_float( string $value ): bool {
	return $value === (string) (float) $value;
}

/**
 * Check if a string looks like a MySQL datetime.
 *
 * @param string $value The value to check.
 * @return bool Whether the value looks like a MySQL datetime.
 */
function looks_like_datetime( string $value ): bool {
	return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) === 1;
}

/**
 * Process a database value, converting it to a more usable type if necessary.
 *
 * @param string $key The database key (like a column name or meta key).
 * @param mixed  $value The value.
 * @return mixed The converted or original value.
 */
function process_database_value( string $key, mixed $value ): mixed {
	// Default.
	$result = $value;

	// If the value is an array and there's only one value, reduce the result to that value.
	if ( is_array( $result ) && count( $result ) === 1 && key_exists( 0, $result ) ) {
		$result = $result[0];
	}

	// If the value is a PHP-serialized array, deserialize it.
	if ( is_string( $result ) ) {
		$result = Serialization::unserialize( $result );
	}

	// Convert non-string values stored as strings into their correct types.
	if ( is_string( $result ) ) {
		if ( looks_like_null( $result ) ) {
			$result = null;
		} elseif ( looks_like_bool( $result ) ) {
			$result = $result === 'true' ? true : false;
		} elseif ( looks_like_int( $result ) ) {
			$result = (int) $result;
		} elseif ( looks_like_float( $result ) ) {
			$result = (float) $result;
		} elseif ( looks_like_datetime( $result ) ) {
			$tz     = substr( $key, -4 ) === '_gmt' ? 'UTC' : 'site';
			$result = DateTimes::create_datetime( $result, $tz );
		}
	}

	return $result;
}

/**
 * Check if two values are equal by value and type.
 *
 * Solves the problem of two object references being considered unequal when they refer to different
 * objects with equal property values.
 *
 * @param mixed $value1 The first value.
 * @param mixed $value2 The second value.
 * @return bool Whether the values are equal.
 */
function are_equal( mixed $value1, mixed $value2 ): bool {
	// Object comparison using JSON encoding.
	if ( is_object( $value1 ) && is_object( $value2 ) ) {
		return get_class( $value1 ) === get_class( $value2 ) && wp_json_encode( $value1 ) === wp_json_encode( $value2 );
	}

	// If not objects, compare directly.
	return $value1 === $value2;
}
