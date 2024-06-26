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

// Include class files.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin.php';
// require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cron.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-datetimes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-log-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-posts.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-users.php';

// Initialisation hook.
add_action( 'plugins_loaded', array( 'WP_Logify\Plugin', 'init' ) );

// Activation hook.
register_activation_hook( __FILE__, array( 'WP_Logify\Plugin', 'activate' ) );

// Deactivation hook.
register_deactivation_hook( __FILE__, array( 'WP_Logify\Plugin', 'deactivate' ) );

// Uninstall hook.
register_uninstall_hook( __FILE__, array( 'WP_Logify\Plugin', 'uninstall' ) );

// Add links to the plugin action links.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'WP_Logify\Plugin', 'add_action_links' ) );

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
