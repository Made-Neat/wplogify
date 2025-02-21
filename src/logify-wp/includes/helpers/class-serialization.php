<?php
/**
 * Contains the Serialization class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use Throwable;

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
	 * Attempts to unserialize a nullable string.
	 *
	 * If the provided value is null, the unserialized value will be null, and the return value will
	 * be true.
	 *
	 * If the provided value is a string with value PHP-serialized data, it will be unserialized,
	 * the unserialized value will be stored in the reference parameter, and the return value will
	 * be true.
	 *
	 * If it doesn't, the unserialized value will be unaltered, and the return value will be false.
	 *
	 * @param ?string $serialized_value The input value which may contain serialized data.
	 * @param mixed   $unserialized_value The unserialized value if the string represents serialized data.
	 * @return bool True if the provided value was null or successfully unserialized; otherwise false.
	 */
	public static function try_unserialize( ?string $serialized_value, mixed &$unserialized_value ): bool {
		if ( $serialized_value === null ) {
			$unserialized_value = null;
			return true;
		}
	
		if ( ! self::is_valid_serialized( $serialized_value ) ) {
			return false; // Invalid serialized string.
		}
	
		try {
			$unserialized_value = @unserialize( $serialized_value );
			return $unserialized_value !== false || $serialized_value === 'b:0;';
		} catch ( Throwable $e ) {
			return false;
		}
	}

	private static function is_valid_serialized( string $data ): bool {
		// Basic serialized string validation.
		return preg_match('/^(?:[aOs]:|b:0;|N;|i:\d+;|d:\d+\.\d+;)/', $data) === 1;
	}
}
