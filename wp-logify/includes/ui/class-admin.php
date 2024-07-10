<?php
/**
 * Contains the Admin class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Admin_Bar;

/**
 * Class WP_Logify\Admin
 *
 * This class handles the WP Logify admin functionality.
 */
class Admin {

	/**
	 * Initializes the WP Logify admin functionality.
	 *
	 * This method adds various actions and filters to set up the WP Logify plugin
	 * in the WordPress admin area.
	 *
	 * admin area.
	 *
	 * It registers the admin menu, settings, dashboard widget, assets, screen option,
	 * AJAX endpoint, access restriction, and log reset functionality.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_menu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );
		add_action( 'admin_post_wp_logify_reset_logs', array( __CLASS__, 'reset_logs' ) );
	}

	/**
	 * Adds the admin menu for the WP Logify plugin.
	 *
	 * This method adds the main menu page and submenu pages for the WP Logify plugin
	 * in the WordPress admin dashboard.
	 *
	 * It also ensures the user is an administrator.
	 */
	public static function add_admin_menu() {
		if ( ! Users::current_user_has_role( 'administrator' ) ) {
			return;
		}

		$hook = add_menu_page( 'WP Logify', 'WP Logify', 'manage_options', 'wp-logify', array( 'WP_Logify\Log_Page', 'display_log_page' ), 'dashicons-list-view' );
		add_submenu_page( 'wp-logify', 'View Log', 'View Log', 'manage_options', 'wp-logify', array( 'WP_Logify\Log_Page', 'display_log_page' ) );
		add_submenu_page( 'wp-logify', 'Settings', 'Settings', 'manage_options', 'wp-logify-settings', array( 'WP_Logify\Settings', 'display_settings_page' ) );
		add_action( "load-$hook", array( __CLASS__, 'add_screen_options' ) );
	}

