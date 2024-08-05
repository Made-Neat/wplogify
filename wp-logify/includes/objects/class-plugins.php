<?php
/**
 * Contains the Posts class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Plugin_Upgrader;
use WP_Upgrader;

/**
 * Class WP_Logify\Posts
 *
 * Provides tracking of events related to posts.
 */
class Plugins {

	/**
	 * Array to remember properties between different events.
	 *
	 * @var array
	 */
	private static $properties = array();

	/**
	 * Array to remember metadata between different events.
	 *
	 * @var array
	 */
	private static $eventmetas = array();

	/**
	 * Link the events we want to log to methods.
	 */
	public static function init() {
		// Plugin install and update.
		add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrader_process_complete' ), 10, 2 );

		// Plugin activation and deactivation.
		add_action( 'activate_plugin', array( __CLASS__, 'on_activate_plugin' ), 10, 2 );
		add_action( 'deactivate_plugin', array( __CLASS__, 'on_deactivate_plugin' ), 10, 2 );

		// Plugin deletion and uninstall.
		add_action( 'delete_plugin', array( __CLASS__, 'on_delete_plugin' ), 10, 1 );
		add_action( 'pre_uninstall_plugin', array( __CLASS__, 'on_pre_uninstall_plugin' ), 10, 2 );

		// Enabling and disabling auto-updates.
		add_action( 'update_option', array( __CLASS__, 'on_update_option' ), 10, 3 );
	}

	// =============================================================================================
	// Event handlers.

	/**
	 * Fires when the upgrader process is complete.
	 *
	 * See also {@see 'upgrader_package_options'}.
	 *
	 * @param WP_Upgrader $upgrader   WP_Upgrader instance. In other contexts this might be a
	 *                                Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
	 * @param array       $hook_extra {
	 *     Array of bulk item update data.
	 *
	 *     @type string $action       Type of action. Default 'update'.
	 *     @type string $type         Type of update process. Accepts 'plugin', 'theme', 'translation', or 'core'.
	 *     @type bool   $bulk         Whether the update process is a bulk update. Default true.
	 *     @type array  $plugins      Array of the basename paths of the plugins' main files.
	 *     @type array  $themes       The theme slugs.
	 *     @type array  $translations {
	 *         Array of translations update data.
	 *
	 *         @type string $language The locale the translation is for.
	 *         @type string $type     Type of translation. Accepts 'plugin', 'theme', or 'core'.
	 *         @type string $slug     Text domain the translation is for. The slug of a theme/plugin or
	 *                                'default' for core translations.
	 *         @type string $version  The version of a theme, plugin, or core.
	 *     }
	 * }
	 */
	public static function on_upgrader_process_complete( WP_Upgrader $upgrader, array $hook_extra ) {
		// Check this is a plugin upgrader.
		if ( ! $upgrader instanceof Plugin_Upgrader ) {
			return;
		}

		// Check we're installing or updating a plugin.
		$installing_plugin = $hook_extra['action'] === 'install';
		$updating_plugin   = $hook_extra['action'] === 'update';
		if ( ! $installing_plugin && ! $updating_plugin ) {
			return;
		}

		// If the result is null, the plugin was not installed or updated, so we won't log anything.
		if ( $upgrader->result === null ) {

			// The user may be downgrading the plugin, so let's store the current plugin verison in
			// the options.
			$plugin = self::get_plugin_by_name( $upgrader->new_plugin_data['Name'] );
			if ( $plugin ) {
				$version_key = $plugin['Name'] . ' version';
				$old_version = $plugin['Version'] ?? null;
				if ( $old_version ) {
					update_option( $version_key, $old_version );
				}
			}

			return;
		}

		// debug( $upgrader->new_plugin_data );
		// debug( $upgrader->result );
		// debug( $hook_extra );

		// Default the old version to null (i.e. the plugin is new).
		$old_version = null;

		// Get the properties.
		$props = self::get_core_properties( $upgrader->new_plugin_data );

		// Get the event type.
		if ( $installing_plugin ) {
			// Default event type verb.
			$event_type_verb = 'Installed';

			// Handle upgrade and downgrade events.
			if ( $upgrader->result['clear_destination'] ) {
				if ( $upgrader->result['clear_destination'] === 'downgrade-plugin' ) {
					$event_type_verb = 'Downgraded';
				} elseif ( $upgrader->result['clear_destination'] === 'update-plugin' ) {
					$event_type_verb = 'Upgraded';
				}
			}

			// See if the old version was stored in the options.
			$version_key = $upgrader->new_plugin_data['Name'] . ' version';
			$old_version = get_option( $version_key );

			// Remove the option, as we don't need it anymore.
			if ( $old_version ) {
				delete_option( $version_key );
			}
		} else {
			// Updating the plugin from the install page.
			$event_type_verb = 'Upgraded';
			$old_version     = $upgrader->skin->plugin_info['Version'] ?? null;
		}
		$event_type = "Plugin $event_type_verb";

		// If we have both the old and new versions, show this.
		$new_version = $upgrader->new_plugin_data['Version'] ?? null;
		if ( $old_version && $new_version && $old_version !== $new_version ) {
			Property::update_array( $props, 'version', null, $old_version, $new_version );
		}

		// Log the event.
		Logger::log_event( $event_type, 'plugin', null, $upgrader->new_plugin_data['Name'], null, $props );
	}

