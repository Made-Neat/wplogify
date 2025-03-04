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
            error_log("Updating database from version $installed_version to " . self::DB_VERSION); // Debugging log
            Database::create_all_tables();
            update_option('logify_wp_db_version', self::DB_VERSION);
        }
    }	

	/**
	 * Run on deactivation.
	 */
	public static function deactivate() {
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
