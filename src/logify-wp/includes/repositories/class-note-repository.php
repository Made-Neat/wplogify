<?php
namespace Logify_WP;

use InvalidArgumentException;
use RuntimeException;

/**
 * Note Repository Class
 * Handles CRUD operations for the logify_notes table.
 */
class Note_Repository extends Repository {
    
    /**
     * Table name for the notes.
     */
    protected $table_name;

    /**
     * Constructor: Set the table name and initialize parent.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'logify_notes';
        //parent::__construct();
    }
    
    /**
     * Get the table name for the repository.
     *
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'logify_notes';
    }

    /**
     * Create a new table for notes.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;
    
        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
    
        $sql = "CREATE TABLE {$table_name} (
            note_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            activity_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            user_role VARCHAR(255) NOT NULL,
            note TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            ip_address VARCHAR(45) NOT NULL,
            PRIMARY KEY (note_id),
            KEY activity_index (activity_id)
        ) {$charset_collate} ENGINE=InnoDB;";
    
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

	/**
	 * Notes constructor.
	 *
	 * @param array $record The database record as an associative array.
	 * @return Event The new Event object.
	 */
	public static function record_to_object( array $record ): Note {
		$note                 = new Note();
		$note->id             = (int) $record['note_id'];
		$note->when_happened  = DateTimes::create_datetime( $record['created_at'] );
		$note->user_id        = (int) $record['user_id'];
		$note->user_name      = $record['user_name'];
		$note->user_role      = $record['user_role'];
		$note->user_ip        = $record['user_ip'];
		$note->note  = $record['note'];
		$note->event_id     = $record['activity_id'];
		return $note;
	}   
    

    /**
     * Load a note by ID.
     *
     * @param int $id Note ID.
     * @return object|null Note data or null if not found.
     */
    public static function load(int $id): ?object {
        global $wpdb;

        $record = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE note_id = %d', self::get_table_name(), $id ),
			ARRAY_A
		);

		// If the record is not found, return null.
		if ( ! $record ) {
			return null;
		}

        // Construct the new Event object.
		$note = self::record_to_object( $record );

		if($note->event_id != 0){
            // Load the properties.
            $note->properties = Property_Repository::load_by_event_id( $note->event_id );

            // Load the eventmetas.
            $note->eventmetas = Eventmeta_Repository::load_by_event_id( $note->event_id );

            // Load the notes.
            $note->eventnotes = Note_Repository::load_by_event_id( $note->event_id );
        }
		return $note;
    }

    /**
     * Save or update a note.
     *
     * @param object $entity The note object to save.
     * @return bool True on success, false otherwise.
     * @throws InvalidArgumentException
     */
    public static function save(object $entity): bool {
        global $wpdb;
        $table_name      = self::get_table_name();
        if (!isset($entity->id) || empty($entity->id) || !is_numeric($entity->id)) {
            throw new InvalidArgumentException('Note ID is required for save operation.');
        }

        if (empty($entity->note) || !is_string($entity->note)) {
            throw new InvalidArgumentException('Note content must be a non-empty string.');
        }

        $updated = $wpdb->update(
            $table_name,
            [
                'note'       => wp_kses_post($entity->note),
                'updated_at' => current_time('mysql'),
            ],
            ['note_id' => intval($entity->id)],
            ['%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

        /**
     * Drop the notes table.
     *
     * @return void
     */
    public static function drop_table(): void {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS " . self::get_table_name());
    }

    /**
     * Truncate the notes table (delete all records).
     *
     * @return void
     */
    public static function truncate_table(): void {
        global $wpdb;

        $wpdb->query("TRUNCATE TABLE " . self::get_table_name());
    }


    /**
     * Create a new note entry.
     *
     * @param array $data Associative array of note data.
     * @return int Inserted ID.
     * @throws InvalidArgumentException|RuntimeException
     */
    public static function create(array $data): int {
        global $wpdb;
        
        if (empty($data['activity_id']) || !is_numeric($data['activity_id'])) {
           // throw new InvalidArgumentException('Invalid or missing activity_id.');
        }

        if (empty($data['user_id']) || !is_numeric($data['user_id'])) {
            throw new InvalidArgumentException('Invalid or missing user_id.');
        }

        if (empty($data['note']) || !is_string($data['note'])) {
            throw new InvalidArgumentException('Note content must be a non-empty string.');
        }

        $inserted = $wpdb->insert(self::get_table_name(), [
            'activity_id' => intval($data['activity_id']),
            'user_id'     => intval($data['user_id']),
            'user_name'    => $data['user_name'],
            'user_role'   => $data['user_role'],
            'note'        => wp_kses_post($data['note']),
            'created_at'  => current_time('mysql'),
            'ip_address'  => sanitize_text_field($data['ip_address'] ?? ''),
        ]);

        if (!$inserted) {
            throw new RuntimeException('Failed to create a new note.');
        }

        return $wpdb->insert_id;
    }

    /**
     * Delete a note by its ID.
     *
     * @param int $id Note ID.
     * @return bool True on success, false on failure.
     * @throws InvalidArgumentException|RuntimeException
     */
    public static function delete(int $id): bool {
        global $wpdb;
        
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid note ID. ID must be a positive integer.');
        }

        $deleted = $wpdb->delete(self::get_table_name(), ['id' => $id], ['%d']);

        if ($deleted === false) {
            throw new RuntimeException("Failed to delete note with ID: {$id}");
        }

        return $deleted > 0;
    }

    /**
     * Search notes for a given keyword.
     *
     * @param string $keyword Search term.
     * @return array List of matching notes.
     * @throws InvalidArgumentException
     */
    public function search(string $keyword): array {
        if (empty($keyword)) {
            throw new InvalidArgumentException('Search keyword must be a non-empty string.');
        }

        $like = '%' . $this->wpdb->esc_like($keyword) . '%';

        return $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE note LIKE %s", $like)
        );
    }

    /**
     * Get all notes for a specific activity ID.
     *
     * @param int $activity_id Activity Log ID.
     * @return array List of notes.
     * @throws InvalidArgumentException
     */
   /* public function get_by_activity_id(int $activity_id): array {
        if ($activity_id <= 0) {
            throw new InvalidArgumentException('Invalid activity ID.');
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE activity_id = %d", $activity_id)
        );
    }*/

    /**
     * Helper to get the WordPress users table name.
     *
     * @return string
     */
    private static function get_wp_users_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'users';
    }


    /**
     * Load notes by activity/event ID.
     *
     * @param int $event_id The event ID.
     * @return array List of notes.
     */
    public static function load_by_event_id($event_id) {
        global $wpdb;
		
		// Check if the table exists before querying.
		$table_name = self::get_table_name();
		if (!self::table_exists($table_name)) {			
			return null; // Avoid running the query if the table is missing.
		}
    
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE activity_id = %d ORDER BY note_id DESC LIMIT 1",
				intval($event_id)
			)
		);
    }
	
	/**
	 * Helper function to check if a table exists in the database.
	 */
	private static function table_exists($table_name) {
		global $wpdb;
		return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
	}
    
}
