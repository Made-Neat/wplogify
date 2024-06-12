<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly.
}

// Include the logger class.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-logify-logger.php';

// Delete the custom table for logged activities
global $wpdb;
$table_name = WP_Logify_Logger::get_table_name();

$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Delete the plugin options
delete_option( 'wp_logify_api_key' );
delete_option( 'wp_logify_delete_on_uninstall' );
