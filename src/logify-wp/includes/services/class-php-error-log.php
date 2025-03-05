<?php
/**
 * Contains the PHP_Error_Log class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class PHP_Error_Log
 * Handles fetching and displaying PHP errors in the admin panel.
 */
class PHP_Error_Log
{
    /**
     * Initialize the error log page by adding an AJAX action.
     */
    public static function init(): void
    {
        add_action('wp_ajax_logify_wp_fetch_errors', [__CLASS__, 'fetch_errors']);
    }

    /**
     * Get the number of items per page for pagination.
     *
     * @return int Number of items per page.
     */
    public static function get_items_per_page(): int
    {
        $page_length = (int) get_user_option('logify_wp_errors_per_page', get_current_user_id());
        return $page_length ?: 20;
    }

    /**
     * Fetch errors from the database and return them as a JSON response.
     */
    public static function fetch_errors(): void
    {
        // Verify the AJAX request nonce for security.
        check_ajax_referer('logify-wp-php-error-log-page', 'security');

        // Check if the user has the required permissions.
        if (!Access_Control::can_access_log_page()) {
            wp_send_json_error(['message' => esc_html__('You are not allowed to access this data.', 'logify')], 403);
        }

        global $wpdb;

        // Define table names.
        $errors_table_name = Error_Repository::get_table_name();
        $users_table_name  = $wpdb->users;

        // Get pagination settings.
        $page_length = self::get_items_per_page();

        // Define the database columns to retrieve.
        $columns = ['error_id', 'error_type', 'error_content', 'created_at'];

        // Get pagination start value from request.
        $start = isset($_POST['start']) ? (int) $_POST['start'] : 0;
        if ($start < 0) {
            $start = 0;
        }

        // Determine the column to order by.
        $order_by_columns = ['error_id'];
        if (isset($_POST['order'][0]['column'])) {
            $column_number = (int) $_POST['order'][0]['column'];
            if (array_key_exists($column_number, $columns)) {
                $order_by_columns = [$columns[$column_number]];
            }
        }

        // Get the sorting direction (ASC or DESC), default to DESC.
        $order_by_direction = isset($_POST['order'][0]['dir'])
            ? strtoupper(sanitize_text_field(wp_unslash($_POST['order'][0]['dir'])))
            : 'DESC';
        if (!in_array($order_by_direction, ['ASC', 'DESC'], true)) {
            $order_by_direction = 'DESC';
        }

        // Retrieve total number of records.
        $num_total_records = (int) $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM %i', $errors_table_name)
        );

        // Define SQL queries for selecting error records.
        $select_query = 'SELECT * FROM %i e LEFT JOIN %i u ON e.error_id = u.ID LIMIT %d OFFSET %d';
        $query_args = [$errors_table_name, $users_table_name, $page_length, $start];

        // Fetch error records from the database.
        $recordset = $wpdb->get_results($wpdb->prepare($select_query, $query_args), ARRAY_A);

        // Prepare data for JSON response.
        $data = [];
        foreach ($recordset as $record) {
            $error = Error_Repository::load($record['error_id']);
            $data[] = [
                'error_id'      => $error->error_id,
                'error_type'    => $error->error_type,
                'error_content' => $error->error_content,
            ];
        }

        // Get the draw counter from DataTables request.
        $draw = isset($_POST['draw']) ? (int) wp_unslash($_POST['draw']) : 0;

        // Send JSON response with data.
        wp_send_json([
            'draw'            => $draw,
            'recordsTotal'    => $num_total_records,
            'recordsFiltered' => $num_total_records,
            'data'            => $data,
        ]);
    }

    /**
     * Display the PHP error log page in the WordPress admin panel.
     */
    public static function display_php_error_log_page(): void
    {
        // Ensure the user has permission to view the log page.
        if (!Access_Control::can_access_log_page()) {
            wp_die(esc_html__('Sorry, you are not allowed to access this page.', 'logify'), 403);
        }

        // Retrieve necessary data for rendering the log page.
        $users = Event_Repository::get_users();
        $roles = Event_Repository::get_roles();

        // Include the log page template file.
        include LOGIFY_WP_PLUGIN_DIR . 'templates/php-error-log-page.php';
    }
}
