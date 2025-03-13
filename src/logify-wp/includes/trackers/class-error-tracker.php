<?php
/**
 * Contains the Core_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DateTime;
use Error_Repository;

class Error_Tracker
{
    private static $start_time = null; // Start time for the error capturing process

    private static $log_file = WP_CONTENT_DIR . "/debug.log"; // Path to the debug log file
    private static $last_position_file = WP_CONTENT_DIR . "/last_log_position.txt"; // Path to the last log position file
    private static $selected_error_types; // Selected error types to track
    private static $errors_capture_period = ""; // Period for which errors are captured

    /**
     * Initializes the error tracker.
     */
    public static function init()
    {
        self::$selected_error_types = get_option("logify_wp_php_error_types", []);
        self::$errors_capture_period = get_option("logify_wp_keep_period_errors", []);
        // Hook to schedule background task
        add_action('schedule_process', array(__CLASS__, 'schedule_background_task'));

        // Hook to process errors
        add_action('logify_wp_process_errors', array(__CLASS__, 'process_new_errors'));
    }

    /**
     * Start the background task and set the start time.
     */
    public static function schedule_background_task()
    {
        // Unschedule previous actions to avoid duplication
        as_unschedule_all_actions('logify_wp_process_errors', [], 'logify-wp');


        if (count(self::$selected_error_types) > 0) {
            // Schedule a recurring action every 10 seconds to process errors
            as_schedule_recurring_action(time()+10, 10, 'logify_wp_process_errors', [], 'logify-wp');
        } else {
            return;
        }
    }

    /**
     * Stop all scheduled actions from the Action Scheduler.
     */
    public static function stop_all_scheduled_actions()
    {
        // Unschedule all instances of the scheduled action
        as_unschedule_all_actions('logify_wp_process_errors', [], 'logify-wp');

    }

    /**
     * Get the last position from the log file.
     *
     * @return int
     */
    private static function get_last_position()
    {
        // Check if the last position file exists and return its content
        $last_position_file = WP_CONTENT_DIR . "/last_log_position.txt";
        if (!file_exists($last_position_file)) {
            file_put_contents($last_position_file, "0");
        }
        return (int) file_get_contents($last_position_file);
    }

    /**
     * Update the last read position to a file.
     *
     * @param int $position
     */
    private static function update_last_position($position)
    {
        // Write the updated position to the last position file
        $last_position_file = WP_CONTENT_DIR . "/last_log_position.txt";
        file_put_contents($last_position_file, $position);
    }

    /**
     * Check if a log entry is a PHP error.
     *
     * @param string $log_entry
     * @return bool
     */
    private static function is_php_error($log_entry)
    {
        // Match the PHP error pattern
        return preg_match('/\[(.*?)\] (PHP (Fatal error|Parse error|Warning|Notice|Error)): (.*) in (.*?) on line (\d+)/', $log_entry);
    }

    /**
     * Process new errors from the log file.
     *
     * @param int $start_time
     */
    public static function process_new_errors()
    {
        global $wp_filesystem;

        // Initialize WP_Filesystem
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        self::$log_file = WP_CONTENT_DIR . "/debug.log"; // Define the log file

        // Initialize error repository
        $error_repo = new \Logify_WP\Error_Repository();

        // If the log file does not exist, return empty
        if (!$wp_filesystem->exists(self::$log_file)) {
            return [];
        }

        // Get the last read position
        $last_position = self::get_last_position();

        // Read the log file content
        $log_content = $wp_filesystem->get_contents(self::$log_file);
        if ($log_content === false) {
            return [];
        }

        // Extract new log entries
        $new_logs = explode("\n", substr($log_content, $last_position));

        // Update last position after reading the new logs
        self::update_last_position(strlen($log_content));

        // Stop the scheduled task if the capture period has passed
        $elapsed_time = time() - get_option('logify_wp_capture_start_time');

        if (
            (self::$errors_capture_period === "10mins" && $elapsed_time > 600) ||
            (self::$errors_capture_period === "30mins" && $elapsed_time > 1800) ||
            (self::$errors_capture_period === "1hour" && $elapsed_time > 3600)
        ) {
            self::stop_all_scheduled_actions();

        } else {
            // Process each selected error type
            foreach (self::$selected_error_types as $selected_error_type) {
                foreach ($new_logs as $new_log) {
                    if (
                        $selected_error_type === 'Fatal_Errors' &&
                        preg_match('/\[(.*?)\] PHP Fatal error: (.+?) in (.+?) on line (\d+)/', $new_log, $matches)
                    ) {

                        $error_repo->save((object) [
                            'error_type' => "Fatal Error",
                            'error_content' => esc_html($matches[2])
                        ]);

                    } elseif (
                        $selected_error_type === 'Warnings' &&
                        preg_match('/\[(.*?)\] PHP Warning: (.+?) in (.+?) on line (\d+)/', $new_log, $matches)
                    ) {
                        
                        $error_repo->save((object) [
                            'error_type' => "Warning",
                            'error_content' => esc_html($matches[2])
                        ]);
                        
                    } elseif (
                        $selected_error_type === 'Notices' &&
                        preg_match('/\[(.*?)\] PHP Notice: (.+?) in (.+?) on line (\d+)/', $new_log, $matches)
                    ) {

                        $error_repo->save((object) [
                            'error_type' => "Notice",
                            'error_content' => esc_html($matches[2])
                        ]);
                    }
                }
            }
        }
    }
}
