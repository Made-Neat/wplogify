<?php
/*
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

// Include core files
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-basic.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-tracker.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-cron.php';

// Initialize the plugin
function wp_logify_init() {
	WP_Logify_Basic::init();
	WP_Logify_API::init();
	WP_Logify_Admin::init();
	WP_Logify_Logger::init();
	WP_Logify_Tracker::init();
	WP_Logify_Cron::init();
}
add_action( 'plugins_loaded', 'wp_logify_init' );

// Activation and deactivation hooks
register_activation_hook( __FILE__, 'wp_logify_activate' );
register_deactivation_hook( __FILE__, 'wp_logify_deactivate' );
register_uninstall_hook( __FILE__, 'wp_logify_uninstall' );

function wp_logify_activate() {
	WP_Logify_Logger::create_table();
}

function wp_logify_deactivate() {
	wp_clear_scheduled_hook( 'wp_logify_cleanup_logs' );
}

function wp_logify_uninstall() {
	// Check if the user has opted to delete the database
	if ( get_option( 'wp_logify_delete_on_uninstall' ) === 'yes' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wp_logify_activities';
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}
	// Delete the plugin options
	delete_option( 'wp_logify_api_key' );
	delete_option( 'wp_logify_delete_on_uninstall' );
}

// Add "View Log" link to the plugin action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wp_logify_action_links' );

function wp_logify_action_links( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wp-logify-settings' ) . '">' . __( 'Settings', 'wp-logify' ) . '</a>';
	array_unshift( $links, $settings_link );

	$view_log_link = '<a href="' . admin_url( 'admin.php?page=wp-logify' ) . '">' . __( 'View log', 'wp-logify' ) . '</a>';
	array_unshift( $links, $view_log_link );

	return $links;
}

// Define the sanitize callback function
function wp_logify_sanitize_roles( $roles ) {
	$valid_roles = array_keys( wp_roles()->roles );
	return array_filter(
		$roles,
		function ( $role ) use ( $valid_roles ) {
			return in_array( $role, $valid_roles );
		}
	);
}
