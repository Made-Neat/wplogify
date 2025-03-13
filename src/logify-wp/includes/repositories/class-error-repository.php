<?php

namespace Logify_WP;

use InvalidArgumentException;
use RuntimeException;

/**
 * Error Repository Class
 * Handles CRUD operations for the logify_errors table.
 */
class Error_Repository extends Repository
{
    /**
     * Table name for the errors.
     * @var string
     */
    protected $table_name;

    /**
     * Constructor: Set the table name and initialize parent.
     */
    public function __construct()
    {
        global $wpdb; // Access the global WordPress database object
        $this->wpdb = $wpdb; // Store the database object in the class
        $this->table_name = $wpdb->prefix . 'logify_errors'; // Define the table name with prefix
    }

    /**
     * Get the table name for the repository.
     *
     * @return string
     */
    public static function get_table_name(): string
    {
        global $wpdb; // Access the global database object
        return $wpdb->prefix . 'logify_errors'; // Return the table name with prefix
    }

    /**
     * Create a new table for errors.
     *
     * @return void
     */
    public static function create_table(): void
    {
        global $wpdb; // Access the global database object
        $table_name = self::get_table_name(); // Get the table name
        $charset_collate = $wpdb->get_charset_collate(); // Get the database charset

        // SQL query to create the errors table
        $sql = "CREATE TABLE {$table_name} (
            error_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            error_type VARCHAR(255) NOT NULL,
            error_content TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (error_id)
        ) {$charset_collate} ENGINE=InnoDB;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php'; // Include WordPress upgrade functions
        dbDelta($sql); // Execute the table creation query
    }

    /**
     * Convert database record to PHP_Error object.
     *
     * @param array $record The database record as an associative array.
     * @return PHP_Error The new PHP_Error object.
     */
    public static function record_to_object(array $record): PHP_Error
    {
        $error = new PHP_Error(); // Create a new PHP_Error object
        $error->error_id = (int) $record['error_id']; // Assign error ID
        $error->error_type = $record['error_type']; // Assign error type
        $error->error_content = $record['error_content']; // Assign error content
        return $error; // Return the object
    }

    /**
     * Load an error by ID.
     *
     * @param int $id Error ID.
     * @return object|null Error data or null if not found.
     */
    public static function load(int $id): ?object
    {
        global $wpdb; // Access the global database object
        
        // Fetch the error record from the database
        $record = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE error_id = %d', self::get_table_name(), $id),
            ARRAY_A
        );
        
        return $record ? self::record_to_object($record) : null; // Convert record to object if found
    }

    /**
     * Save a new error entry.
     *
     * @param object $data The error object to save.
     * @return bool True on success, false otherwise.
     * @throws InvalidArgumentException
     */
    public static function save(object $data): bool
    {
        global $wpdb; // Access the global database object

        // Validate error content
        if (empty($data->error_content) || !is_string($data->error_content)) {
            throw new InvalidArgumentException('Error content must be a non-empty string.');
        }

        // Insert error data into the database
        return (bool) $wpdb->insert(
            self::get_table_name(),
            [
                'error_type' => $data->error_type,
                'error_content' => $data->error_content,
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Drop the errors table.
     *
     * @return void
     */
    public static function drop_table(): void
    {
        global $wpdb; // Access the global database object
        $wpdb->query("DROP TABLE IF EXISTS " . self::get_table_name()); // Execute drop table query
    }

    /**
     * Truncate the errors table (delete all records).
     *
     * @return void
     */
    public static function truncate_table(): void
    {
        global $wpdb; // Access the global database object
        $wpdb->query("TRUNCATE TABLE " . self::get_table_name()); // Execute truncate table query
    }

    /**
     * Delete an error by its ID.
     *
     * @param int $id Error ID.
     * @return bool True on success, false on failure.
     * @throws InvalidArgumentException|RuntimeException
     */
    public static function delete(int $id): bool
    {
        global $wpdb; // Access the global database object

        if ($id <= 0) { // Validate ID
            throw new InvalidArgumentException('Invalid error ID. ID must be a positive integer.');
        }

        // Execute delete query
        $deleted = $wpdb->delete(self::get_table_name(), ['error_id' => $id], ['%d']);

        if ($deleted === false) { // Handle deletion failure
            throw new RuntimeException("Failed to delete error with ID: {$id}");
        }

        return $deleted > 0; // Return true if rows were affected
    }

    /**
     * Helper function to check if a table exists in the database.
     *
     * @param string $table_name The table name.
     * @return bool True if table exists, false otherwise.
     */
    private static function table_exists(string $table_name): bool
    {
        global $wpdb; // Access the global database object
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name; // Check table existence
    }
}
