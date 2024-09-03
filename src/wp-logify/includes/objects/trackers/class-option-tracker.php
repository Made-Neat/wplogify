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
	 * Options update event.
	 *
	 * @var Event
	 */
	private static $event;

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Track option updates.
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
		if ( Types::starts_with( $option, '_transient' ) || Types::starts_with( $option, '_site_transient' ) || $option === 'wp_user_roles' || $option === 'cron' ) {
			return;
		}

		// Ignore updates to widget-related options, which are handled by Widget_Tracker.
		if ( Types::starts_with( $option, 'widget_' ) || $option === 'sidebars_widgets' ) {
			return;
		}

		// Process the values for comparison.
		$old_val = Types::process_database_value( $option, $old_value );
		$new_val = Types::process_database_value( $option, $value );

		// If the value has changed, add the option change to the event properties.
		if ( $old_val !== $new_val ) {

			// Create the event object to encapsulate option updates, if it doesn't already exist.
			if ( ! isset( self::$event ) ) {
				$object_ref  = new Object_Reference( 'option', null, null );
				self::$event = Event::create( 'Options Updated', $object_ref );
			}

			// Add the option change to the properties.
			self::$event->set_prop( $option, $wpdb->options, $old_val, $new_val );
		}
	}

	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown() {
		// If there is no event to log, return.
		if ( ! isset( self::$event ) ) {
			return;
		}

		// Change the event type to singular if only one option was updated.
		if ( count( self::$event->properties ) === 1 ) {
			self::$event->event_type = 'Option Updated';
		}

		// Get the option names, but limit to 50 characters total.
		$option_names = '';
		foreach ( self::$event->properties as $option => $prop ) {
			$option_names2 = ( $option_names ? ( $option_names . ', ' ) : '' ) . $option;
			if ( strlen( $option_names2 ) <= Logger::MAX_OBJECT_NAME_LENGTH - 6 ) {
				$option_names = $option_names2;
			} else {
				$option_names .= ', etc.';
				break;
			}
		}
		self::$event->object_name = $option_names;

		// Log the event.
		self::$event->save();
	}
}
