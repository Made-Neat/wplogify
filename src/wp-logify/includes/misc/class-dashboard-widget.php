<?php
/**
 * Contains the Dashboard_Widget class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_User;

/**
 * Contains methods relating to the dashboard widget.
 */
class Dashboard_Widget {

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
		if ( ! User_Utility::current_user_has_role( 'administrator' ) ) {
			return;
		}

		// Add the widget.
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
		include WP_LOGIFY_PLUGIN_DIR . 'templates/dashboard-widget.php';
	}
}
