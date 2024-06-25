<?php
/**
Plugin Name: WP Logify
Plugin URI: https://wplogify.com
Description: WP Logify features advanced tracking to ensure awareness of all changes made to your WordPress website, including who made them and when.
Version: 1.0
Author: Made Neat
Author URI: https://madeneat.com.au
License: GPL2

WP Logify is a plugin that logs all changes made to your WordPress website, including who made them
and when. It features advanced tracking to ensure awareness of all changes made to your WordPress
website.

This plugin was created by Made Neat, a web development agency based in Australia. We specialise in
creating custom WordPress websites and plugins for businesses of all sizes. If you need help with
your WordPress website, please get in touch with us at https://madeneat.com.au.

This plugin is released under the GPL2 license. You are free to use, modify, and distribute this
plugin as you see fit. We hope you find it useful!

@package WP_Logify
 */

namespace WP_Logify;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Include files containing the classes.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cron.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-datetimes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-log-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-posts.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-users.php';

/**
 * Initialize the plugin.
 */
function wp_logify_init() {
	Admin::init();
	API::init();
	Cron::init();
	Logger::init();
	Posts::init();
	Users::init();
}

add_action( 'plugins_loaded', 'wp_logify_init' );

// Set up activation and deactivation hooks.
register_activation_hook( __FILE__, 'wp_logify_activate' );
register_deactivation_hook( __FILE__, 'wp_logify_deactivate' );
register_uninstall_hook( __FILE__, 'wp_logify_uninstall' );

/**
 * Run on activation.
 */
function wp_logify_activate() {
	Logger::create_table();
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
	// Check if the user has opted to delete the database table on uninstallation of the plugin.
	if ( get_option( 'wp_logify_delete_on_uninstall' ) === 'yes' ) {
		global $wpdb;
		$table_name = Logger::get_table_name();
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
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
 * Dump one or more variables into the error log.
 *
 * @param mixed ...$args The variable(s) to dump.
 */
function debug_log( ...$args ) {
	// Ensure there is at least one argument.
	if ( empty( $args ) ) {
		return;
	}

	// Convert each argument to a string representation.
	$strings = array_map(
		function ( $arg ) {
			return is_string( $arg ) ? $arg : var_export( $arg, true );
		},
		$args
	);

	// Join the strings with ': ' separator.
	$debug_string = implode( ', ', array_filter( $strings ) );

	// Log the debug string.
	error_log( $debug_string );
}
