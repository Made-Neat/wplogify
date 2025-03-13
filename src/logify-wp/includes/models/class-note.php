<?php
/**
 * Class Note
 * Represents a Note entity for the logify_notes table.
 */
namespace Logify_WP;

class Note {
    /**
     * @var int $id Primary Key: Unique ID for the note.
     */
    public $id;

    /**
     * @var int $activity_id Foreign key: Links note to activity log.
     */
    public $activity_id;

    /**
     * @var int $user_id Foreign key: User who created the note.
     */
    public $user_id;

	/**
	 * The name of the user who did the action.
	 *
	 * @var string
	 */
	public string $user_name;

	/**
	 * The role of the user. Will be 'none' for an anonymous user.
	 *
	 * @var string
	 */
	public string $user_role;    

    /**
     * @var string $note The note content.
     */
    public $note;

    /**
     * @var string $created_at Timestamp of note creation.
     */
    public $created_at;

    /**
     * @var string $ip_address IP Address of the user who created the note.
     */
    public $ip_address;

    /**
     * Constructor to initialize the Note object.
     *
     * @param array $data Associative array to initialize note properties.
     */
    public function __construct($data = []) {
        $this->id          = isset($data['id']) ? (int) $data['id'] : null;
        $this->activity_id = isset($data['activity_id']) ? (int) $data['activity_id'] : null;
        $this->user_id     = isset($data['user_id']) ? (int) $data['user_id'] : null;
		$this->user_name   = isset($data['user_name']) ? sanitize_textarea_field($data['user_name']) : '';
		$this->user_role   = isset($data['user_role']) ? sanitize_textarea_field($data['user_role']) : '';     
        $this->note        = isset($data['note']) ? sanitize_textarea_field($data['note']) : '';
        $this->created_at  = isset($data['created_at']) ? sanitize_text_field($data['created_at']) : '';
        $this->ip_address  = isset($data['ip_address']) ? sanitize_text_field($data['ip_address']) : '';
    }

    /**
     * Convert the Note object into an associative array.
     *
     * @return array
     */
    public function to_array() {
        return [
            'id'          => $this->id,
            'activity_id' => $this->activity_id,
            'user_id'     => $this->user_id,
            'user_name'   => $this->user_name,
            'user_role'   => $this->user_role,
            'note'        => $this->note,
            'created_at'  => $this->created_at,
            'ip_address'  => $this->ip_address,
        ];
    }

    /**
     * Validate the note properties before saving.
     *
     * @return bool|string Returns true if valid, or an error message.
     */
    public function validate() {
        if (empty($this->activity_id)) {
            return __('Activity ID is required.', 'logify-wp');
        }
        if (empty($this->user_id)) {
            return __('User ID is required.', 'logify-wp');
        }
        if (empty($this->note)) {
            return __('Note content cannot be empty.', 'logify-wp');
        }
        return true;
    }
}
