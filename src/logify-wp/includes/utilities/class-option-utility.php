<?php
/**
 * Contains the Option_Utility class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class Logify_WP\Option_Utility
 *
 * Provides methods for working with options.
 */
class Option_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if an option exists.
	 *
	 * @param int|string $option_name The name of the option.
	 * @return bool True if the option exists, false otherwise.
	 */
	public static function exists( int|string $option_name ): bool {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(option_name) FROM %i WHERE option_name = %d', $wpdb->options, $option_name )
		);
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
	 * Get a readable name for an option.
	 *
	 * @param int|string $option_name The name of the option.
	 * @return ?string A more readable name of the option.
	 */
	public static function get_name( int|string $option_name ): ?string {
		return Strings::key_to_label( $option_name );
	}

	/**
	 * Get the core properties of a option.
	 *
	 * @param int|string $option_name The option name.
	 * @return ?Property[] The core properties of the option, or null if not found.
	 */
	public static function get_core_properties( int|string $option_name ): ?array {
		global $wpdb;

		// Load the option.
		$option_value = self::load( $option_name );

		// Handle the case where the option no longer exists.
		if ( $option_value === null ) {
			return null;
		}

		// Build the array of properties.
		$props = array();

		// Name.
		Property::update_array( $props, 'name', $wpdb->options, $option_name );

		// Value.
		$option_value = Types::process_database_value( $option_name, $option_value );
		Property::update_array( $props, 'value', $wpdb->options, $option_value );

		return $props;
	}

	/**
	 * There's no way to get a link to an option, so we just return a span either way.
	 *
	 * If the option isn't present, then the "(deleted)" text will be appended to the name.
	 *
	 * @param int|string $option_name The name of the option.
	 * @param ?string    $old_name    The fallback display name of the option if it's been deleted.
	 * @return string The span HTML.
	 */
	public static function get_tag( int|string $option_name, ?string $old_name = null ): string {
		// Load the option.
		$option_value = self::load( $option_name );

		// If the option exists, get a span.
		if ( $option_value !== null ) {
			$name = self::get_name( $option_name );
			return "<span class='logify-wp-object'>$name</span>";
		}

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = Strings::key_to_label( $option_name );
		}

		// The option no longer exists. Construct the 'deleted' span element.
		return "<span class='logify-wp-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Check if an option is a registered setting.
	 *
	 * @param string $option_name The name of the option.
	 * @return bool True if the option is a registered setting, false otherwise.
	 */
	public static function is_setting( string $option_name ): bool {
		global $allowed_options, $wp_registered_settings;

		// Check if the option is a WordPress allowed option.
		if ( is_array( $allowed_options ) ) {
			foreach ( $allowed_options as $options_group => $options ) {
				if ( in_array( $option_name, $options, true ) ) {
					return true;
				}
			}
		}

		// Check if the option is registered as a setting by a plugin.
		if ( isset( $wp_registered_settings[ $option_name ] ) ) {
			return true;
		}

		// Check if the option is one of the additional settings on the Permalinks and Privacy
		// settings pages that (for some reason) aren't included in $allowed_options.
		$additional_settings = array( 'permalink_structure', 'category_base', 'tag_base', 'wp_page_for_privacy_policy' );
		if ( in_array( $option_name, $additional_settings, true ) ) {
			return true;
		}

		return false;
	}
}
