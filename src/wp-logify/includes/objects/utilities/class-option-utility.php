<?php
/**
 * Contains the Option_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;

/**
 * Class WP_Logify\Option_Utility
 *
 * Provides tracking of events related to options.
 */
class Option_Utility extends Object_Utility {

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

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = Types::make_key_readable( $option );
		}

		// The option no longer exists. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
	}
}
