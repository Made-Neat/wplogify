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
    private static $errors_capture_period; // Period for which errors are captured

    /**
     * Initializes the error tracker.
     */
    public static function init()
    {
        self::$selected_error_types = get_option('logify_wp_php_error_types', []); // Get the selected error types from options
        self::$errors_capture_period = get_option('logify_wp_keep_period_errors', []); // Get the error capture period from options

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
        as_unschedule_action('logify_wp_process_errors');

        // If the start time is not set, set it now
        if (self::$start_time === null) {
            self::$start_time = time();
            $start_time = self::$start_time;
        }

        // Schedule a recurring action every 10 seconds to process errors
        as_schedule_recurring_action(time(), 10, 'logify_wp_process_errors', [$start_time], 'logify-wp');
    }

    /**
     * Stop all scheduled actions from the Action Scheduler.
     */
    public static function stop_all_scheduled_actions()
    {
        // Unschedule all actions
        as_unschedule_all_actions();
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
    public static function process_new_errors($start_time)
    {
        self::$log_file = WP_CONTENT_DIR . "/debug.log"; // Define the log file

        // Initialize error repository
        $error_repo = new \Logify_WP\Error_Repository();

        // If the log file does not exist, return empty
        if (!file_exists(self::$log_file)) {
            return [];
        }

        // Get the last read position
        $last_position = self::get_last_position();

        // Open the log file for reading
        $handle = fopen(self::$log_file, "r");
        if (!$handle) {
            return [];
        }

        // Seek to the last read position to avoid reading old errors
        fseek($handle, $last_position);

        // Read new log entries
        $new_logs = [];
        while (($line = fgets($handle)) !== false) {
            // If the line is a PHP error, add it to new logs
            if (self::is_php_error($line)) {
                $new_logs[] = $line;
            }
        }

        // Update last position after reading the new logs
        $last_position = ftell($handle);
        fclose($handle);
        self::update_last_position($last_position);

        // Process each selected error type
        foreach (self::$selected_error_types as $selected_error_type) {
            if ($selected_error_type == 'Fatal Error') {
                // Process Fatal errors
                foreach ($new_logs as $new_log) {
                    if (preg_match('/\[(.*?)\] PHP (Fatal error): (.*?) in (.*?) on line (\d+)/', $new_log, $matches)) {
                        $error_time = date('Y-m-d H:i:s', strtotime($matches[1])); // Convert log time to MySQL format
                        $error_type = $matches[2];
                        $error_msg = $matches[3];
                        // Save the error to the database
                        $success = $error_repo->save((object) [
                            'error_type' => $error_type,
                            'error_content' => $error_msg
                        ]);
                    }
                }
            } elseif ($selected_error_type === "Warnings") {
                // Process Warning errors
                foreach ($new_logs as $new_log) {
                    if (preg_match('/\[(.*?)\] PHP (Warning): (.*?) in (.*?) on line (\d+)/', $new_log, $matches)) {
                        $error_time = date('Y-m-d H:i:s', strtotime($matches[1])); // Convert log time to MySQL format
                        $error_type = $matches[2];
                        $error_msg = $matches[3];
                        // Save the error to the database
                        $success = $error_repo->save((object) [
                            'error_type' => $error_type,
                            'error_content' => $error_msg
                        ]);
                    }
                }
            } else {
                // Process Notice errors
                foreach ($new_logs as $new_log) {
                    if (preg_match('/\[(.*?)\] PHP (Notice): (.*?) in (.*?) on line (\d+)/', $new_log, $matches)) {
                        $error_time = date('Y-m-d H:i:s', strtotime($matches[1])); // Convert log time to MySQL format
                        $error_type = $matches[2];
                        $error_msg = $matches[3];
                        // Save the error to the database
                        $success = $error_repo->save((object) [
                            'error_type' => $error_type,
                            'error_content' => $error_msg
                        ]);
                    }
                }
            }
        }

        // Check if the capture period has passed, then stop the scheduled task
        if (self::$errors_capture_period == "10mins" && (time() - $start_time) > 10 * 60) {
            self::stop_all_scheduled_actions();
            return;
        } elseif (self::$errors_capture_period == "30mins" && (time() - $start_time) > 30 * 60) {
            self::stop_all_scheduled_actions();
            return;
        } elseif (self::$errors_capture_period == "1hour" && (time() - $start_time) > 60 * 60) {
            self::stop_all_scheduled_actions();
            return;
        } 
    }
}
