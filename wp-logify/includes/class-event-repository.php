<?php
/**
 * EventRepository class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class responsible for managing events in the database.
 */
class EventRepository {

	/**
	 * Initializes the class by adding WordPress actions.
	 */
	public static function init() {
		// Ensure table is created on plugin activation.
		self::create_table();
	}

	/**
	 * Get the name of the table used to store log events.
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wp_logify_events';
	}

	/**
	 * Create the table used to store log events.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            date_time datetime NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            user_role varchar(255) NOT NULL,
            user_ip varchar(100) NOT NULL,
            user_location varchar(255) NULL,
            user_agent varchar(255) NULL,
            event_type varchar(255) NOT NULL,
            object_type varchar(20) NULL,
            object_id varchar(20) NULL,
            object_name varchar(255) NULL,
            details text NULL,
            changes text NULL,
            PRIMARY KEY (ID)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the events table.
	 */
	public static function drop_table() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::get_table_name() ) );
	}

	/**
	 * Empty the events table.
	 */
	public static function truncate_table() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::get_table_name() ) );
	}

	/**
	 * Saves an event to the database.
	 *
	 * @param Event $event The event to save.
	 */
	public static function save( Event $event ) {
		global $wpdb;

		$data   = $event->to_array();
		$format = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( empty( $event->id ) ) {
			// Insert new record.
			$wpdb->insert( self::get_table_name(), $data, $format );
			$event->id = $wpdb->insert_id;
		} else {
			// Update existing record.
			$wpdb->update( self::get_table_name(), $data, array( 'ID' => $event->id ), $format, array( '%d' ) );
		}
	}

	/**
	 * Loads an event from the database.
	 *
	 * @param int $id The ID of the event to load.
	 * @return Event|null The loaded event, or null if not found.
	 */
	public static function load( int $id ): ?Event {
		global $wpdb;

		$sql  = $wpdb->prepare( 'SELECT * FROM %i WHERE ID = %d', self::get_table_name(), $id );
		$data = $wpdb->get_row( $sql, ARRAY_A );
		return $data ? new Event( $data ) : null;
	}

	/**
	 * Deletes an event from the database.
	 *
	 * @param int $id The ID of the event to delete.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		return (bool) $wpdb->delete( self::get_table_name(), array( 'ID' => $id ), array( '%d' ) );
	}

	/**
	 * Retrieves all events from the database.
	 *
	 * @return Event[] An array of events.
	 */
	public static function load_all(): array {
		global $wpdb;

		$sql     = $wpdb->prepare( 'SELECT * FROM %i ORDER BY date_time', self::get_table_name() );
		$results = $wpdb->get_results( $sql, ARRAY_A );
		$events  = array();
		foreach ( $results as $row ) {
			$events[] = new Event( $row );
		}
		return $events;
	}
}
