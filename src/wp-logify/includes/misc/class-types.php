<?php
/**
 * Contains the Types class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;

/**
 * Miscellaneous general-purpose static methods for working with values of different types.
 */
class Types {

	/**
	 * Check if a string looks like a null.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like a null.
	 */
	public static function looks_like_null( string $value ): bool {
		return $value === 'null';
	}

	/**
	 * Check if a string looks like a boolean.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like a boolean.
	 */
	public static function looks_like_bool( string $value ): bool {
		return $value === 'true' || $value === 'false';
	}

	/**
	 * Check if a string looks like an integer.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like an integer.
	 */
	public static function looks_like_int( string $value ): bool {
		return $value === (string) (int) $value;
	}

	/**
	 * Check if a string looks like a floating point value.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like a floating point value.
	 */
	public static function looks_like_float( string $value ): bool {
		return $value === (string) (float) $value;
	}

	/**
	 * Check if a string looks like a MySQL datetime.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like a MySQL datetime.
	 */
	public static function looks_like_datetime( string $value ): bool {
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) === 1;
	}

	/**
	 * Check if a value is null or an empty string.
	 *
	 * @param mixed $value The value to check.
	 * @return bool Whether the value is null or an empty string.
	 */
	public static function is_null_or_empty_string( mixed $value ): bool {
		return $value === null || $value === '';
	}

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

		// If the value is an array and there's only one value, reduce the result to that value.
		if ( is_array( $result ) && count( $result ) === 1 && key_exists( 0, $result ) ) {
			$result = $result[0];
		}

		// If the value is a PHP-serialized array, deserialize it.
		if ( is_string( $result ) && Serialization::try_unserialize( $result, $unserialized_result ) ) {
			$result = $unserialized_result;
		}

		// Convert non-string values stored as strings into their correct types.
		if ( is_string( $result ) ) {
			if ( self::looks_like_null( $result ) ) {
				$result = null;
			} elseif ( self::looks_like_bool( $result ) ) {
				$result = $result === 'true' ? true : false;
			} elseif ( self::looks_like_int( $result ) ) {
				$result = (int) $result;
			} elseif ( self::looks_like_float( $result ) ) {
				$result = (float) $result;
			} elseif ( self::looks_like_datetime( $result ) ) {
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
				return '(None)';
			}

			// Start the table.
			$html = "<table>\n";

			// Check if the array is a list.
			$is_list = array_is_list( $value );

			foreach ( $value as $key2 => $value2 ) {
				// Start the table row.
				$html .= '<tr>';

				// If it's not a list, show the key.
				if ( ! $is_list ) {
					$html .= "<th>$key2</th>";
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

	/**
	 * Convert a camel-case string into an array of words.
	 *
	 * @param string $str The camel-case string.
	 * @return array The array of words.
	 */
	public static function camel_case_to_words( string $str ): array {
		// Split the camel-case string into words.
		return preg_split( '/(?<!^)(?=[A-Z])/', $str );
	}

	/**
	 * Make a key readable.
	 *
	 * This function takes a key and makes it more readable by converting it to title case and
	 * replacing underscores with spaces.
	 *
	 * @param ?string $key    The key to make readable. Could be null.
	 * @param bool    $ucwords Whether to capitalize the first letter of each word.
	 * @return string The readable key.
	 */
	public static function make_key_readable( ?string $key, bool $ucwords = false ): string {
		// Handle null key.
		if ( $key === null ) {
			return '';
		}

		// Handle some special, known cases.
		switch ( $key ) {
			case 'blogname':
				return 'Blog name';

			case 'blogdescription':
				return 'Blog description';

			case 'user_pass':
				return 'Password';

			case 'user_nicename':
				return 'Nice name';

			case 'show_admin_bar_front':
				return 'Show toolbar';

			case 'user registered':
				return 'Registered (UTC)';

			case 'post_date':
				return 'Created';

			case 'post_date_gmt':
				return 'Created (UTC)';

			case 'post_modified':
				return 'Last modified';

			case 'post_modified_gmt':
				return 'Last modified (UTC)';
		}

		// Convert snake-case or kebab-case keys into words.
		$words = array_filter( preg_split( '/[-_ ]+/', $key ) );

		// Convert camel-case keys into words.
		$words2 = array();
		foreach ( $words as &$word ) {
			// If it's all lowercase or all uppercase, leave unchanged.
			if ( $word === strtolower( $word ) || $word === strtoupper( $word ) ) {
				array_push( $words2, $word );
			} else {
				// If it's mixed-case, assume camel-case and split it.
				$words2 = array_merge( $words2, self::camel_case_to_words( $word ) );
			}
		}
		$words = $words2;

		// Process the words.
		foreach ( $words as $i => $word ) {
			// Process height and width abbreviations.
			if ( $word === 'h' ) {
				$words[ $i ] = 'height';
			} elseif ( $word === 'w' ) {
				$words[ $i ] = 'width';
			}

			// Make acronyms upper-case.
			if ( in_array( $word, array( 'gmt', 'guid', 'id', 'ip', 'rss', 'ssl', 'ui', 'uri', 'url', 'utc', 'wp' ), true ) ) {
				$words[ $i ] = strtoupper( $word );
			} elseif ( $ucwords ) {
				// Upper-case the first letter of the word if requested.
				$words[ $i ] = ucfirst( $word );
			}
		}

		// Convert to readable string.
		return ucfirst( implode( ' ', $words ) );
	}

	/**
	 * Convert a version string (e.g. '1.23.45') to a float.
	 *
	 * @param string $version The version string.
	 * @return float The version as a float.
	 */
	public static function version_to_float( string $version ): float {
		$parts = explode( '.', $version );
		$major = empty( $parts[0] ) ? 0 : (int) $parts[0];
		$minor = empty( $parts[1] ) ? 0 : (int) $parts[1];
		$patch = empty( $parts[2] ) ? 0 : (int) $parts[2];
		return $major + $minor / 100 + $patch / 10000;
	}
}
