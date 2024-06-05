<?php
class WP_Logify_Basic {
    public static function init() {
        // Basic tracking functionalities
        add_action('save_post', [__CLASS__, 'track_post_changes']);
        add_action('wp_login', [__CLASS__, 'track_login'], 10, 2);
        // Add other basic hooks as needed
    }

    public static function track_post_changes($post_id) {
        $post = get_post($post_id);
        $event = (wp_is_post_revision($post_id)) ? 'Post Updated' : 'Post Added';
        WP_Logify_Logger::log_event($event, "Post ID: $post_id");
    }

    public static function track_login($user_login, $user) {
        WP_Logify_Logger::log_event('User Logged In', "User: $user_login", $user->ID);
    }
}