	/**
	 * Adds WP Logify menu to the Admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The Admin bar object.
	 */
	public static function add_admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) {
		// Don't show if the user isn't an admin or if they don't want to see it in the admin bar.
		if ( ! Users::current_user_has_role( 'administrator' ) || ! Settings::get_show_in_admin_bar() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'wp-logify',
				'title' => 'WP Logify',
				'href'  => admin_url( 'admin.php?page=wp-logify' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'wp-logify-view-log',
				'parent' => 'wp-logify',
				'title'  => 'View Log',
				'href'   => admin_url( 'admin.php?page=wp-logify' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'wp-logify-settings',
				'parent' => 'wp-logify',
				'title'  => 'Settings',
				'href'   => admin_url( 'admin.php?page=wp-logify-settings' ),
			)
		);
	}

	/**
	 * Adds screen options for the WP Logify plugin.
	 *
	 * This function adds a screen option for the number of activities to display per page in the
	 * WP Logify plugin.
	 *
	 * @return void
	 */
	public static function add_screen_options() {
		$option = 'per_page';
		$args   = array(
			'label'   => __( 'Log entries per page', 'wp-logify' ),
			'default' => 20,
			'option'  => 'wp_logify_events_per_page',
		);
		add_screen_option( $option, $args );
	}

	/**
	 * Sets the screen option value for a specific option.
	 *
	 * @param mixed  $status The current status of the screen option.
	 * @param string $option The name of the screen option.
	 * @param mixed  $value The new value to set for the screen option.
	 * @return mixed The updated status of the screen option.
	 */
	public static function set_screen_option( $status, $option, $value ) {
		if ( 'wp_logify_events_per_page' === $option ) {
			return $value;
		}
		return $status;
	}

	/**
	 * Converts a filename to a handle for WP Logify script or style.
	 *
	 * This function takes a filename and converts it into a handle by replacing dots with dashes
	 * and converting it to lowercase. The resulting handle is prefixed with 'wp-logify-'.
	 *
	 * @param string $filename The filename to convert.
	 * @return string The handle for the WP Logify plugin.
	 */
	private static function filename_to_handle( $filename ) {
		return 'wp-logify-' . strtolower( str_replace( '.', '-', pathinfo( $filename, PATHINFO_FILENAME ) ) );
	}

	/**
	 * Enqueues a stylesheet for the WP Logify plugin.
	 *
	 * @param string           $src    The source file path of the stylesheet.
	 * @param array            $deps   Optional. An array of dependencies for the stylesheet. Default is an empty array.
	 * @param string|bool|null $ver    Optional. The version number of the stylesheet.
	 *      If set to false (default), the WordPress version number will be used.
	 *      If set to 'auto', it will use the last modified time of the source file as the version.
	 *      If set to null, no version number will be added.
	 * @param string           $media  Optional. The media type for which the stylesheet is defined. Default is 'all'.
	 * @return string The handle of the enqueued stylesheet.
	 */
	public static function enqueue_style( $src, $deps = array(), $ver = 'auto', $media = 'all' ): string {
		global $wp_logify_plugin_url;
		global $wp_logify_plugin_dir;

		// If auto is specified, use the file modified time as the version number.
		$ver = 'auto' === $ver ? filemtime( "$wp_logify_plugin_dir/assets/css/$src" ) : $ver;

		// Enqueue the script.
		$handle  = self::filename_to_handle( $src );
		$src_url = "$wp_logify_plugin_url/assets/css/$src";
		wp_enqueue_style( $handle, $src_url, $deps, $ver, $media );

		// Return the handle.
		return $handle;
	}

	/**
	 * Enqueues a script in WordPress.
	 *
	 * @param string           $src The source file path of the script.
	 * @param array            $deps Optional. An array of script handles that this script depends on.
	 * @param string|bool|null $ver Optional. The version number of the script.
	 *      If set to false (default), the WordPress version number will be used.
	 *      If set to 'auto', it will use the last modified time of the source file as the version.
	 *      If set to null, no version number will be added.
	 * @param array|bool       $args Optional. Additional arguments for the script.
	 * @return string The handle of the enqueued script.
	 */
	public static function enqueue_script( $src, $deps = array(), $ver = 'auto', $args = array() ): string {
		global $wp_logify_plugin_url;
		global $wp_logify_plugin_dir;

		// If auto is specified, use the file modified time as the version number.
		$ver = 'auto' === $ver ? filemtime( "$wp_logify_plugin_dir/assets/js/$src" ) : $ver;

		// Enqueue the script.
		$handle  = self::filename_to_handle( $src );
		$src_url = "$wp_logify_plugin_url/assets/js/$src";
		wp_enqueue_script( $handle, $src_url, $deps, $ver, $args );

		// Return the handle.
		return $handle;
	}

	/**
	 * Enqueues the necessary assets for the WP Logify admin pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		// Dashboard widget.
		if ( $hook === 'index.php' ) {
			self::enqueue_style( 'dashboard-widget.css' );
		}

		// Settings.
		if ( $hook === 'wp-logify_page_wp-logify-settings' ) {
			self::enqueue_style( 'settings.css' );
		}

		// Log page.
		if ( $hook === 'toplevel_page_wp-logify' ) {
			// Styles.
			self::enqueue_style( 'log-page.css' );

			// Scripts.
			$log_page_script_handle = self::enqueue_script( 'log-page.js', array( 'jquery' ), 'auto', true );
			// The handle here must match the handle of the JS script to attach to.
			// So we must remember that the self::enqueue_script() method prepends 'wp-logify-' to the handle.
			wp_localize_script(
				$log_page_script_handle,
				'wpLogifyLogPage',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
				)
			);

			// DataTables assets.
			self::enqueue_style( 'dataTables.css', array(), null );
			self::enqueue_script( 'dataTables.js', array( 'jquery' ), null, true );
		}
	}

	/**
	 * Resets logs by truncating the wp_logify_events table and redirects to the settings page.
	 *
	 * @return void
	 */
	public static function reset_logs() {
		Event_Repository::truncate_table();
		wp_safe_redirect( admin_url( 'admin.php?page=wp-logify-settings&reset=success' ) );
		exit;
	}
}
