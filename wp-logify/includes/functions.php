<?php

namespace WP_Logify;

/**
 * Check if a key looks like an ID field.
 *
 * @param string $key The key to check.
 * @return bool Whether the key looks like an ID field.
 */
function key_is_id( string $key ) {
	$key = strtolower( $key );
	return $key === 'id' || substr( $key, -3 ) === '_id';
}

/**
 * Converts a string to an integer if it looks like one.
 *
 * @param mixed $value The value to convert.
 * @return mixed The converted value.
 */
function string_to_int( mixed $value ) {
	// Check if we should convert it.
	if ( is_string( $value ) ) {
		$int_value = (int) $value;
		if ( $value === (string) $int_value ) {
			return $int_value;
		}
	}

	// Return the original value.
	return $value;
}

/**
 * If the key looks like an ID field, convert the value to an integer.
 *
 * @param string $key The key to check.
 * @param mixed  $value The value to convert.
 * @return mixed The converted value.
 */
function make_id_int( string $key, mixed $value ) {
	return key_is_id( $key ) ? string_to_int( $value ) : $value;
}
