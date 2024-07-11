<?php
/**
 * Encodable interface.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Interface for objects that can be encoded into a JSON-compatible format.
 */
interface Encodable {

	/**
	 * Convert the object to an array suitable for encoding as JSON.
	 *
	 * @return array The array representation of the object.
	 */
	public static function encode( object $obj ): array;

	/**
	 * Check if the provided array represents an object of the implementing class.
	 *
	 * @param array   $ary The array to check.
	 * @param ?object $obj The object to populate if valid.
	 * @return bool If the array contains a valid encoded object.
	 */
	public static function can_decode( array $ary, ?object &$obj ): bool;
}
