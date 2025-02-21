<?php
/**
 * Contains the Option_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class Logify_WP\Option_Tracker
 *
 * Provides tracking of events related to settings.
 *
 * The reason this is called Option_Tracker is because originally we were tracking changes to all
 * options, not just settings. Settings are options that can be changed by a user via a form, which
 * are the only options we want to track changes to.
 */
class Option_Tracker {

	/**
	 * Settings update event.
	 *
	 * @var Event
	 */
	private static $event;

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Track settings updates.
		add_action( 'update_option', [__NAMESPACE__.'\Async_Tracker','async_update_option'], 10, 3 );
		add_action( 'middle_update_option', array( __CLASS__, 'on_update_option' ), 10, 3 );
		
		add_action( 'shutdown', [__NAMESPACE__.'\Async_Tracker','async_shutdown'], 10, 0 );
		add_action( 'middle_shutdown', array( __CLASS__, 'on_shutdown' ), 10, 0 );
	}

	/**
	 * Fires immediately before an option value is updated.
	 *
	 * @param string $option_name Name of the option to update.
	 * @param mixed  $old_value   The old option value.
	 * @param mixed  $new_value   The new option value.
	 */
	public static function on_update_option( string $option_name, mixed $old_value, mixed $new_value ) {
		global $wpdb, $wp_registered_settings;

		// Ignore options that aren't settings.
		if ( ! Option_Utility::is_setting( $option_name ) ) {
			return;
		}

		// If this setting is of type boolean, convert the values to boolean.
		if ( isset( $wp_registered_settings[ $option_name ] ) && $wp_registered_settings[ $option_name ]['type'] === 'boolean' ) {
			$val     = rest_sanitize_boolean( $old_value );
			$new_val = rest_sanitize_boolean( $new_value );
		} else {
			// Process the values for comparison.
			$val     = Types::process_database_value( $option_name, $old_value );
			$new_val = Types::process_database_value( $option_name, $new_value );
		}

		// If the value has changed, add the setting change to the event properties.
		if ( ! Types::are_equal( $val, $new_val ) ) {

			// Create the event object to encapsulate setting updates, if it doesn't already exist.
			if ( ! isset( self::$event ) ) {
				$object_ref  = new Object_Reference( 'option', null, null );
				self::$event = Event::create( 'Settings Updated', $object_ref );

				// If the event could not be created, we aren't tracking this user.
				if ( ! self::$event ) {
					return;
				}
			}

			if ( str_ends_with( $option_name, '_category' ) ) {
				// Convert categories to links.
				$val     = new Object_Reference( 'term', $val );
				$new_val = new Object_Reference( 'term', $new_val );
			} elseif ( $option_name === 'wp_page_for_privacy_policy' ) {
				// Convert privacy page to reference.
				$val     = new Object_Reference( 'post', $val );
				$new_val = new Object_Reference( 'post', $new_val );
			}

			// Add the setting change to the properties.
			self::$event->set_prop( $option_name, $wpdb->options, $val, $new_val );
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

		// Get the setting names, but limit to 50 characters total.
		$option_names = '';
		foreach ( self::$event->properties as $option => $prop ) {
			$option_name   = Option_Utility::get_name( $option );
			$option_names2 = ( $option_names ? ( $option_names . ', ' ) : '' ) . $option_name;
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
