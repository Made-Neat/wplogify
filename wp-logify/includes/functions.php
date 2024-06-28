<?php
/**
 * Contains debugging functions.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Dump one or more variables into the error log.
 *
 * @param mixed ...$args The variable(s) to dump.
 */
function debug_log( ...$args ) {
	// Ensure there is at least one argument.
	if ( empty( $args ) ) {
		return;
	}

	// Convert each argument to a string representation.
	$strings = array_map(
		function ( $arg ) {
			return is_string( $arg ) ? $arg : var_export( $arg, true );
		},
		$args
	);

	// Join the strings with ': ' separator.
	$debug_string = implode( ', ', array_filter( $strings ) );

	// Log the debug string.
	error_log( $debug_string );
}

/**
 * Convert a value to a string for comparison and display.
 *
 * @param mixed $value The value to convert.
 * @return string The value as a string.
 */
function value_to_string( mixed $value ) {
	return is_string( $value ) ? $value
		: ( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
}
