<?php
/**
 * Contains the Option_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Option_Tracker
 *
 * Provides tracking of events related to options.
 */
class Option_Tracker {

	/**
	 * Array to remember properties between different events.
	 *
	 * @var array
	 */
	protected static $properties = array();

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Track option update.
		add_action( 'update_option', array( __CLASS__, 'on_update_option' ), 10, 3 );
		add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 10, 0 );
	}

	/**
	 * Fires immediately before an option value is updated.
	 *
	 * @param string $option    Name of the option to update.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	public static function on_update_option( string $option, mixed $old_value, mixed $value ) {
		global $wpdb;

		// Ignore certain options that clutter the log.
		if ( strpos( $option, '_transient' ) === 0 || strpos( $option, '_site_transient' ) === 0 || $option === 'wp_user_roles' || $option === 'cron' ) {
			return;
		}

		// Check if this option change was triggered by an HTTP request, i.e. more likely to be
		// caused a user action than programmatic.
		// $contents = file_get_contents( 'php://input' );
		// if ( empty( $contents ) ) {
		// return;
		// }

		// debug( '$option', $option );
		// debug( '$_POST', $_POST );
		// debug( "file_get_contents( 'php://input' )", $contents );

		// Process the values for comparison.
		$old_val = Types::process_database_value( $option, $old_value );
		$new_val = Types::process_database_value( $option, $value );

		// If the value has changed, add the option change to the properties.
		if ( $old_val !== $new_val ) {
			Property::update_array( self::$properties, $option, $wpdb->options, $old_val, $new_val );
		}
	}

	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown() {
		// Log the option changes, if there are any.
		if ( self::$properties ) {
			$object_ref = new Object_Reference( 'option', null, null );
			$event_type = 'Option' . ( count( self::$properties ) > 1 ? 's' : '' ) . ' Updated';
			Logger::log_event( $event_type, $object_ref, null, self::$properties );
		}
	}
}
