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
class Option_Tracker extends Object_Tracker {

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Track option update.
		add_action( 'update_option', array( __CLASS__, 'on_update_option' ), 10, 3 );
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
		if ( strpos( $option, '_transient' ) === 0 || strpos( $option, '_site_transient' ) === 0 || $option === 'wp_user_roles' ) {
			return;
		}

		// Process the values for comparison.
		$old_val = Types::process_database_value( $option, $old_value );
		$new_val = Types::process_database_value( $option, $value );
		if ( $old_val === $new_val ) {
			return;
		}

		// Get the properties.
		$properties = array();
		Property::update_array( $properties, 'value', $wpdb->options, $old_val, $new_val );

		// Log the event.
		Logger::log_event( 'Option Updated', $option, null, $properties );
	}
}
