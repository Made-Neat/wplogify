<?php
/**
 * Class WP_Logify_Admin
 *
 * This class handles the WP Logify admin functionality.
 */
class WP_Logify_Admin {

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
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widget' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );
		add_action( 'wp_ajax_wp_logify_fetch_logs', array( 'WP_Logify_Log_Page', 'fetch_logs' ) );
		add_action( 'admin_init', array( __CLASS__, 'restrict_access' ) );
		add_action( 'admin_post_wp_logify_reset_logs', array( __CLASS__, 'reset_logs' ) );
	}

	/**
	 * Adds the admin menu for the WP Logify plugin.
	 *
	 * This method adds the main menu page and submenu pages for the WP Logify plugin
	 * in the WordPress admin dashboard. It also checks the user's access roles and
	 * only displays the menu if the current user has the required access.
	 *
	 * @return void
	 */
	public static function add_admin_menu() {
		$access_roles = get_option( 'wp_logify_view_roles', array( 'administrator' ) );
		if ( ! self::current_user_has_access( $access_roles ) ) {
			return;
		}

		$hook = add_menu_page( 'WP Logify', 'WP Logify', 'manage_options', 'wp-logify', array( 'WP_Logify_Log_Page', 'display_log_page' ), 'dashicons-list-view' );
		add_submenu_page( 'wp-logify', 'Log', 'Log', 'manage_options', 'wp-logify', array( 'WP_Logify_Log_Page', 'display_log_page' ) );
		add_submenu_page( 'wp-logify', 'Settings', 'Settings', 'manage_options', 'wp-logify-settings', array( __CLASS__, 'display_settings_page' ) );
		add_action( "load-$hook", array( __CLASS__, 'add_screen_options' ) );
	}

	/**
	 * Registers the settings for the WP Logify plugin.
	 */
	public static function register_settings() {
		register_setting( 'wp_logify_settings_group', 'wp_logify_api_key' );
		register_setting( 'wp_logify_settings_group', 'wp_logify_delete_on_uninstall' );
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_roles_to_track',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'wp_logify_sanitize_roles' ),
				'default'           => array( 'administrator' ),
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_view_roles',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'wp_logify_sanitize_roles' ),
				'default'           => array( 'administrator' ),
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_keep_forever',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_keep_period_quantity',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 1,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_keep_period_units',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'year',
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_wp_cron_tracking',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
	}

	/**
	 * Sanitizes the given array of roles by filtering out any invalid roles.
	 *
	 * @param array $roles The array of roles to be sanitized.
	 * @return array The sanitized array of roles.
	 */
	public static function wp_logify_sanitize_roles( $roles ) {
		$valid_roles = array_keys( wp_roles()->roles );
		return array_filter(
			$roles,
			function ( $role ) use ( $valid_roles ) {
				return in_array( $role, $valid_roles );
			}
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
			'label'   => __( 'Activities per page', 'wp-logify' ),
			'default' => 20,
			'option'  => 'activities_per_page',
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
		if ( 'activities_per_page' == $option ) {
			return $value;
		}
		return $status;
	}

	/**
	 * Displays the settings page for the WP Logify plugin.
	 *
	 * This method includes the settings-page.php template file to render the settings page.
	 *
	 * @return void
	 */
	public static function display_settings_page() {
		include plugin_dir_path( __FILE__ ) . '../templates/settings-page.php';
	}

	/**
	 * Adds a dashboard widget for WP Logify plugin.
	 *
	 * This function checks the user's access roles and adds the dashboard widget only if the user
	 * has the required access.
	 *
	 * The dashboard widget displays recent site activities.
	 *
	 * @return void
	 */
	public static function add_dashboard_widget() {
		$access_roles = get_option( 'wp_logify_view_roles', array( 'administrator' ) );
		if ( ! self::current_user_has_access( $access_roles ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wp_logify_dashboard_widget',
			'WP Logify - Recent Site Activity',
			array( __CLASS__, 'display_dashboard_widget' )
		);
	}

	/**
	 * Displays the dashboard widget for WP Logify plugin.
	 */
	public static function display_dashboard_widget() {
		include plugin_dir_path( __FILE__ ) . '../templates/dashboard-widget.php';
	}

	/**
	 * Converts a filename to a handle for WP Logify plugin.
	 *
	 * This function takes a filename and converts it into a handle by replacing dots with dashes and converting it to lowercase.
	 * The resulting handle is prefixed with 'wp-logify-'.
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
		$handle   = self::filename_to_handle( $src );
		$src_url  = plugin_dir_url( __FILE__ ) . '../assets/css/' . $src;
		$src_path = plugin_dir_path( __FILE__ ) . '../assets/css/' . $src;
		$ver      = 'auto' === $ver ? filemtime( $src_path ) : $ver;
		wp_enqueue_style( $handle, $src_url, $deps, $ver, $media );
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
		$handle   = self::filename_to_handle( $src );
		$src_url  = plugin_dir_url( __FILE__ ) . '../assets/js/' . $src;
		$src_path = plugin_dir_path( __FILE__ ) . '../assets/js/' . $src;
		$ver      = 'auto' === $ver ? filemtime( $src_path ) : $ver;
		wp_enqueue_script( $handle, $src_url, $deps, $ver, $args );
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
			self::enqueue_style( 'dataTables.2.0.8.css', array(), null );
			self::enqueue_script( 'dataTables.2.0.8.js', array( 'jquery' ), null, true );
		}

		// Attach activity script to all pages.
		$activity_script_handle = self::enqueue_script( 'activity.js', array( 'jquery' ), 'auto', true );

		// Localise script to pass AJAX URL and nonce.
		wp_localize_script(
			$activity_script_handle,
			'wpLogifyActivity',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wp_logify_activity_nonce' ),
			)
		);
	}

	/**
	 * Restricts access to the WP Logify plugin based on user roles or plugin installer status.
	 *
	 * This method checks the current screen and redirects the user to the admin dashboard if access
	 * is restricted.
	 *
	 * The access control can be set to either 'only_me' or 'user_roles' in the plugin settings.
	 * If 'only_me' is selected, only the plugin installer has access.
	 * If 'user_roles' is selected, only users with specific roles defined in the plugin settings
	 * have access.
	 *
	 * @return void
	 */
	public static function restrict_access() {
		$screen = get_current_screen();

		if ( $screen === null ) {
			return;
		}

		if ( strpos( $screen->id, 'wp-logify' ) !== false ) {
			$access_control = get_option( 'wp_logify_access_control', 'only_me' );
			if ( $access_control === 'only_me' && ! self::is_plugin_installer() ) {
				wp_redirect( admin_url() );
				exit;
			} elseif ( $access_control === 'user_roles' && ! self::current_user_has_access( get_option( 'wp_logify_view_roles', array( 'administrator' ) ) ) ) {
				wp_redirect( admin_url() );
				exit;
			}
		}
	}

	/**
	 * Resets logs by truncating the wp_logify_events table and redirects to the settings page.
	 *
	 * @return void
	 */
	public static function reset_logs() {
		global $wpdb;
		$table_name = WP_Logify_Logger::get_table_name();
		$wpdb->query( "TRUNCATE TABLE $table_name" );
		wp_redirect( admin_url( 'admin.php?page=wp-logify-settings&reset=success' ) );
		exit;
	}

	/**
	 * Hides the plugin from the list of installed plugins based on access control settings.
	 *
	 * @param array $plugins The array of installed plugins.
	 * @return array The modified array of installed plugins.
	 */
	public static function hide_plugin_from_list( $plugins ) {
		// Retrieve the access control setting from the options.
		$access_control = get_option( 'wp_logify_access_control', 'only_me' );

		// Check if the access control is set to 'only_me' and the current user is not the plugin
		// installer.
		if ( $access_control === 'only_me' && ! self::is_plugin_installer() ) {
			// Remove the plugin from the list of installed plugins.
			unset( $plugins[ plugin_basename( __FILE__ ) ] );
		}
		// Check if the access control is set to 'user_roles' and the current user does not have
		// access based on the specified roles.
		elseif ( $access_control === 'user_roles' && ! self::current_user_has_access( get_option( 'wp_logify_view_roles', array( 'administrator' ) ) ) ) {
			// Remove the plugin from the list of installed plugins.
			unset( $plugins[ plugin_basename( __FILE__ ) ] );
		}

		// Return the modified array of installed plugins.
		return $plugins;
	}

	/**
	 * Checks if the current user has access based on their roles.
	 *
	 * @param array $roles An array of roles to check against.
	 * @return bool Returns true if the current user has any of the specified roles, false otherwise.
	 */
	private static function current_user_has_access( $roles ) {
		$user = wp_get_current_user();
		foreach ( $roles as $role ) {
			if ( in_array( $role, $user->roles ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if the current user is the plugin installer.
	 *
	 * @return bool Returns true if the current user is the plugin installer, false otherwise.
	 */
	private static function is_plugin_installer() {
		$plugin_installer = get_option( 'wp_logify_plugin_installer' );
		return $plugin_installer && get_current_user_id() == $plugin_installer;
	}
}

// Initialize the plugin
WP_Logify_Admin::init();
