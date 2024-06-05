<?php
class WP_Logify_Logger {
    public static function init() {
        // Ensure table is created on plugin activation
        self::create_table();
    }

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_logify_activities';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date_time datetime NOT NULL,
            user_id bigint(20) NOT NULL,
            user_role varchar(255) NOT NULL,
            source_ip varchar(100) NOT NULL,
            event varchar(255) NOT NULL,
            object varchar(255) NOT NULL,
            details text NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function log_change($user_id, $action, $details = []) {
        global $wpdb;

        $current_time = current_time('mysql', 1);
        $log_data = [
            'user_id'    => intval($user_id),
            'action'     => sanitize_text_field($action),
            'details'    => maybe_serialize(array_map('sanitize_text_field', $details)),
            'created_at' => sanitize_text_field($current_time)
        ];

        $wpdb->insert("{$wpdb->prefix}logify_logs", $log_data);
    }

    public static function log_event($event, $object, $user_id = null) {
        global $wpdb;

        $user = $user_id ? get_userdata(intval($user_id)) : wp_get_current_user();
        $user_id = $user->ID;
        $user_role = implode(', ', array_map('sanitize_text_field', $user->roles));
        $source_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        $date_time = current_time('mysql', true);

        $wpdb->insert(
            $wpdb->prefix . 'wp_logify_activities',
            [
                'date_time' => sanitize_text_field($date_time),
                'user_id' => intval($user_id),
                'user_role' => $user_role,
                'source_ip' => $source_ip,
                'event' => sanitize_text_field($event),
                'object' => sanitize_text_field($object),
                'details' => maybe_serialize([])
            ]
        );
    }
}
?>
