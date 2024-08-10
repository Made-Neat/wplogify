<?php
/**
 * Contains the Option_Manager class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

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
	 * @param int|string $option_name The name of the option.
	 * @return bool True if the option exists, false otherwise.
	 */
	public static function exists( int|string $option_name ): bool {
		global $wpdb;
		$sql   = $wpdb->prepare( 'SELECT COUNT(option_name) FROM %i WHERE option_name = %d', $wpdb->options, $option_name );
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}

	/**
	 * Get a option value.
	 *
	 * @param int|string $option_name The name of the option.
	 * @return mixed The option value or null if not found.
	 */
	public static function load( int|string $option_name ): mixed {
		// Get the option value, defaulting to null.
		return get_option( $option_name, null );
	}

	/**
	 * Get the name of a option.
	 *
	 * @param int|string $option_name The name of the option.
	 * @return ?string The name of the option or null if the option doesn't exist.
	 */
	public static function get_name( int|string $option_name ): ?string {
		return $option_name;
	}

	/**
	 * Get the core properties of a option.
	 *
	 * @param int|string $option_name The ID of the option.
	 * @return array The core properties of the option.
	 */
	public static function get_core_properties( int|string $option_name ): array {
		global $wpdb;

		// Load the option.
		$option_value = self::load( $option_name );

		// Build the array of properties.
		$properties = array();

		// Name.
		Property::update_array( $properties, 'name', $wpdb->options, $option_name );

		// Value.
		$option_value = Types::process_database_value( $option_name, $option_value );
		Property::update_array( $properties, 'value', $wpdb->options, $option_value );

		return $properties;
	}

	/**
	 * There's no way currently to get a link to an option, so we just return a span either way.
	 *
	 * If the option isn't present, then the "(deleted)" text will be appended to the name.
	 *
	 * @param int|string $option_name The ID of the option.
	 * @param ?string    $old_name The fallback name of the option if it's been deleted (not used).
	 * @return string The span HTML.
	 */
	public static function get_tag( int|string $option_name, ?string $old_name ): string {
		// Load the option.
		$option_value = self::load( $option_name );

		// If the option exists, get a span.
		if ( $option_value !== null ) {
			return "<span class='wp-logify-object'>$option_name</span>";
		}

		// The option no longer exists. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$option_name (deleted)</span>";
	}
}
