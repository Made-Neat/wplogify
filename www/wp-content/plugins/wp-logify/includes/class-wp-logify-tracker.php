<?php
class WP_Logify_Tracker {
    public static function init() {
        // Hook into various WordPress actions
        // User-related hooks
        add_action('wp_login', [__CLASS__, 'track_login'], 10, 2);
        add_action('wp_logout', [__CLASS__, 'track_logout']);
        add_action('wp_login_failed', [__CLASS__, 'track_failed_login']);
        add_action('profile_update', [__CLASS__, 'track_profile_changes']);
        add_action('user_register', [__CLASS__, 'track_user_registration']);
        add_action('delete_user', [__CLASS__, 'track_user_deletion']);

        // Post-related hooks
        add_action('save_post', [__CLASS__, 'track_post_changes']);
        add_action('delete_post', [__CLASS__, 'track_post_deletion']);

        // Comment-related hooks
        add_action('wp_insert_comment', [__CLASS__, 'track_comment_changes'], 10, 2);
        add_action('edit_comment', [__CLASS__, 'track_comment_changes']);
        add_action('delete_comment', [__CLASS__, 'track_comment_deletion']);

        // Attachment-related hooks
        add_action('add_attachment', [__CLASS__, 'track_attachment_changes']);
        add_action('edit_attachment', [__CLASS__, 'track_attachment_changes']);
        add_action('delete_attachment', [__CLASS__, 'track_attachment_deletion']);

        // Taxonomy-related hooks
        add_action('created_term', [__CLASS__, 'track_taxonomy_changes'], 10, 3);
        add_action('edited_term', [__CLASS__, 'track_taxonomy_changes'], 10, 3);
        add_action('delete_term', [__CLASS__, 'track_taxonomy_deletion'], 10, 3);

        // Plugin-related hooks
        add_action('activated_plugin', [__CLASS__, 'track_plugin_changes'], 10, 2);
        add_action('deactivated_plugin', [__CLASS__, 'track_plugin_deactivation'], 10, 2);
        add_action('upgrader_process_complete', [__CLASS__, 'track_plugin_upgrade'], 10, 2);

        // Menu-related hooks
        add_action('wp_update_nav_menu', [__CLASS__, 'track_menu_changes']);

        // Option-related hooks
        add_action('updated_option', [__CLASS__, 'track_option_changes'], 10, 3);

        // Privacy-related hooks
        add_action('create_privacy_policy_content', [__CLASS__, 'track_privacy_page_creation']);

        // Data Export Request-related hooks
        add_action('user_request_action', [__CLASS__, 'track_data_export_request'], 10, 3);

        // Data Erasure Request-related hooks
        add_action('wp_privacy_personal_data_eraser_fulfillment', [__CLASS__, 'track_data_erasure_request'], 10, 2);

        // Theme-related hooks
        add_action('switch_theme', [__CLASS__, 'track_theme_changes']);

        // Customizer-related hooks
        add_action('customize_save_after', [__CLASS__, 'track_customizer_changes']);

        // Widget-related hooks
        add_action('sidebar_admin_setup', [__CLASS__, 'track_widget_changes']);

        // Core update-related hooks
        add_action('upgrader_process_complete', [__CLASS__, 'track_core_update'], 10, 2);
    }

    // User Login
    public static function track_login($user_login, $user) {
        $details = [
            'user_login' => $user_login,
            'user_id' => $user->ID,
        ];
        self::log_event('User Logged In', $details, $user->ID);
    }

    public static function track_logout() {
        $user = wp_get_current_user();
        $details = [
            'user_login' => $user->user_login,
            'user_id' => $user->ID,
        ];
        self::log_event('User Logged Out', $details, $user->ID);
    }

    public static function track_failed_login($username) {
        $details = [
            'username' => $username,
            'source_ip' => sanitize_text_field($_SERVER['REMOTE_ADDR']),
        ];
        self::log_event('Failed Login Attempt', $details);
    }

    // User Profile Changes
    public static function track_profile_changes($user_id) {
        $user = get_userdata($user_id);
        $details = [
            'user_login' => $user->user_login,
            'user_id' => $user_id,
        ];
        self::log_event('Profile Updated', $details, $user_id);
    }

    // User Registration
    public static function track_user_registration($user_id) {
        $user = get_userdata($user_id);
        $details = [
            'user_login' => $user->user_login,
            'user_id' => $user_id,
        ];
        self::log_event('User Registered', $details, $user_id);
    }

    // User Deletion
    public static function track_user_deletion($user_id) {
        $user = get_userdata($user_id);
        $details = [
            'user_login' => $user->user_login,
            'user_id' => $user_id,
        ];
        self::log_event('User Deleted', $details, $user_id);
    }

    // Post Changes
    public static function track_post_changes($post_id) {
        $post = get_post($post_id);
        $user_id = get_current_user_id();
        $details = [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
        ];
        self::log_event('Post Updated', $details, $user_id);
    }

    public static function track_post_deletion($post_id) {
        $post = get_post($post_id);
        $user_id = get_current_user_id();
        $details = [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
        ];
        self::log_event('Post Deleted', $details, $user_id);
    }

