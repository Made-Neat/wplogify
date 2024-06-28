<?php
/**
 * Contains the Widget class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Contains methods relating to the dashboard widget.
 */
class Widget {

	/**
	 * Initializes the class.
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widget' ) );
	}

	/**
	 * Adds the dashboard widget.
	 */
	public static function add_dashboard_widget() {
		// Check current user has access.
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
	 * Displays the dashboard widget.
	 */
	public static function display_dashboard_widget() {
		include plugin_dir_path( __FILE__ ) . '../templates/dashboard-widget.php';
	}

	/**
	 * Checks if the current user has access to the dashboard widget.
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
}
