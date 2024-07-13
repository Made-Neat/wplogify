<?php
/**
 * Contains test code.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;

function test_datetime() {

	// $fields = array(
	// 'date'          => '2024-07-11 90:50:17.501574',
	// 'timezone_type' => 3,
	// 'timezone'      => 'Australia/Brisbane',
	// );
	// try {
	// $dt = DateTime::__set_state( $fields );
	// debug( $dt->format( DateTime::ATOM ), $dt );
	// } catch ( Throwable $e ) {
	// debug( get_class( $e ), $e->getMessage() );
	// }

	$dt = DateTimes::current_datetime();
	debug( $dt );
	debug( 'dt atom', $dt->format( DateTime::ATOM ) );

	$encoded_dt = DateTimes::encode( $dt );
	debug( $encoded_dt );

	$json_encoded_dt = Json::encode( $dt );
	debug( $json_encoded_dt );

	$decoded_dt = Json::decode( $json_encoded_dt );
	debug( '$decoded_dt', $decoded_dt );

	debug( 'decoded dt atom', $decoded_dt->format( DateTime::ATOM ) );
}

function test_obj_ref() {
	$objref = new Object_Reference( 'user', 1, 'admin' );
	debug( $objref );

	$encoded_objref = Object_Reference::encode( $objref );
	debug( $encoded_objref );

	$json_encoded_objref = Json::encode( $objref );
	debug( $json_encoded_objref );

	$decoded_objref = Json::decode( $json_encoded_objref );
	debug( '$decoded_objref', $decoded_objref );
}

test_datetime();
// test_objref();
