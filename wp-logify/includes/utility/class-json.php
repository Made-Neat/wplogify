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
		// Special handling of DateTime objects.
		if ( $data instanceof DateTime ) {
			return DateTimes::to_json( $data );
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
		// Special handling of DateTime objects.
		if ( DateTimes::json_is_datetime( $json, $datetime ) ) {
			return $datetime;
		}

		return json_decode( $json );
	}
}
