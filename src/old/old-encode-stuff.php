<?php


// From DateTimes:

	/**
	 * Convert the DateTime to an array suitable for encoding as JSON.
	 *
	 * @param object $obj The DateTime to convert.
	 * @return array The array representation of the DateTime.
	 * @throws InvalidArgumentException If the object is not an instance of DateTime.
	 */
public static function encode( object $obj ): array {
	// Check the type.
	if ( ! $obj instanceof DateTime ) {
		throw new InvalidArgumentException( 'The object must be an instance of DateTime.' );
	}

	return array( 'DateTime' => (array) $obj );
}

	/**
	 * Check if the provided array is an encoded DateTime.
	 *
	 * @param array   $ary The array to check.
	 * @param ?object $obj The DateTime object to populate if valid.
	 * @return bool    If the JSON contains a valid date-time string.
	 */
public static function can_decode( array $ary, ?object &$obj ): bool {
	// Check it looks right.
	if ( count( $ary ) !== 1 || empty( $ary['DateTime'] ) || ! is_array( $ary['DateTime'] ) ) {
		return false;
	}

	// Check the array of properties has the right number and type of values.
	$fields = $ary['DateTime'];
	if ( count( $fields ) !== 3
		|| ! key_exists( 'date', $fields ) || ! is_string( $fields['date'] )
		|| ! key_exists( 'timezone_type', $fields ) || ! is_int( $fields['timezone_type'] )
		|| ! key_exists( 'timezone', $fields ) || ! is_string( $fields['timezone'] )
	) {
		return false;
	}

	// Try to convert the inner object to a DateTime.
	try {
		// The DateTime constructor will throw if the details don't represent a valid DateTime.
		$obj = DateTime::__set_state( $ary['DateTime'] );
	} catch ( Throwable $ex ) {
		Debug::info( $ex->getMessage(), $ary['DateTime'], );
		return false;
	}

	return true;
}

// From Object_Reference:


	// =============================================================================================
	// Encodable interface methods.

	/**
	 * Convert the object reference to a array suitable for encoding as JSON.
	 *
	 * @param object $obj The Object_Reference to convert.
	 * @return array The array representation of the Object_Reference.
	 * @throws InvalidArgumentException If the object is not an instance of Object_Reference.
	 */
public static function encode( object $obj ): array {
	// Check the type.
	if ( ! $obj instanceof Object_Reference ) {
		throw new InvalidArgumentException( 'The object must be an instance of Object_Reference.' );
	}

	return array(
		'Object_Reference' => array(
			'type' => $obj->type,
			'id'   => $obj->id,
			'name' => $obj->name,
		),
	);
}

	/**
	 * Check if the value expresses a valid Object_Reference.
	 *
	 * @param array   $ary The value to check.
	 * @param ?object $obj The Object_Reference object to populate if valid.
	 * @return bool If the JSON contains a valid date-time string.
	 */
public static function can_decode( array $ary, ?object &$obj ): bool {
	// Check it looks right.
	if ( count( $ary ) !== 1 || empty( $ary['Object_Reference'] ) || ! is_array( $ary['Object_Reference'] ) ) {
		return false;
	}

	// Check the array of properties has the right number and type of values.
	$fields = $ary['Object_Reference'];
	if ( count( $fields ) !== 3
		|| ! key_exists( 'type', $fields ) || ! is_string( $fields['type'] )
		|| ! key_exists( 'id', $fields ) || ( ! is_int( $fields['id'] ) && ! is_string( $fields['id'] ) )
		|| ! key_exists( 'name', $fields ) || ! is_string( $fields['name'] )
	) {
		return false;
	}

	// Create the new object.
	$obj = new self( $fields['type'], $fields['id'], $fields['name'] );

	return true;
}
