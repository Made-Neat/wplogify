<?php
/**
 * Contains the Types class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DateTime;

/**
 * Miscellaneous general-purpose static methods for working with values of different types.
 */
class Types {

	/**
	 * Process a database value, converting it to a more usable type if necessary.
	 *
	 * @param string $key The database key (like a column name or meta key).
	 * @param mixed  $value The value.
	 * @return mixed The converted or original value.
	 */
	public static function process_database_value( string $key, mixed $value ): mixed {
		// Handle nulls.
		if ( $value === null ) {
			return null;
		}

		// Default result is the unaltered value.
		$result = $value;

		// If the value is a PHP-serialized array, deserialize it.
		if ( is_string( $result ) && Serialization::try_unserialize( $result, $unserialized_result ) ) {
			$result = $unserialized_result;
		}

		// Convert non-string values stored as strings into their correct types.
		if ( is_string( $result ) ) {
			if ( Strings::looks_like_null( $result ) ) {
				$result = null;
			} elseif ( Strings::looks_like_bool( $result ) ) {
				$result = $result === 'true' ? true : false;
			} elseif ( Strings::looks_like_int( $result ) ) {
				$result = (int) $result;
			} elseif ( Strings::looks_like_float( $result ) ) {
				$result = (float) $result;
			} elseif ( Strings::looks_like_datetime( $result ) ) {
				$tz     = substr( $key, -4 ) === '_gmt' ? 'UTC' : 'site';
				$result = DateTimes::create_datetime( $result, $tz );
			}
		}

		return $result;
	}

	/**
	 * Check if two values are equal by value and type.
	 *
	 * Solves the problem of two object references being considered unequal when they refer to
	 * different objects with equal property values.
	 *
	 * @param mixed $value1 The first value.
	 * @param mixed $value2 The second value.
	 * @return bool Whether the values are equal.
	 */
	public static function are_equal( mixed $value1, mixed $value2 ): bool {
		// Object comparison using JSON encoding.
		if ( is_object( $value1 ) && is_object( $value2 ) ) {
			return get_class( $value1 ) === get_class( $value2 ) && wp_json_encode( $value1 ) === wp_json_encode( $value2 );
		}

		// If not objects, compare directly.
		return $value1 === $value2;
	}

	/**
	 * Convert a value to a string for display.
	 *
	 * @param mixed $value The value to convert.
	 * @return string The value as a string.
	 */
	public static function value_to_string( mixed $value ) {
		if ( $value === null ) {
			return '';
		} elseif ( is_string( $value ) ) {
			return $value;
		} elseif ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		} elseif ( is_scalar( $value ) ) {
			return (string) $value;
		} elseif ( $value instanceof DateTime ) {
			return DateTimes::format_datetime_site( $value );
		} elseif ( $value instanceof Object_Reference ) {
			return $value->get_tag();
		} else {
			// Value is an array or another type of object.
			return wp_json_encode( $value );
		}
	}

	/**
	 * Convert a value to an HTML string for display.
	 *
	 * @param string $key The key of the value.
	 * @param mixed  $value The value to convert.
	 * @return string The value as an HTML string.
	 */
	public static function value_to_html( string $key, mixed $value ): string {
		// Hide passwords.
		if ( $key === 'user_pass' ) {
			return empty( $value ) ? '' : '(hidden)';
		}

		// Expand arrays into mini-tables.
		if ( is_array( $value ) ) {

			// Handle empty arrays.
			if ( count( $value ) === 0 ) {
				return '';
			}

			// Start the table.
			$html = "<table>\n";

			// Check if the array is a list.
			$is_list = Arrays::is_list( $value );

			foreach ( $value as $key2 => $value2 ) {
				// Start the table row.
				$html .= '<tr>';

				// If it's not a list, show the key.
				if ( ! $is_list ) {
					$html .= '<th>' . Strings::key_to_label( $key2 ) . '</th>';
				}

				// Show the value.
				$html .= '<td>' . self::value_to_html( $key2, $value2 ) . '</td>';

				// End the table row.
				$html .= '</tr>';
			}

			// End the table.
			$html .= "</table>\n";

			return $html;
		}

		// Convert to plain text string.
		return self::value_to_string( $value );
	}
}
