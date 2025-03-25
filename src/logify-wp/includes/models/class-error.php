<?php

/**
 * Class PHP_Error
 * Represents an error entity for the logify_errors table.
 */
namespace Logify_WP;

class PHP_Error {
    /**
     * @var int|null $error_id Primary Key: Unique ID for the error.
     */
    public $error_id;

    /**
     * @var string $error_type Type of the error (e.g., Fatal, Warning, Notice).
     */
    public $error_type;

    /**
     * @var string $error_content Content/details of the error.
     */
    public $error_content;

    /**
     * @var string|null $created_at Timestamp of when the error was recorded.
     */
    public $created_at;

    /**
     * Constructor to initialize the PHP_Error object.
     *
     * @param array $data Associative array to initialize error properties.
     */
    public function __construct($data = []) {
        // Assign the error ID if provided, ensuring it's an integer.
        $this->error_id = isset($data['error_id']) ? (int) $data['error_id'] : null;
        
        // Sanitize and assign the error type to prevent malicious input.
        $this->error_type = isset($data['error_type']) ? sanitize_textarea_field($data['error_type']) : '';
        
        // Sanitize and assign the error content for security.
        $this->error_content = isset($data['error_content']) ? sanitize_textarea_field($data['error_content']) : '';
    }

    /**
     * Convert the PHP_Error object into an associative array.
     *
     * @return array The object properties as an associative array.
     */
    public function to_array(): array {
        return [
            'error_id'      => $this->error_id,       // Unique error ID.
            'error_type'    => $this->error_type,     // Type of error.
            'error_content' => $this->error_content,  // Content/details of the error.
            'created_at'    => $this->created_at,     // Timestamp when the error was recorded.
        ];
    }

    /**
     * Validate the error properties before saving.
     *
     * @return bool|string Returns true if valid, or an error message string if invalid.
     */
    public function validate() {
        // Check if the error type is provided.
        if (empty($this->error_type)) {
            return __('Error type is required.', 'logify-wp');
        }
        
        // Check if the error content is provided.
        if (empty($this->error_content)) {
            return __('Error content is required.', 'logify-wp');
        }
        
        return true; // Validation passed.
    }
}
