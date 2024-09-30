<?php
/**
 * Contains debugging functions.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;

/**
 * Convert a variable to a string representation suitable for the debug log.
 *
 * @param mixed $value The variable to convert.
 * @return string The string representation of the variable.
 */
function debug_var_to_string( mixed $value ): string {
	if ( $value === null ) {
		return 'null';
	} elseif ( is_string( $value ) ) {
		return '"' . $value . '"';
	} elseif ( is_bool( $value ) ) {
		return $value ? 'true' : 'false';
	} elseif ( is_scalar( $value ) ) {
		return (string) $value;
	} elseif ( $value instanceof DateTime ) {
		return DateTimes::format_datetime_site( $value );
	} elseif ( is_object( $value ) ) {
		return get_class( $value ) . ' ' . wp_json_encode( $value, JSON_PRETTY_PRINT );
	} else {
		return wp_json_encode( $value, JSON_PRETTY_PRINT );
	}
}

/**
 * Dump one or more variables into the error log.
 *
 * @param mixed ...$args The variable(s) to dump.
 */
function debug( ...$args ) {
	// Check if debug logging is enabled.
	if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
		return;
	}

	// If no arguments are provided, do nothing.
	if ( empty( $args ) ) {
		return;
	}

	// Convert each argument to a string representation.
	$strings = array_map( fn( $arg ) => debug_var_to_string( $arg ), $args );

	// Log the strings.
	error_log( implode( ', ', $strings ) );
}

/**
 * Dump an SQL query into the error log.
 *
 * @param string $sql The SQL query to dump.
 */
function debug_sql( string $sql ) {
	global $wpdb;
	debug( $wpdb->remove_placeholder_escape( $sql ) );
}
