<?php

// From User_Tracker::init()


		// Data export.
		// add_action( 'export_wp', array( __CLASS__, 'on_export_wp' ), 10, 1 );
		// add_action( 'wp_privacy_personal_data_export_file', array( __CLASS__, 'on_export_personal_data' ), 10, 1 );



// From User_Tracker

	/**
	 * Generate the export file from the collected, grouped personal data.
	 *
	 * @param int $request_id The export request ID.
	 */
public static function on_export_personal_data( int $request_id ) {
	$event = Event::create( 'Personal Data Export Requested', 'user' );
	$event->set_meta( 'request_id', $request_id );
	$event->save();
}


	/**
	 * Fires at the beginning of an export, before any headers are sent.
	 *
	 * @param array $args An array of export arguments.
	 */
public static function on_export_wp( $args ) {
	$event = Event::create( 'Data Export', 'user' );
	$event->set_meta( 'export_arguments', $args );
	$event->save();
}
