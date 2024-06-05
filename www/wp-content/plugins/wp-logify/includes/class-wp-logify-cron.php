<?php
class WP_Logify_Cron {
    public static function init() {
        add_action('wp_logify_cleanup', [__CLASS__, 'cleanup_old_records']);
        if (!wp_next_scheduled('wp_logify_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_logify_cleanup');
        }
    }

    public static function cleanup_old_records() {
        global $wpdb;
        $days = absint(get_option('wp_logify_keep_days', 30));
        $table_name = $wpdb->prefix . 'wp_logify_activities';
        $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE date_time < NOW() - INTERVAL %d DAY", $days));
    }
}
