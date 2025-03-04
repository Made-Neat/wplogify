<?php

use ActionScheduler_Action;

class Log_Error_Background_Process {
    
    public function __construct() {
        // Hook into Action Scheduler
        add_action('logify_wp_process_errors', array(__CLASS__, 'process_new_errors'),10,2);
    }

    // Method to process new errors from debug.log and save them to the database
    public function process_new_errors() {
        $log_file_path = WP_CONTENT_DIR . '/debug.log';

        error_log('schedule started');

        // Read the content of the log file
        if (file_exists($log_file_path)) {
            $log_content = file_get_contents($log_file_path);

            // Parse the log for new errors (you can customize this parsing logic)
            $new_errors = $this->parse_errors($log_content);

            // Insert new errors into the database
            foreach ($new_errors as $error) {
                $this->insert_error_to_db($error);
            }
        }
    }

    // Parse errors from the log content
    private function parse_errors($log_content) {
        $new_errors = [];

        // Example: Regex to capture the error pattern
        // You can adjust the regex to capture the format of the log entries
        preg_match_all('/\[(.*?)\] PHP Error: (.*?) in (.*?) on line (\d+)/', $log_content, $matches);

        // Prepare errors
        foreach ($matches[0] as $key => $match) {
            $new_errors[] = [
                'error_type' => 'PHP Error',
                'error_content' => $matches[2][$key],
                'error_file' => $matches[3][$key],
                'error_line' => $matches[4][$key],
                'created_at' => current_time('mysql'),
            ];
        }

        return $new_errors;
    }

    // Insert errors into the database
    private function insert_error_to_db($error_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'logify_errors';  // Replace with your table name

        $wpdb->insert($table_name, [
            'error_type' => $error_data['error_type'],
            'error_content' => $error_data['error_content'],
            'error_file' => $error_data['error_file'],
            'error_line' => $error_data['error_line'],
            'created_at' => $error_data['created_at'],
        ]);
    }

    // Schedule the background task
    public function schedule_background_task() {
        if (!as_next_scheduled_action('logify_wp_process_errors')) {
            as_schedule_recurring_action(time(), 5, 'logify_wp_process_errors'); // Every 5 sec
        }
    }
}

// Initialize and schedule the process
$error_background_process = new Log_Error_Background_Process();
$error_background_process->schedule_background_task();
