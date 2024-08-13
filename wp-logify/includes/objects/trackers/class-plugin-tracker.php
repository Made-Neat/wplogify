<?php
/**
 * Contains the Plugin_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Upgrader;
use Plugin_Upgrader;

/**
 * Class WP_Logify\Plugin_Tracker
 *
 * Provides tracking of events related to plugins.
 */
class Plugin_Tracker extends Object_Tracker {

	/**
	 * Set up hooks for the events we want to log.
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

		// Get the plugin name and load the plugin.
		$plugin_name = $upgrader->new_plugin_data['Name'];
		$plugin_data = Plugin_Utility::load_by_name( $plugin_name );

		// If the result is null, the plugin was not installed or updated, so we won't log anything.
		if ( $upgrader->result === null ) {

			// The user may be downgrading the plugin, so let's store the current plugin verison in
			// the options.
			if ( $plugin_data ) {
				$version_key = $plugin_name . ' version';
				$old_version = $plugin_data['Version'] ?? null;
				if ( $old_version ) {
					update_option( $version_key, $old_version );
				}
			}

			return;
		}

		// Default the old version to null (i.e. the plugin is new).
		$old_version = null;

		// Get the event type.
		if ( $installing_plugin ) {
			// Default event type verb.
			$verb = 'Installed';

			// Handle upgrade and downgrade events.
			if ( $upgrader->result['clear_destination'] ) {
				if ( $upgrader->result['clear_destination'] === 'downgrade-plugin' ) {
					$verb = 'Downgraded';
				} elseif ( $upgrader->result['clear_destination'] === 'update-plugin' ) {
					$verb = 'Upgraded';
				}
			}

			// See if the old version was stored in the options.
			$version_key = $plugin_name . ' version';
			$old_version = get_option( $version_key );

			// Remove the option, as we don't need it anymore.
			if ( $old_version ) {
				delete_option( $version_key );
			}
		} else {
			// Updating the plugin from the install page.
			$verb        = 'Upgraded';
			$old_version = $upgrader->skin->plugin_info['Version'] ?? null;
		}

		$event_type = "Plugin $verb";

		// If we have both the old and new versions, show this.
		$properties  = array();
		$new_version = $upgrader->new_plugin_data['Version'] ?? null;
		if ( $old_version && $new_version && $old_version !== $new_version ) {
			Property::update_array( $properties, 'version', null, $old_version, $new_version );
		}

		// Log the event.
		Logger::log_event(
			$event_type,
			Object_Reference::new_from_wp_object( $plugin_data ),
			null,
			$properties
		);
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
		$plugin_data = Plugin_Utility::load_by_file( $plugin_file );

		// If this is a multisite, record if the plugin activation was network-wide.
		if ( is_multisite() ) {
			Eventmeta::update_array( self::$eventmetas, 'network_wide', $network_wide );
		}

		// Log the event.
		Logger::log_event(
			'Plugin Activated',
			Object_Reference::new_from_wp_object( $plugin_data ),
			self::$eventmetas
		);
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
		$plugin_data = Plugin_Utility::load_by_file( $plugin_file );

		// If this is a multisite, record if the plugin deactivation was network-wide.
		if ( is_multisite() ) {
			Eventmeta::update_array( self::$eventmetas, 'network_wide', $network_deactivating );
		}

		// Log the event.
		Logger::log_event(
			'Plugin Deactivated',
			Object_Reference::new_from_wp_object( $plugin_data ),
			self::$eventmetas
		);
	}

	/**
	 * Fires immediately before a plugin deletion attempt.
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 */
	public static function on_delete_plugin( string $plugin_file ) {
		// Load the plugin.
		$plugin_data = Plugin_Utility::load_by_file( $plugin_file );

		// Log the event.
		Logger::log_event(
			'Plugin Deleted',
			Object_Reference::new_from_wp_object( $plugin_data )
		);
	}

	/**
	 * Fires in uninstall_plugin() immediately before the plugin is uninstalled.
	 *
	 * @param string $plugin_file           Path to the plugin file relative to the plugins directory.
	 * @param array  $uninstallable_plugins Uninstallable plugins.
	 */
	public static function on_pre_uninstall_plugin( string $plugin_file, array $uninstallable_plugins ) {
		// Load the plugin.
		$plugin_data = Plugin_Utility::load_by_file( $plugin_file );

		// Log the event.
		Logger::log_event(
			'Plugin Uninstalled',
			Object_Reference::new_from_wp_object( $plugin_data )
		);
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
		foreach ( $enabled as $plugin_file ) {
			// Check the plugin exists.
			if ( ! Plugin_Utility::exists( $plugin_file ) ) {
				continue;
			}

			// Load the plugin.
			$plugin_data = Plugin_Utility::load_by_file( $plugin_file );

			// Log the event.
			Logger::log_event(
				'Plugin Auto-update Enabled',
				Object_Reference::new_from_wp_object( $plugin_data )
			);
		}

		// Log an event for each plugin for which auto-update has been enabled.
		$disabled = array_diff( $old_value, $value );
		foreach ( $disabled as $plugin_file ) {
			// Check the plugin exists.
			if ( ! Plugin_Utility::exists( $plugin_file ) ) {
				continue;
			}

			// Load the plugin.
			$plugin_data = Plugin_Utility::load_by_file( $plugin_file );

			// Log the event.
			Logger::log_event(
				'Plugin Auto-update Disabled',
				Object_Reference::new_from_wp_object( $plugin_data )
			);
		}
	}
}
