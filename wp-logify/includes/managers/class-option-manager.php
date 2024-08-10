<?php
/**
 * Contains the Option_Manager class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;

/**
 * Class WP_Logify\Option_Manager
 *
 * Provides tracking of events related to options.
 */
class Option_Manager extends Object_Manager {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Track option update.
		add_action( 'update_option', array( __CLASS__, 'on_update_option' ), 10, 3 );
	}

	/**
	 * Check if an option exists.
	 *
	 * @param int|string $option The name of the option.
	 * @return bool True if the option exists, false otherwise.
	 */
	public static function exists( int|string $option ): bool {
		global $wpdb;
		$sql   = $wpdb->prepare( 'SELECT COUNT(option_name) FROM %i WHERE option_name = %d', $wpdb->options, $option );
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}

	/**
	 * Get a option value.
	 *
	 * @param int|string $option The name of the option.
	 * @return mixed The option value or null if not found.
	 */
	public static function load( int|string $option ): mixed {
		// Get the option value, defaulting to null.
		return get_option( $option, null );
	}

	/**
	 * Get a readable name for an option.
	 *
	 * @param int|string $option The name of the option.
	 * @return ?string A more readable name of the option.
	 */
	public static function get_name( int|string $option ): ?string {
		return Types::make_key_readable( $option );
	}

	/**
	 * Get the core properties of a option.
	 *
	 * @param int|string $option The option name.
	 * @return array The core properties of the option.
	 * @throws Exception If the option no longer exists.
	 */
	public static function get_core_properties( int|string $option ): array {
		global $wpdb;

		// Load the option.
		$option_value = self::load( $option );

		// Handle the case where the option no longer exists.
		if ( $option_value === null ) {
			throw new Exception( "Option '$option' not found." );
		}

		// Build the array of properties.
		$properties = array();

		// Name.
		Property::update_array( $properties, 'name', $wpdb->options, $option );

		// Value.
		$option_value = Types::process_database_value( $option, $option_value );
		Property::update_array( $properties, 'value', $wpdb->options, $option_value );

		return $properties;
	}

	/**
	 * There's no way currently to get a link to an option, so we just return a span either way.
	 *
	 * If the option isn't present, then the "(deleted)" text will be appended to the name.
	 *
	 * @param int|string $option   The name of the option.
	 * @param ?string    $old_name The fallback name of the option if it's been deleted (not used).
	 * @return string The span HTML.
	 */
	public static function get_tag( int|string $option, ?string $old_name ): string {
		// Load the option.
		$option_value = self::load( $option );

		// If the option exists, get a span.
		if ( $option_value !== null ) {
			$name = self::get_name( $option );
			return "<span class='wp-logify-object'>$name</span>";
		}

		// The option no longer exists. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$option (deleted)</span>";
	}

	// =============================================================================================
	// Hooks.

	/**
	 * Fires immediately before an option value is updated.
	 *
	 * @param string $option    Name of the option to update.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	public static function on_update_option( string $option, mixed $old_value, mixed $value ) {
		global $wpdb;

		// Ignore transient options.
		if ( strpos( $option, '_transient' ) === 0 || strpos( $option, '_site_transient' ) === 0 ) {
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

		// Get an object reference.
		$object_ref = new Object_Reference( 'option', $option, self::get_name( $option ) );

		// Log the event.
		Logger::log_event( 'Option Updated', $object_ref, null, $properties );
	}
}
