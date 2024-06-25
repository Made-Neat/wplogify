<?php
/**
 * Uninstall script for the plugin.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Include the logger class.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-logger.php';

// Delete the custom table for logged activities.
global $wpdb;
$table_name = Logger::get_table_name();

$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
