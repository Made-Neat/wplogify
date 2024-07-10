<?php
/**
 * Contains the Json class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;

/**
 * Utility class for encoding and decoding JSON.
 */
class Json {

	/**
	 * Encode data to JSON.
	 *
	 * @param mixed $data The data to encode.
	 * @return string The JSON string.
	 */
	public static function encode( mixed $data ): string {
		// Check for special objects with custom encoding.
		if ( $data instanceof DateTime ) {
			// Special handling of DateTime objects.
			$data = DateTimes::encode( $data );
		} elseif ( $data instanceof Object_Reference ) {
			// Special handling of Object_Reference objects.
			$data = Object_Reference::encode( $data );
		}

		return wp_json_encode( $data );
	}

	/**
	 * Decode JSON to data.
	 *
	 * @param string $json The JSON string.
	 * @return mixed The decoded data.
	 */
	public static function decode( string $json ): mixed {
		$value = json_decode( $json );

		// If the value is an object, check if it requires special handling.
		if ( is_object( $value ) ) {
			// Special handling of DateTime objects.
			if ( DateTimes::is_encoded_object( $value, $datetime ) ) {
				return $datetime;
			}

			// Special handling of Object_Reference objects.
			if ( Object_Reference::is_encoded_object( $value, $object_ref ) ) {
				return $object_ref;
			}
		}

		return $value;
	}
}
