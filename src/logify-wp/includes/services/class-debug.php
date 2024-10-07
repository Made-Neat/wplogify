<?php
/**
 * Contains the Debug class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DateTime;

/**
 * Class Logify_WP\DEbug
 *
 * Contains debugging functions.
 */
class Debug {

	public const SEVERITY_INFO    = 1;
	public const SEVERITY_WARNING = 2;
	public const SEVERITY_ERROR   = 3;

	/**
	 * Convert a variable to a string representation suitable for the debug log.
	 *
	 * @param mixed $value The variable to convert.
	 * @return string The string representation of the variable.
	 */
	public static function var_to_string( mixed $value ): string {
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
	public static function log( int $severity, ...$args ) {
		// Check if debug logging is enabled.
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			error_log( 'Logging disabled' );
			return;
		}

		// If no arguments are provided, do nothing.
		if ( empty( $args ) ) {
			return;
		}

		// Convert each argument to a string representation.
		$strings = array_map( fn( $arg ): string => self::var_to_string( $arg ), $args );

		// Convert the severity constant into a string.
		$severity_string = match ( $severity ) {
			self::SEVERITY_INFO => 'INF',
			self::SEVERITY_WARNING => 'WRN',
			self::SEVERITY_ERROR => 'ERR',
			default => '???',
		};

		// Log the strings.
		error_log( $severity_string . ': ' . implode( ', ', $strings ) );
	}

	/**
	 * Dump one or more variables into the error log with severity INFO.
	 *
	 * @param mixed ...$args The variable(s) to dump.
	 */
	public static function info( ...$args ) {
		self::log( self::SEVERITY_INFO, ...$args );
	}

	/**
	 * Dump one or more variables into the error log with severity WARNING.
	 *
	 * @param mixed ...$args The variable(s) to dump.
	 */
	public static function warning( ...$args ) {
		self::log( self::SEVERITY_WARNING, ...$args );
	}

	/**
	 * Dump one or more variables into the error log with severity ERROR.
	 *
	 * @param mixed ...$args The variable(s) to dump.
	 */
	public static function error( ...$args ) {
		self::log( self::SEVERITY_ERROR, ...$args );
	}

	/**
	 * Dump an SQL query into the error log.
	 *
	 * @param string $sql The SQL query to dump.
	 */
	public static function sql( string $sql ) {
		global $wpdb;
		self::info( $wpdb->remove_placeholder_escape( $sql ) );
	}
}
