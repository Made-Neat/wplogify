<?php
/**
 * Contains the Serialization class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use InvalidArgumentException;

/**
 * This class provides serialization and unserialization functions.
 *
 * It wraps the core PHP functions serialize() and unserialize() to provide support for nullable
 * strings, and better handling of unserialization errors.
 */
class Serialization {

	/**
	 * Serialize a value, or return null if the value is null.
	 *
	 * @param mixed $value The value to serialize.
	 * @return ?string The serialized value or null if the value is null.
	 */
	public static function serialize( mixed $value ): ?string {
		return $value === null ? null : serialize( $value );
	}

	/**
	 * Attempts to unserialize a nullable string. If the value is null, null will be returned.
	 * If the string represents PHP-serialized data, it will be unserialized and the unserialized value
	 * will be returned. If it doesn't, the original string will be returned.
	 *
	 * @param ?string $value The input string which may contain serialized data.
	 * @param bool    $suppress_exception Whether to suppress exceptions when unserialization fails.
	 * @return mixed The unserialized value if the string represents serialized data; otherwise, the
	 *               original string.
	 * @throws InvalidArgumentException If the value is not null and unserialization fails.
	 */
	public static function unserialize( ?string $value, bool $suppress_exception = true ): mixed {
		// If the value is null, return null.
		if ( $value === null ) {
			return null;
		}

		// Attempt to unserialize the value.
		$unserialized_value = @unserialize( $value );

		// Check if unserialization was successful.
		if ( $unserialized_value !== false || $value === 'b:0;' ) {
			return $unserialized_value;
		}

		// If we're suppressing exceptions, return the original value.
		if ( $suppress_exception ) {
			return $value;
		}

		// If unserialization failed, throw an exception.
		throw new InvalidArgumentException( 'Failed to unserialize value: ' . $value );
	}
}
