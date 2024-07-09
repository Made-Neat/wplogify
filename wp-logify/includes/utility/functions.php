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
function debug( ...$args ) {
	// If no arguments are provided, do nothing.
	if ( empty( $args ) ) {
		return;
	}

	// Convert each argument to a string representation.
	$strings = array_map( fn( $arg ) => var_export( $arg, true ), $args );

	// Log the strings.
	error_log( implode( ', ', $strings ) );
}

/**
 * Dump a SQL query into the error log.
 */
function debug_sql( string $sql ) {
	global $wpdb;
	debug( $wpdb->remove_placeholder_escape( $sql ) );
}

/**
 * Convert a value to a string for comparison and display.
 *
 * @param mixed $value The value to convert.
 * @return string The value as a string.
 */
function value_to_string( mixed $value ) {
	return is_string( $value ) ? $value
		: ( is_scalar( $value ) ? (string) $value : Json::encode( $value ) );
}
