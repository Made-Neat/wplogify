<?php
/**
 * Flatten an array by converting single-value numeric arrays into scalars where possible and
 * discarding other non-scalar values.
 */
public static function flatten_array( array $input_array ): array {
	$flat_array = array();
	foreach ( $input_array as $key => $value ) {
		if ( is_scalar( $value ) ) {
			$flat_array[ $key ] = $value;
		} elseif ( is_array( $value ) && count( $value ) === 1 && array_key_exists( 0, $value ) && is_scalar( $value[0] ) ) {
			$flat_array[ $key ] = $value[0];
		}
	}
	return $flat_array;
}

/**
 * Retrieves all the information about a user.
 *
 * This function retrieves all the information about a user, including the user object, user
 * data, and user meta data. It then flattens the data into a single array.
 *
 * @param int|WP_User $user The ID of the user or the user object.
 * @return array The user information.
 */
public static function get_user_info( int|WP_user $user ): array {
	Load the user if necessary .
	if ( is_int( $user ) ) {
		$user = get_userdata( $user );
	}

	Get the data and merge into one flat array .
	$flat_user = self::flatten_array( (array) $user );
	$flat_data = self::flatten_array( (array) $user->data );
	$flat_meta = self::flatten_array( get_user_meta( $user->ID ) );
	return array_merge( $flat_user, $flat_data, $flat_meta );
}
