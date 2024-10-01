<?php
/**
 * Contains the Dashboard_Widget class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

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
		if ( ! Access_Control::can_access_log_page() ) {
			return;
		}

		// Add the widget.
		wp_add_dashboard_widget(
			'logify_wp_dashboard_widget',
			'Logify WP - Recent Site Activity',
			array( __CLASS__, 'display_dashboard_widget' )
		);
	}

	/**
	 * Displays the dashboard widget.
	 */
	public static function display_dashboard_widget() {
		include LOGIFY_WP_PLUGIN_DIR . 'templates/dashboard-widget.php';
	}
}
