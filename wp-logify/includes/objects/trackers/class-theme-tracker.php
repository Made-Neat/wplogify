<?php
/**
 * Contains the Theme_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Theme_Upgrader;
use WP_Theme;
use WP_Upgrader;

/**
 * Class WP_Logify\Theme_Tracker
 *
 * Provides tracking of events related to themes.
 */
class Theme_Tracker extends Object_Tracker {

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Load the themes pages.
		add_action( 'load-themes.php', array( __CLASS__, 'on_load_themes_page' ), 10, 0 );
		add_action( 'load-theme-install.php', array( __CLASS__, 'on_load_themes_page' ), 10, 0 );

		// Theme install and update.
		add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrader_process_complete' ), 10, 2 );

		// Theme switch.
		add_action( 'switch_theme', array( __CLASS__, 'on_switch_theme' ), 10, 3 );

		// Theme delete.
		add_action( 'delete_theme', array( __CLASS__, 'on_delete_theme' ), 10, 2 );
	}

	/**
	 * Fires when the themes.php page is loaded.
	 */
	public static function on_load_themes_page() {
		// Store the current version numbers of all the installed themes, which enables us to show
		// the old version number when a theme is upgraded.
		$versions = array();

		// Get all the installed themes.
		$all_themes = wp_get_themes();

		// Loop through all the installed themes and store their version numbers.
		foreach ( $all_themes as $theme ) {
			$stylesheet              = $theme->get_stylesheet();
			$version                 = $theme->get( 'Version' );
			$versions[ $stylesheet ] = $version;
		}

		// Store the theme versions in a transient.
		set_transient( 'theme-versions', $versions );
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
		// Check this is a theme upgrader.
		if ( ! $upgrader instanceof Theme_Upgrader || $hook_extra['type'] !== 'theme' ) {
			return;
		}

		// If the result is not an array, it could be a WP_Error or could be null.
		// Either way, we aren't installing the new plugin on this HTTP request.
		// Most likely, there is already a version of this theme present, and they attempting an
		// upgrade, downgrade, or re-install, and WordPress is asking the user what the want to do.
		if ( ! is_array( $upgrader->result ) ) {
			return;
		}

		// debug( '$_POST', $_POST );
		// debug( '$upgrader->new_theme_data', $upgrader->new_theme_data );
		// debug( '$upgrader->result', $upgrader->result );

		// Try to load the theme.
		$theme_loaded = false;

		// Get the theme name.
		$name = $upgrader->new_theme_data['Name'] ?? null;
		if ( $name ) {
			// Load the theme by name.
			$theme = Theme_Utility::load_by_name( $name );
			if ( $theme ) {
				$theme_loaded = true;
				// Get the stylesheet.
				$stylesheet = $theme->get_stylesheet();
			}
		}

		// If we couldn't load it by name, we can try loading it by stylesheet.
		if ( ! $theme_loaded && ! empty( $_POST['slug'] ) ) {
			// Get the theme stylesheet from the $_POST data.
			$stylesheet = $_POST['slug'];

			// Load the theme by stylesheet.
			$theme = Theme_Utility::load( $stylesheet );
			if ( $theme ) {
				$theme_loaded = true;
			}
		}

		// If we couldn't load the theme, we can't log the event.
		if ( ! $theme_loaded ) {
			debug( "Couldn't load the theme." );
			return;
		}

		$props = array();

		// Get the old version, if it's there.
		$versions    = get_transient( 'theme-versions' );
		$old_version = $versions[ $stylesheet ] ?? null;

		if ( $old_version ) {
			// The user is overwriting the existing theme with a new version.

			// Get the new version.
			$new_version = $upgrader->new_theme_data['Version'];

			// Determine if we're upgrading, downgrading, or re-installing.
			$old_ver = Types::version_to_float( $old_version );
			$new_ver = Types::version_to_float( $new_version );

			if ( $old_ver < $new_ver ) {
				$event_type = 'Theme Upgraded';
			} elseif ( $old_ver > $new_ver ) {
				$event_type = 'Theme Downgraded';
			} else {
				$event_type = 'Theme Re-installed';
			}

			// Put the old and new versions in the props, if they are different.
			if ( $old_version !== $new_version ) {
				Property::update_array( $props, 'version', null, $old_version, $new_version );
			}
		} else {
			// The user is installing a new theme.
			$event_type = 'Theme Installed';
		}

		// Log the event.
		Logger::log_event( $event_type, $theme, null, $props );
	}

	/**
	 * Fires after the theme is switched.
	 *
	 * @param string   $new_name  Name of the new theme.
	 * @param WP_Theme $new_theme WP_Theme instance of the new theme.
	 * @param WP_Theme $old_theme WP_Theme instance of the old theme.
	 */
	public static function on_switch_theme( string $new_name, WP_Theme $new_theme, WP_Theme $old_theme ) {
		$metas         = array();
		$old_theme_ref = Object_Reference::new_from_wp_object( $old_theme );
		Eventmeta::update_array( $metas, 'old_theme', $old_theme_ref );
		Logger::log_event( 'Theme Switched', $new_theme, $metas );
	}

	/**
	 * Fires immediately before a theme deletion attempt.
	 *
	 * @param string $stylesheet Stylesheet of the theme to delete.
	 */
	public static function on_delete_theme( string $stylesheet ) {
		// Load the theme before it gets deleted.
		$theme = Theme_Utility::load( $stylesheet );

		// Log the theme deletion event.
		Logger::log_event( 'Theme Deleted', $theme );
	}
}
