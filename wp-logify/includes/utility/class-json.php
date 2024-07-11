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
		if ( is_object( $data ) ) {
			if ( $data instanceof DateTime ) {
				// Special handling of DateTime objects.
				$data = DateTimes::encode( $data );
			} elseif ( $data instanceof Object_Reference ) {
				// Special handling of Object_Reference objects.
				$data = Object_Reference::encode( $data );
			}
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
		$data = json_decode( $json, true );

		// If the value is an array, check if it represents an encodable PHP object.
		if ( is_array( $data ) ) {
			if ( DateTimes::can_decode( $data, $datetime ) ) {
				// Special handling of DateTime objects.
				return $datetime;
			} elseif ( Object_Reference::can_decode( $data, $object_ref ) ) {
				// Special handling of Object_Reference objects.
				return $object_ref;
			}
		}

		return $data;
	}
}
