<?php
/**
Plugin Name: WP Logify
Plugin URI: https://wplogify.com
Description: WP Logify features advanced tracking to ensure you are aware of all changes made to your WordPress website.
Version: 1.0
Author: Made Neat
Author URI: https://madeneat.com.au
License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Core classes.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-cron.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-datetime.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-log-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-logger.php';

// Event tracking classes.
require_once plugin_dir_path( __FILE__ ) . 'includes/event-tracking/class-wp-logify-posts.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/event-tracking/class-wp-logify-users.php';

/**
 * Initialize the plugin.
 */
function wp_logify_init() {
	// Initialise core classes.
	WP_Logify_API::init();
	WP_Logify_Admin::init();
	WP_Logify_Logger::init();
	WP_Logify_Cron::init();

	// Initialise event logging classes.
	WP_Logify_Users::init();
	WP_Logify_Posts::init();
	// WP_Logify_Tracker::init();
}

add_action( 'plugins_loaded', 'wp_logify_init' );

// Set up activation and deactivation hooks
register_activation_hook( __FILE__, 'wp_logify_activate' );
register_deactivation_hook( __FILE__, 'wp_logify_deactivate' );
register_uninstall_hook( __FILE__, 'wp_logify_uninstall' );

/**
 * Run on activation.
 */
function wp_logify_activate() {
	WP_Logify_Logger::create_table();
}

/**
 * Run on deactivation.
 */
function wp_logify_deactivate() {
	wp_clear_scheduled_hook( 'wp_logify_cleanup_logs' );
}

/**
 * Run on uninstallation.
 */
function wp_logify_uninstall() {
	// Check if the user has opted to delete the database.
	if ( get_option( 'wp_logify_delete_on_uninstall' ) === 'yes' ) {
		global $wpdb;
		$table_name = WP_Logify_Logger::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}

	// Delete the plugin options.
	delete_option( 'wp_logify_api_key' );
	delete_option( 'wp_logify_delete_on_uninstall' );
	delete_option( 'wp_logify_keep_forever' );
	delete_option( 'wp_logify_keep_period_quantity' );
	delete_option( 'wp_logify_keep_period_units' );
}

// Add links to the plugin action links.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wp_logify_action_links' );

/**
 * Create plugin action links and attach to existing array.
 *
 * @param array $links Existing links.
 */
function wp_logify_action_links( array $links ) {
	// Link to settings.
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wp-logify-settings' ) . '">' . __( 'Settings', 'wp-logify' ) . '</a>';
	array_unshift( $links, $settings_link );

	// Link to view the log.
	$view_log_link = '<a href="' . admin_url( 'admin.php?page=wp-logify' ) . '">' . __( 'View log', 'wp-logify' ) . '</a>';
	array_unshift( $links, $view_log_link );

	return $links;
}

/**
 * Define the sanitize callback function.
 */
function wp_logify_sanitize_roles( $roles ) {
	$valid_roles = array_keys( wp_roles()->roles );
	return array_filter(
		$roles,
		function ( $role ) use ( $valid_roles ) {
			return in_array( $role, $valid_roles );
		}
	);
}

/**
 * Dump a variable into the error log.
 *
 * @param string $label The label to prepend to the output.
 * @param mixed  $var The variable to dump.
 */
function debug_log( string $label, mixed $var ) {
	$output  = empty( $label ) ? '' : $label . ': ';
	$output .= is_string( $var ) ? $var : var_export( $var, true );
	error_log( $output );
}