	/**
	 * Fires before a plugin is activated.
	 *
	 * If a plugin is silently activated (such as during an update),
	 * this hook does not fire.
	 *
	 * @param string $plugin_file  Path to the plugin file relative to the plugins directory.
	 * @param bool   $network_wide Whether to enable the plugin for all sites in the network
	 *                             or just the current site. Multisite only. Default false.
	 */
	public static function on_activate_plugin( string $plugin_file, bool $network_wide ) {
		// Load the plugin.
		$plugin_data = self::load( $plugin_file );

		// Get the properties.
		$props = self::get_core_properties( $plugin_data );

		// If this is a multisite, record if the plugin activation was network-wide.
		$metas = array();
		if ( is_multisite() ) {
			Eventmeta::update_array( $metas, 'network_wide', $network_wide );
		}

		// Log the event.
		Logger::log_event( 'Plugin Activated', 'plugin', null, $plugin_data['Name'], $metas, $props );
	}

	/**
	 * Fires before a plugin is deactivated.
	 *
	 * If a plugin is silently deactivated (such as during an update),
	 * this hook does not fire.
	 *
	 * @param string $plugin_file          Path to the plugin file relative to the plugins directory.
	 * @param bool   $network_deactivating Whether the plugin is deactivated for all sites in the network
	 *                                     or just the current site. Multisite only. Default false.
	 */
	public static function on_deactivate_plugin( string $plugin_file, bool $network_deactivating ) {
		// Load the plugin.
		$plugin_data = self::load( $plugin_file );

		// Get the properties.
		$props = self::get_core_properties( $plugin_data );

		// If this is a multisite, record if the plugin deactivation was network-wide.
		$metas = array();
		if ( is_multisite() ) {
			Eventmeta::update_array( $metas, 'network_wide', $network_deactivating );
		}

		// Log the event.
		Logger::log_event( 'Plugin Deactivated', 'plugin', null, $plugin_data['Name'], $metas, $props );
	}

	/**
	 * Fires immediately before a plugin deletion attempt.
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 */
	public static function on_delete_plugin( string $plugin_file ) {
		// Load the plugin.
		$plugin_data = self::load( $plugin_file );

		// Get the properties.
		$props = self::get_core_properties( $plugin_data );

		// Log the event.
		Logger::log_event( 'Plugin Deleted', 'plugin', null, $plugin_data['Name'], null, $props );
	}

	/**
	 * Fires in uninstall_plugin() immediately before the plugin is uninstalled.
	 *
	 * @param string $plugin_file           Path to the plugin file relative to the plugins directory.
	 * @param array  $uninstallable_plugins Uninstallable plugins.
	 */
	public static function on_pre_uninstall_plugin( string $plugin_file, array $uninstallable_plugins ) {
		// Load the plugin.
		$plugin_data = self::load( $plugin_file );

		// Get the properties.
		$props = self::get_core_properties( $plugin_data );

		// Log the event.
		Logger::log_event( 'Plugin Uninstalled', 'plugin', null, $plugin_data['Name'], null, $props );
	}

	/**
	 * Fires after a plugin has been enabled or disabled for auto-updates.
	 *
	 * @param string $option    Name of the option to update.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	public static function on_update_option( string $option, mixed $old_value, mixed $value ) {
		// Check if the changed option is the auto_update_plugins option.
		if ( $option !== 'auto_update_plugins' ) {
			return;
		}

		// Log an event for each plugin for which auto-update has been enabled.
		$enabled = array_diff( $value, $old_value );
		foreach ( $enabled as $plugin ) {
			// Load the plugin.
			$plugin_data = self::load( $plugin );

			// Get the properties.
			$props = self::get_core_properties( $plugin_data );

			// Log the event.
			Logger::log_event( 'Plugin Auto-update Enabled', 'plugin', null, $plugin_data['Name'], null, $props );
		}

		// Log an event for each plugin for which auto-update has been enabled.
		$disabled = array_diff( $old_value, $value );
		foreach ( $disabled as $plugin ) {
			// Load the plugin.
			$plugin_data = self::load( $plugin );

			// Get the properties.
			$props = self::get_core_properties( $plugin_data );

			// Log the event.
			Logger::log_event( 'Plugin Auto-update Disabled', 'plugin', null, $plugin_data['Name'], null, $props );
		}
	}

	// =============================================================================================
	// Methods for getting information about plugins.

	/**
	 * Check if a plugin exists.
	 *
	 * @param string $plugin The plugin to check.
	 * @return bool True if the plugin exists, false otherwise.
	 */
	public static function plugin_exists( string $plugin ): bool {
		// Prepend the path to the plugins directory and check if the plugin is there.
		return file_exists( WP_PLUGIN_DIR . '/' . $plugin );
	}

