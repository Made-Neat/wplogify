<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit; // Exit if accessed directly.
}

// Delete the custom table for logged activities
global $wpdb;
$table_name = $wpdb->prefix . 'wp_logify_activities';

$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Delete the plugin options
delete_option('wp_logify_api_key');
delete_option('wp_logify_delete_on_uninstall');
?>
