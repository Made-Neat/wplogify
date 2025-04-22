<?php
/**
 * Contains the Main class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use ReflectionClass;

/**
 * Class Logify_WP\Main
 *
 * Contains functions to perform actions on the plugin itself.
 */
class Main {

	const DB_VERSION = '1.2.0'; // Update this when changing the database structure.

	/**
	 * Initialize the plugin.
	 */
	public static function init() {
		// Get WordPress content directory
		$content_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-content';
		if ( defined('WP_CONTENT_DIR') ) {
			$content_dir = WP_CONTENT_DIR;
		}
		$debug_log_path = $content_dir . '/debug.log';
		
		// Ensure directory exists and is writable
		if (!file_exists($content_dir)) {
			error_log('Logify WP: wp-content directory does not exist: ' . $content_dir);
			return;
		}
		
		// Ensure log file exists and is writable
		if (!file_exists($debug_log_path)) {
			if (touch($debug_log_path)) {
				chmod($debug_log_path, 0666);
				error_log('Logify WP: Created debug.log file at: ' . $debug_log_path);
			} else {
				error_log('Logify WP: Failed to create debug.log file at: ' . $debug_log_path);
			}
		}
		
		// Set custom error log path
		ini_set('error_log', $debug_log_path);
		
		// Log plugin initialization
		error_log('Logify WP: Plugin initialization started at ' . date('Y-m-d H:i:s'));
		error_log('Logify WP: Using debug log at: ' . $debug_log_path);
		
		// Ensure debug is always enabled while plugin is active
		if (!defined('WP_DEBUG')) {
			error_log('Logify WP: WP_DEBUG was not defined, setting to true');
			define('WP_DEBUG', true);
		} else {
			error_log('Logify WP: WP_DEBUG was already defined as: ' . (WP_DEBUG ? 'true' : 'false'));
		}

		if (!defined('WP_DEBUG_LOG')) {
			error_log('Logify WP: WP_DEBUG_LOG was not defined, setting to true');
			define('WP_DEBUG_LOG', true);
		} else {
			error_log('Logify WP: WP_DEBUG_LOG was already defined as: ' . (WP_DEBUG_LOG ? 'true' : 'false'));
		}

		if (!defined('WP_DEBUG_DISPLAY')) {
			error_log('Logify WP: WP_DEBUG_DISPLAY was not defined, setting to false');
			define('WP_DEBUG_DISPLAY', false);
		} else {
			error_log('Logify WP: WP_DEBUG_DISPLAY was already defined as: ' . (WP_DEBUG_DISPLAY ? 'true' : 'false'));
		}

		$display_errors = ini_set('display_errors', 0);
		error_log('Logify WP: display_errors was set to: ' . ($display_errors === false ? 'failed' : '0'));
		
		add_action('upgrader_process_complete', [__CLASS__, 'maybe_upgrade_db'], 10, 2);
    
		// Only check DB upgrade when loading admin area (to avoid unnecessary checks on frontend).
		if (is_admin()) {
			add_action('admin_init', [__CLASS__, 'maybe_upgrade_db']);
		}

		// Get all declared classes.
		$classes = get_declared_classes();

		// Iterate over each class.
		foreach ( $classes as $class ) {

			// Check if the class is in the Logify_WP namespace. Ignore the Main class (this class).
			if ( $class !== 'Logify_WP\\Main' && str_starts_with( $class, 'Logify_WP\\' ) ) {

				// Use reflection to check for the init method.
				$reflection = new ReflectionClass( $class );

				if ( $reflection->hasMethod( 'init' ) ) {
					$method = $reflection->getMethod( 'init' );

					// Check if the init method is static and not abstract.
					if ( $method->isStatic() && ! $method->isAbstract() ) {
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
		// Force debug configuration
		define('WP_DEBUG', true);
		define('WP_DEBUG_LOG', true);
		define('WP_DEBUG_DISPLAY', false);
		@ini_set('display_errors', 0);

		// Create the database tables used by the plugin.
		Database::create_all_tables();
		update_option('logify_wp_db_version', self::DB_VERSION);
	}

    /**
     * Run on plugin update to check if a database upgrade is needed.
     */
    public static function maybe_upgrade_db($upgrader_object = null, $options = null) {
        $installed_version = get_option('logify_wp_db_version', '1.0');

        if (version_compare($installed_version, self::DB_VERSION, '<')) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log("Updating database from version $installed_version to " . self::DB_VERSION); // Debugging log
            Database::create_all_tables();
            update_option('logify_wp_db_version', self::DB_VERSION);
        }
    }	

	/**
	 * Run on deactivation.
	 */
	public static function deactivate() {
		
		Error_Tracker::stop_all_scheduled_actions();
		wp_clear_scheduled_hook( 'logify_wp_cleanup' );
	}

	/**
	 * Run on uninstallation.
	 */
	public static function uninstall() {
		// Drop the tables if the option is set to do so.
		if ( Plugin_Settings::get_delete_on_uninstall() ) {
			Database::drop_all_tables();
		}

		// Delete settings.
		Plugin_Settings::delete_all();
	}

	/**
	 * Create plugin action links and attach to existing array.
	 *
	 * @param array $links Existing links.
	 * @return array The modified array of links.
	 */
	public static function add_action_links( array $links ) {
		// Link to settings.
		$settings_page_link = '<a href="' . admin_url( 'admin.php?page=logify-wp-settings' ) . '">' . __( 'Settings', 'logify-wp' ) . '</a>';
		array_unshift( $links, $settings_page_link );

		// Link to view the log.
		$log_page_link = '<a href="' . admin_url( 'admin.php?page=logify-wp' ) . '">' . __( 'View log', 'logify-wp' ) . '</a>';
		array_unshift( $links, $log_page_link );

		// Link to php_error.
		$php_error_page_link = '<a href="' . admin_url( 'admin.php?page=logify-wp-php-error-log' ) . '">' . __( 'View PHP error', 'logify-wp' ) . '</a>';
		array_unshift( $links, $php_error_page_link );

		return $links;
	}
}
