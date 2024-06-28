<?php
/**
 * Contains the Plugin class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Plugin
 *
 * Contains functions to perform actions on the plugin itself.
 */
class Plugin {

	/**
	 * Initialize the plugin.
	 */
	public static function init() {
		Admin::init();
		Cron::init();
		Logger::init();
		Posts::init();
		Users::init();
		Widget::init();
	}

	/**
	 * Run on activation.
	 */
	public static function activate() {
		Logger::create_table();
	}

	/**
	 * Run on deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wp_logify_cleanup_logs' );
	}

	/**
	 * Run on uninstallation.
	 */
	public static function uninstall() {
		// Delete options.
		delete_option( 'wp_logify_view_roles' );
		delete_option( 'wp_logify_api_key' );
		delete_option( 'wp_logify_keep_forever' );
		delete_option( 'wp_logify_keep_period_quantity' );
		delete_option( 'wp_logify_keep_period_units' );

		// Drop the events table.
		Logger::drop_table();
	}

	/**
	 * Create plugin action links and attach to existing array.
	 *
	 * @param array $links Existing links.
	 * @return array The modified array of links.
	 */
	public static function add_action_links( array $links ) {
		// Link to settings.
		$settings_page_link = '<a href="' . admin_url( 'admin.php?page=wp-logify-settings' ) . '">' . __( 'Settings', 'wp-logify' ) . '</a>';
		array_unshift( $links, $settings_page_link );

		// Link to view the log.
		$log_page_link = '<a href="' . admin_url( 'admin.php?page=wp-logify' ) . '">' . __( 'View log', 'wp-logify' ) . '</a>';
		array_unshift( $links, $log_page_link );

		return $links;
	}
}