	/**
	 * Get the data for a plugin.
	 *
	 * @param string $plugin_file The plugin file.
	 * @return array The plugin data.
	 */
	public static function load( string $plugin_file ): array {
		// Load the plugin data.
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );

		// Add the file to the array.
		$plugin_data['File'] = $plugin_file;

		// Add the slug to the array.
		$plugin_data['Slug'] = self::get_slug( $plugin_file );

		return $plugin_data;
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
	 * Get the core properties of a plugin.
	 *
	 * @param array $plugin_data The plugin data to extract the core properties from.
	 * @return array The core properties of the plugin.
	 */
	public static function get_core_properties( array $plugin_data ): array {
		// Collect the core properties.
		$properties = array();

		// Name. Use the link if there is one.
		if ( $plugin_data['PluginURI'] ) {
			$name = "<a href='{$plugin_data['PluginURI']}' target='_blank'>{$plugin_data['Name']}</a>";
		} else {
			$name = $plugin_data['Name'];
		}
		Property::update_array( $properties, 'name', null, $name );

		// Slug.
		if ( isset( $plugin_data['Slug'] ) ) {
			$slug = $plugin_data['Slug'];
		} else {
			$plugin_data2 = self::get_plugin_by_name( $plugin_data['Name'] );
			$slug         = $plugin_data2['Slug'];
		}
		Property::update_array( $properties, 'slug', null, $slug );

		// Version.
		Property::update_array( $properties, 'version', null, $plugin_data['Version'] );

		// Author. Use the link if there is one.
		if ( $plugin_data['AuthorURI'] ) {
			$author = "<a href='{$plugin_data['AuthorURI']}' target='_blank'>{$plugin_data['AuthorName']}</a>";
		} else {
			$author = $plugin_data['AuthorName'];
		}
		Property::update_array( $properties, 'author', null, $author );

		return $properties;
	}

	/**
	 * Get a plugin's data by its name.
	 *
	 * @param string $name The name domain of the plugin.
	 * @return ?array The plugin data or null if the plugin isn't found.
	 */
	public static function get_plugin_by_name( string $name ): ?array {
		// Include the necessary file if it's not already included.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Get all installed plugins.
		$all_plugins = get_plugins();

		// Loop through each plugin and check its text domain.
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			if ( isset( $plugin_data['Name'] ) && $plugin_data['Name'] === $name ) {
				// Add the file to the array.
				$plugin_data['File'] = $plugin_file;

				// Add the slug to the array.
				$plugin_data['Slug'] = self::get_slug( $plugin_file );

				return $plugin_data;
			}
		}

		// Return null if no plugin is found with the given text domain.
		return null;
	}

	/**
	 * If the plugin hasn't been deleted, get a link to its web page; otherwise, get a span with
	 * the name as the link text.
	 *
	 * @param string $name The name of the plugin.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( string $name ) {
		// Check if the plugin exists.
		$plugin = self::get_plugin_by_name( $name );

		// Provide a link to the plugin site.
		if ( $plugin ) {
			return "<a href='{$plugin['PluginURI']}' class='wp-logify-post-link' target='_blank'>$name</a>";
		}

		// The plugin has been deleted. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$name (deleted)</span>";
	}

	// =============================================================================================
	// Helper methods.

	/**
	 * Convert a plugin file to an object references.
	 *
	 * @param string $plugin_file The plugin file.
	 * @return Object_Reference[] The object references.
	 */
	private static function convert_plugin_file_to_object_reference( string $plugin_file ): Object_Reference {
		$plugin = self::load( $plugin_file );
		return new Object_Reference( 'plugin', null, $plugin['Name'] );
	}

	/**
	 * Convert an array of plugin files to an array of object references.
	 *
	 * @param string[] $plugin_files The plugin files.
	 * @return Object_Reference[] The object references.
	 */
	private static function convert_plugin_files_to_object_references( array $plugin_files ): array {
		return array_map( fn( $plugin_file ) => self::convert_plugin_file_to_object_reference( $plugin_file ), $plugin_files );
	}
}
