<?php
/**
 * Contains the Plugin_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;

/**
 * Class WP_Logify\Plugin_Utility
 *
 * Provides methods for working with plugins.
 */
class Plugin_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if a plugin exists.
	 *
	 * @param int|string $plugin_slug The plugin's slug.
	 * @return bool True if the plugin exists, false otherwise.
	 */
	public static function exists( int|string $plugin_slug ): bool {
		// Try to load the plugin.
		$plugin = self::load( $plugin_slug );

		// Return true if the plugin was found.
		return $plugin !== null;
	}

	/**
	 * Get a plugin by slug.
	 *
	 * If the plugin isn't found, null will be returned. An exception will not be thrown.
	 *
	 * @param int|string $plugin_slug The plugin's slug.
	 * @return ?array The plugin data or null if not found.
	 */
	public static function load( int|string $plugin_slug ): ?array {
		// Get all installed plugins.
		$all_plugins = get_plugins();

		// Loop through each plugin and check its text domain.
		foreach ( $all_plugins as $plugin_file => $plugin ) {

			// Check for a match.
			if ( self::get_slug( $plugin_file ) === $plugin_slug ) {

				// Return the plugin with additional details.
				return self::add_details( $plugin, $plugin_file );
			}
		}

		// Return null if no plugin is found with the given text domain.
		return null;
	}

	/**
	 * Get the plugin name. This is displayed in the tag.
	 *
	 * @param int|string $plugin_slug The plugin's slug.
	 * @return string The name, or null if the object could not be found.
	 */
	public static function get_name( int|string $plugin_slug ): ?string {
		// Load the plugin.
		$plugin = self::load( $plugin_slug );

		// Return the plugin name.
		return $plugin['Name'] ?? null;
	}

	/**
	 * Get the core properties of a plugin.
	 *
	 * @param int|string $plugin_slug The plugin's slug.
	 * @return Property[] The core properties of the plugin.
	 * @throws Exception If the plugin no longer exists.
	 */
	public static function get_core_properties( int|string $plugin_slug ): array {
		// Get the plugin data.
		$plugin = self::load( $plugin_slug );

		// Handle the case where the plugin no longer exists.
		if ( ! $plugin ) {
			throw new Exception( "Plugin '$plugin_slug' not found." );
		}

		// Collect the core properties.
		$props = array();

		// Name or link. Use the link if there is one, otherwise use the name.
		if ( $plugin['PluginURI'] ) {
			$link = "<a href='{$plugin['PluginURI']}' target='_blank'>{$plugin['Name']}</a>";
			Property::update_array( $props, 'link', null, $link );
		} else {
			Property::update_array( $props, 'name', null, $plugin['Name'] );
		}

		// Slug.
		Property::update_array( $props, 'slug', null, $plugin['slug'] );

		// Version.
		Property::update_array( $props, 'version', null, $plugin['Version'] );

		// Author. Use the link if there is one.
		if ( $plugin['AuthorURI'] ) {
			$author = "<a href='{$plugin['AuthorURI']}' target='_blank'>{$plugin['AuthorName']}</a>";
		} else {
			$author = $plugin['AuthorName'];
		}
		Property::update_array( $props, 'author', null, $author );

		return $props;
	}

	/**
	 * Get the plugin tag.
	 *
	 * If the plugin hasn't been deleted, get a link to its web page; otherwise, get a span with
	 * the name as the link text.
	 *
	 * @param int|string $plugin_slug The plugin's slug.
	 * @param ?string    $old_name    The name of the plugin at the time of the event.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $plugin_slug, ?string $old_name = null ): string {
		// Load the plugin.
		$plugin = self::load( $plugin_slug );

		// Provide a link to the plugin site.
		if ( $plugin ) {
			return "<a href='{$plugin['PluginURI']}' class='wp-logify-object' target='_blank'>{$plugin['Name']}</a>";
		}

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = Strings::make_key_readable( $plugin_slug, true );
		}

		// The plugin has been deleted. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Get the data for a plugin.
	 *
	 * @param string $plugin_file The relative path to the main plugin file.
	 * @return ?array The plugin data or null if not found.
	 */
	public static function load_by_file( string $plugin_file ): ?array {
		// Check if the plugin file exists.
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
		if ( ! file_exists( $plugin_path ) ) {
			return null;
		}

		// Load the plugin data.
		$plugin = get_plugin_data( $plugin_path );

		// Check it loaded ok.
		if ( empty( $plugin['Name'] ) ) {
			return null;
		}

		// Return the plugin with additional details.
		return self::add_details( $plugin, $plugin_file );
	}

	/**
	 * Get a plugin's data by its name.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @return ?array The plugin data or null if the plugin isn't found.
	 */
	public static function load_by_name( string $plugin_name ): ?array {
		// Get all installed plugins.
		$all_plugins = get_plugins();

		// Loop through each plugin and check its text domain.
		foreach ( $all_plugins as $plugin_file => $plugin ) {

			// Check for a match.
			if ( $plugin['Name'] === $plugin_name ) {

				// Return the plugin with additional details.
				return self::add_details( $plugin, $plugin_file );
			}
		}

		// Return null if no plugin is found with the given text domain.
		return null;
	}

	/**
	 * Get the slug of a plugin.
	 *
	 * @param string $plugin_file The plugin file.
	 * @return string The plugin slug.
	 */
	public static function get_slug( string $plugin_file ): string {
		$slug = dirname( $plugin_file );
		if ( $slug === '.' ) {
			$slug = basename( $plugin_file, '.php' );
		}
		return $slug;
	}

	/**
	 * Add details to a plugin array.
	 *
	 * @param array  $plugin      The plugin array.
	 * @param string $plugin_file The plugin file.
	 * @return array The plugin array with added details.
	 */
	public static function add_details( array &$plugin, string $plugin_file ) {
		// Add the object_type to the array.
		$plugin['object_type'] = 'plugin';

		// Add the file to the array.
		$plugin['file'] = $plugin_file;

		// Add the slug to the array.
		$plugin['slug'] = self::get_slug( $plugin_file );

		return $plugin;
	}
}
