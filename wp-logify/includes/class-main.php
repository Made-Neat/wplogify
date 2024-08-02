<?php
/**
 * Contains the Main class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use ReflectionClass;

/**
 * Class WP_Logify\Main
 *
 * Contains functions to perform actions on the plugin itself.
 */
class Main {

	/**
	 * Initialize the plugin.
	 */
	public static function init() {
		// Get all declared classes.
		$classes = get_declared_classes();

		// Iterate over each class.
		foreach ( $classes as $class ) {

			// Check if the class is in the WP_Logify namespace. Ignore the Main class (this class).
			if ( $class !== 'WP_Logify\\Main' && strpos( $class, 'WP_Logify\\' ) === 0 ) {

				// Use reflection to check for the init method.
				$reflection = new ReflectionClass( $class );

				if ( $reflection->hasMethod( 'init' ) ) {
					$method = $reflection->getMethod( 'init' );

					// Check if the init method is static.
					if ( $method->isStatic() ) {
						// Call the init method.
						$method->invoke( null );
					}
				}
			}
		}
	}

	/**
	 * Run on activation.
	 */
	public static function activate() {
		// Make sure the dbDelta() function is available.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create the tables.
		Database::create_all_tables();
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
		// Drop the tables if the option is set to do so.
		if ( Settings::get_delete_on_uninstall() ) {
			Database::drop_all_tables();
		}

		// Delete settings.
		Settings::delete_all();
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
