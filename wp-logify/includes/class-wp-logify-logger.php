<?php
class WP_Logify_Logger {

	/**
	 * The valid object types for which events can be logged.
	 */
	public const VALID_OBJECT_TYPES = array( 'post', 'user', 'category', 'plugin', 'theme' );

	/**
	 * Initializes the class by adding WordPress actions.
	 */
	public static function init() {
		// Ensure table is created on plugin activation
		self::create_table();
	}

	/**
	 * Create the table used to store log events.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date_time datetime NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            user_role varchar(255) NOT NULL,
            source_ip varchar(100) NOT NULL,
            event_type varchar(255) NOT NULL,
            object_type varchar(20) NOT NULL,
            object_id varchar(20) NOT NULL,
            details json NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get the name of the table used to store log events.
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wp_logify_events';
	}

	/**
	 * Logs an event to the database.
	 *
	 * @param string $event_type  The type of event.
	 * @param string $object_type The type of object associated with the event.
	 * @param string $object_id   The ID or name of the object associated with the event.
	 * @param array  $details     Additional details about the event.
	 * @param int    $user_id     The ID of the user associated with the event (defaults to current).
	 */
	public static function log_event( string $event_type, string $object_type = null, string $object_id = null, array $details = array(), int $user_id = null ) {
		global $wpdb;

		// Check object type is valid.
		if ( $object_type !== null && ! in_array( $object_type, self::VALID_OBJECT_TYPES, true ) ) {
			throw new InvalidArgumentException( 'Invalid object type.' );
		}

		$user      = $user_id ? get_userdata( intval( $user_id ) ) : wp_get_current_user();
		$user_id   = $user->ID;
		$user_role = implode( ', ', array_map( 'sanitize_text_field', $user->roles ) );
		$source_ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
		$date_time = current_time( 'mysql' );

		$wpdb->insert(
			self::get_table_name(),
			array(
				'date_time'   => sanitize_text_field( $date_time ),
				'user_id'     => intval( $user_id ),
				'user_role'   => $user_role,
				'source_ip'   => $source_ip,
				'event_type'  => sanitize_text_field( $event_type ),
				'object_type' => $object_type === null ? null : sanitize_text_field( $object_type ),
				'object_id'   => $object_id === null ? null : sanitize_text_field( $object_id ),
				'details'     => wp_json_encode( $details ),
			)
		);
	}
}
