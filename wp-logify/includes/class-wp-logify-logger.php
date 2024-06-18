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
            object_type varchar(20) NULL,
            object_id varchar(20) NULL,
            details text NULL,
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
	 */
	public static function log_event( string $event_type, ?string $object_type = null, ?string $object_id = null, ?array $details = null ) {
		global $wpdb;

		// Check object type is valid.
		if ( $object_type !== null && ! in_array( $object_type, self::VALID_OBJECT_TYPES, true ) ) {
			throw new InvalidArgumentException( 'Invalid object type.' );
		}

		// Get the user info.
		$user      = wp_get_current_user();
		$user_id   = $user->ID;
		$user_role = implode( ', ', array_map( 'sanitize_text_field', $user->roles ) );

		$date_time = WP_Logify_DateTime::format_datetime_mysql( WP_Logify_DateTime::current_datetime() );
		$source_ip = WP_Logify_Users::get_user_ip();

		$wpdb->insert(
			self::get_table_name(),
			array(
				'date_time'   => $date_time,
				'user_id'     => $user_id,
				'user_role'   => $user_role,
				'source_ip'   => $source_ip,
				'event_type'  => $event_type,
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'details'     => $details === null ? null : wp_json_encode( $details ),
			)
		);
	}
}