    // Comment Changes
    public static function track_comment_changes($comment_ID, $comment) {
        $user_id = get_current_user_id();
        $details = [
            'comment_id' => $comment_ID,
            'comment_content' => $comment->comment_content,
        ];
        self::log_event('Comment Updated', $details, $user_id);
    }

    public static function track_comment_deletion($comment_ID) {
        $user_id = get_current_user_id();
        $details = [
            'comment_id' => $comment_ID,
        ];
        self::log_event('Comment Deleted', $details, $user_id);
    }

    // Attachment Changes
    public static function track_attachment_changes($post_id) {
        $post = get_post($post_id);
        $user_id = get_current_user_id();
        $details = [
            'attachment_id' => $post_id,
            'attachment_title' => $post->post_title,
        ];
        self::log_event('Attachment Updated', $details, $user_id);
    }

    public static function track_attachment_deletion($post_id) {
        $post = get_post($post_id);
        $user_id = get_current_user_id();
        $details = [
            'attachment_id' => $post_id,
            'attachment_title' => $post->post_title,
        ];
        self::log_event('Attachment Deleted', $details, $user_id);
    }

    // Taxonomy Changes
    public static function track_taxonomy_changes($term_id, $tt_id, $taxonomy) {
        $term = get_term($term_id);
        $user_id = get_current_user_id();
        $details = [
            'term_id' => $term_id,
            'taxonomy' => $taxonomy,
            'term_name' => $term->name,
        ];
        self::log_event('Term Updated', $details, $user_id);
    }

    public static function track_taxonomy_deletion($term_id, $tt_id, $taxonomy) {
        $term = get_term($term_id);
        $user_id = get_current_user_id();
        $details = [
            'term_id' => $term_id,
            'taxonomy' => $taxonomy,
            'term_name' => $term->name,
        ];
        self::log_event('Term Deleted', $details, $user_id);
    }

    // Plugin Changes
    public static function track_plugin_changes($plugin, $network_wide) {
        $user_id = get_current_user_id();
        $details = [
            'plugin' => $plugin,
            'network_wide' => $network_wide,
        ];
        self::log_event('Plugin Activated', $details, $user_id);
    }

    public static function track_plugin_deactivation($plugin, $network_wide) {
        $user_id = get_current_user_id();
        $details = [
            'plugin' => $plugin,
            'network_wide' => $network_wide,
        ];
        self::log_event('Plugin Deactivated', $details, $user_id);
    }

    public static function track_plugin_upgrade($upgrader_object, $options) {
        $user_id = get_current_user_id();
        $details = [
            'action' => $options['action'],
            'type' => $options['type'],
            'plugins' => $options['plugins'],
        ];
        self::log_event('Plugin Upgraded', $details, $user_id);
    }

    // Menu Changes
    public static function track_menu_changes($nav_menu_selected_id) {
        $user_id = get_current_user_id();
        $details = [
            'menu_id' => $nav_menu_selected_id,
        ];
        self::log_event('Menu Updated', $details, $user_id);
    }

    // Option Changes
    public static function track_option_changes($option_name, $old_value, $value) {
        $user_id = get_current_user_id();
        $details = [
            'option_name' => $option_name,
            'old_value' => maybe_serialize($old_value),
            'new_value' => maybe_serialize($value),
        ];
        self::log_event('Option Updated', $details, $user_id);
    }

    // Privacy Page Management
    public static function track_privacy_page_creation($post_id) {
        $user_id = get_current_user_id();
        $details = [
            'post_id' => $post_id,
        ];
        self::log_event('Privacy Page Created', $details, $user_id);
    }

    // Data Export Requests
    public static function track_data_export_request($export_id, $email, $data) {
        $user_id = get_current_user_id();
        $details = [
            'export_id' => $export_id,
            'email' => $email,
        ];
        self::log_event('Data Export Requested', $details, $user_id);
    }

    // User Data Erasure Requests
    public static function track_data_erasure_request($email_address, $result) {
        $user_id = get_current_user_id();
        $details = [
            'email_address' => $email_address,
        ];
        self::log_event('Data Erasure Requested', $details, $user_id);
    }

    // Theme Changes
    public static function track_theme_changes($new_theme) {
        $user_id = get_current_user_id();
        $details = [
            'new_theme' => $new_theme,
        ];
        self::log_event('Theme Changed', $details, $user_id);
    }

    // Customizer Changes
    public static function track_customizer_changes() {
        $user_id = get_current_user_id();
        $details = [
            'customizer' => 'customizer',
        ];
        self::log_event('Customizer Changes Saved', $details, $user_id);
    }

    // Widget Changes
    public static function track_widget_changes() {
        $user_id = get_current_user_id();
        // You can add specific widget details here if needed
        self::log_event('Widget Updated', ['widget' => 'updated'], $user_id);
    }

    // Core Update
    public static function track_core_update($upgrader, $options) {
        $user_id = get_current_user_id();
        $details = [
            'action' => $options['action'],
            'type' => $options['type'],
        ];
        self::log_event('WordPress Core Updated', $details, $user_id);
    }

    private static function log_event($event, $details, $user_id = null) {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $logger = new WP_Logify_Logger();
        $logger->log_change($user_id, $event, $details);
    }
}

// Initialize the tracker
WP_Logify_Tracker::init();
