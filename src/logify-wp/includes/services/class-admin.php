<?php
/**
 * Contains the Admin class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use WP_Admin_Bar;

/**
 * Class Logify_WP\Admin
 *
 * This class handles the Logify WP admin functionality.
 */
class Admin {

	/**
	 * Initializes the Logify WP admin functionality.
	 *
	 * This method adds various actions and filters to set up the Logify WP plugin
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
		// Add the admin menu.
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ), 10, 0 );

		// Add the Logify WP menu to the Admin bar.
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_menu' ), 100, 1 );

		// Enqueue the necessary assets for the Logify WP admin pages.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 10, 1 );

		// Add screen options for the Logify WP plugin.
		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );

		// Add the log reset functionality.
		add_action( 'admin_post_logify_wp_reset_logs', array( __CLASS__, 'reset_logs' ), 10, 0 );
	}

	/**
	 * Adds the admin menu for the Logify WP plugin.
	 *
	 * This method adds the main menu page and submenu pages for the Logify WP plugin
	 * in the WordPress admin dashboard.
	 */
	public static function add_admin_menu() {
		// Check if the user has access to the Logify WP log page.
		if ( ! Access_Control::can_access_log_page() ) {
			return;
		}

		// Add the main menu page and submenus.
		$hook = add_menu_page( 'Logify WP', 'Logify WP', 'read', 'logify-wp', array( 'Logify_WP\Log_Page', 'display_log_page' ), 'dashicons-list-view' );

		// Log page submenu.
		add_submenu_page( 'logify-wp', 'View Log', 'View Log', 'read', 'logify-wp', array( 'Logify_WP\Log_Page', 'display_log_page' ) );

		// Check if the notes feature is enabled
		if (get_option('logify_wp_enable_notes', false)) {
			// Log page submenu.
			$noteshook = add_submenu_page( 'logify-wp', 'View Notes', 'View Notes', 'read', 'logify-wp-notes', array( 'Logify_WP\Notes_Page', 'display_notes_page' ) );
			add_action( "load-$noteshook", array( __CLASS__, 'add_screen_options' ) );
		}
		// Settings submenu (only for users with 'manage_options' capability).
		add_submenu_page( 'logify-wp', 'Settings', 'Settings', 'manage_options', 'logify-wp-settings', array( 'Logify_WP\Plugin_Settings', 'display_settings_page' ) );

		// Add screen options for the log page.
		add_action( "load-$hook", array( __CLASS__, 'add_screen_options' ) );
	}

	/**
	 * Adds Logify WP menu to the Admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The Admin bar object.
	 */
	public static function add_admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) {
		// Don't show if the they don't want to see it in the admin bar, or if they don't have access.
		// If they don't have access to the log page, they also don't have access to the settings page.
		// (If they did, they could give themselves access to the log page!)
		if ( ! Plugin_Settings::get_show_in_admin_bar() || ! Access_Control::can_access_log_page() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'logify-wp',
				'title' => 'Logify WP',
				'href'  => admin_url( 'admin.php?page=logify-wp' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'logify-wp-view-log',
				'parent' => 'logify-wp',
				'title'  => 'View Log',
				'href'   => admin_url( 'admin.php?page=logify-wp' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'logify-wp-view-log',
				'parent' => 'logify-wp',
				'title'  => 'View Log',
				'href'   => admin_url( 'admin.php?page=logify-wp' ),
			)
		);

		// Check if the notes feature is enabled
		if (get_option('logify_wp_enable_notes', false)) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'logify-wp-notes',
					'parent' => 'logify-wp',
					'title'  => 'View Notes',
					'href'   => admin_url('admin.php?page=logify-wp-notes'),
				)
			);
		}

		// If the user has the manage_options capability, show the settings link.
		if ( current_user_can( 'manage_options' ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'logify-wp-settings',
					'parent' => 'logify-wp',
					'title'  => 'Settings',
					'href'   => admin_url( 'admin.php?page=logify-wp-settings' ),
				)
			);
		}
	}

	/**
	 * Adds screen options for the Logify WP plugin.
	 *
	 * This function adds a screen option for the number of activities to display per page in the
	 * Logify WP plugin.
	 *
	 * @return void
	 */
	public static function add_screen_options() {
		$option = 'per_page';
		$args   = array(
			'label'   => __( 'Log entries per page', 'logify-wp' ),
			'default' => 20,
			'option'  => 'logify_wp_events_per_page',
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
		if ( 'logify_wp_events_per_page' === $option ) {
			return $value;
		}
		return $status;
	}

	/**
	 * Converts a filename to a handle for Logify WP script or style.
	 *
	 * This function takes a filename and converts it into a handle by replacing dots with dashes
	 * and converting it to lowercase. The resulting handle is prefixed with 'logify-wp-'.
	 *
	 * @param string $filename The filename to convert.
	 * @return string The handle for the Logify WP plugin.
	 */
	private static function filename_to_handle( $filename ) {
		return 'logify-wp-' . strtolower( str_replace( '.', '-', pathinfo( $filename, PATHINFO_FILENAME ) ) );
	}

	/**
	 * Enqueues a stylesheet for the Logify WP plugin.
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
		// If auto is specified, use the file modified time as the version number.
		$ver = $ver === 'auto' ? filemtime( LOGIFY_WP_PLUGIN_DIR . $src ) : $ver;

		// Enqueue the script.
		$handle  = self::filename_to_handle( $src );
		$src_url = LOGIFY_WP_PLUGIN_URL . $src;
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
		// If auto is specified, use the file modified time as the version number.
		$ver = $ver === 'auto' ? filemtime( LOGIFY_WP_PLUGIN_DIR . $src ) : $ver;

		// Enqueue the script.
		$handle  = self::filename_to_handle( $src );
		$src_url = LOGIFY_WP_PLUGIN_URL . $src;
		wp_enqueue_script( $handle, $src_url, $deps, $ver, $args );

		// Return the handle.
		return $handle;
	}

	/**
	 * Enqueues the necessary assets for the Logify WP admin pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_assets( $hook ) { 
		// Dashboard widget.
		if ( $hook === 'index.php' ) {
			// Styles.
			self::enqueue_style( 'assets/css/dashboard-widget.css' );

			// Scripts.
			self::enqueue_script( 'assets/js/dashboard-widget.js', array( 'jquery' ), 'auto', true );
		}

		// Settings.
		if ( $hook === 'logify-wp_page_logify-wp-settings' ) {
			self::enqueue_style( 'assets/css/settings.css' );
			self::enqueue_script( 'assets/js/settings-page.js', array( 'jquery' ), 'auto', true );
		}

		// Log page
		if ($hook === 'toplevel_page_logify-wp') {
			self::setup_page($hook, 'assets/js/log-page.js', 'logify-wp-log-page');
		}

		// Notes page
		if ($hook === 'logify-wp_page_logify-wp-notes') {
			self::setup_page($hook, 'assets/js/notes-page.js', 'logify-wp-notes-page');
		}
	}


	// Helper function for common setups
	private static function setup_page($hook, $script_file, $nonce_action) {
		wp_enqueue_editor();
		
		// Include jQuery UI Dialog
		wp_enqueue_script('jquery-ui-dialog');
		wp_enqueue_style('jquery-ui-dialog'); // Ensure styles for the dialog are loaded
		
		// Styles
		self::enqueue_style('assets/css/log-page.css');

		// Get the date format to pass to JS
		$date_format = DateTimes::convert_php_date_format_to_js(get_option('date_format'));

		// Scripts
		$script_handle = self::enqueue_script($script_file, array('jquery'), 'auto', true);
		wp_localize_script(
			$script_handle,
			'logifyWpLogPage',
			array(
				'ajaxUrl'    => admin_url('admin-ajax.php'),
				'ajaxNonce'  => wp_create_nonce($nonce_action),
				'dateFormat' => $date_format,
			)
		);

		// Include jQuery UI datepicker
		wp_enqueue_script('jquery-ui-datepicker');
		self::enqueue_style('assets/jquery/jquery-ui.css', array(), '1.14.0');

		// DataTables assets
		self::enqueue_style('assets/datatables/datatables.css', array(), '2.0.8');
		self::enqueue_script('assets/datatables/datatables.js', array('jquery'), '2.0.8', true);

		// Common script
		self::enqueue_script('assets/js/common-page.js', array('jquery'), 'auto', true);
	}


	/**
	 * Resets logs by truncating the logify_wp_events table and redirects to the settings page.
	 *
	 * @return void
	 */
	public static function reset_logs() {
		// Verify nonce.
		$nonce = isset( $_GET['logify_wp_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['logify_wp_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'logify_wp_reset_logs_action' ) ) {
			wp_die( esc_html( 'Security check failed. Please try again.' ), esc_html( 'Error' ), array( 'response' => 403 ) );
		}

		// Check user capabilities.
		if ( ! Access_Control::can_access_settings_page() ) {
			wp_die( esc_html( 'You are not allowed to perform this action.' ), esc_html( 'Error' ), array( 'response' => 403 ) );
		}

		// Do the reset.
		Database::truncate_all_tables();

		// After performing the action, generate a nonce for the redirect
		$redirect_nonce = wp_create_nonce( 'logify_wp_messages_nonce' );

		// Redirect back with success message and nonce
		wp_redirect(
			add_query_arg(
				array(
					'reset'           => 'success',
					'logify_wp_nonce' => $redirect_nonce,
				),
				admin_url( 'admin.php?page=logify-wp-settings' )
			)
		);
		exit;
	}
}
