<?php
/**
 * Contains the Event_Repository class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use InvalidArgumentException;

/**
 * Class responsible for managing events in the database.
 */
class Event_Repository extends Repository {

	// =============================================================================================
	// CRUD methods.

	/**
	 * Load an Event from the database by ID.
	 *
	 * @param int $event_id The ID of the event.
	 * @return ?Event The Event object, or null if not found.
	 */
	public static function load( int $event_id ): ?Event {
		global $wpdb;

		$sql = $wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %d', self::get_table_name(), $event_id );
		$row = $wpdb->get_row( $sql, ARRAY_A );

		// If the record is not found, return null.
		if ( ! $row ) {
			return null;
		}

		// Construct the new Event object.
		$event = self::record_to_object( $row );

		// Load the event metadata.
		self::load_metadata( $event );

		// Load the object properties.
		self::load_properties( $event );

		return $event;
	}

	/**
	 * Save an Event object to the database.
	 *
	 * If the object has an ID, it will be updated. Otherwise, it will be inserted.
	 *
	 * If inserting, and the insert is successful, the entity's ID property will be set.
	 *
	 * Using transactions here because the overall operation requires a number of SQL commands.
	 * If one fails, it's probably best to rollback the whole thing.
	 *
	 * @param object $event The entity to update or insert.
	 * @return bool True on success, false on failure.
	 * @throws InvalidArgumentException If the entity is not an instance of Event.
	 */
	public static function save( object $event ): bool {
		global $wpdb;

		// Check entity type.
		if ( ! $event instanceof Event ) {
			throw new InvalidArgumentException( 'Entity must be an instance of Event.' );
		}

		// Check if we're inserting or updating.
		$inserting = empty( $event->event_id );

		// Start a transaction.
		$wpdb->query( 'START TRANSACTION' );

		// Update or insert the events record.
		$data    = self::object_to_record( $event );
		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
		if ( $inserting ) {
			// Do the insert.
			$ok = $wpdb->insert( self::get_table_name(), $data, $formats );

			// If the new record was inserted ok, update the Event object with the new ID.
			if ( $ok ) {
				$event->event_id = $wpdb->insert_id;
			}
		} else {
			// Do the update.
			$ok = $wpdb->update( self::get_table_name(), $data, array( 'event_id' => $event->event_id ), $formats, array( '%d' ) );
		}

		// Rollback and return on error.
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Update the event_meta table.
		$ok = self::save_metadata( $event );

		// Rollback and return on error.
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Update the properties table.
		$ok = self::save_properties( $event );

		// Rollback and return on error.
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Commit the transaction.
		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Delete an event record by ID.
	 *
	 * @param int $event_id The ID of the event record to delete.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( int $event_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::get_table_name(), array( 'event_id' => $event_id ), array( '%d' ) );
	}

	// =============================================================================================
	// Methods for loading and saving metadata.

	/**
	 * Load metadata for an event.
	 *
	 * @param Event $event The event object.
	 */
	public static function load_metadata( Event $event ) {
		global $wpdb;

		// Get the metadata records for the event from the event_meta table.
		$sql_meta = $wpdb->prepare(
			'SELECT meta_key, meta_value FROM %i WHERE event_id = %d',
			Event_Meta_Repository::get_table_name(),
			$event->event_id
		);
		$rows     = $wpdb->get_results( $sql_meta, ARRAY_A );

		// Convert the query result into an associative array.
		foreach ( $rows as $row ) {
			$event->event_meta[ $row['meta_key'] ] = Json::decode( $row['meta_value'] );
		}
	}

	/**
	 * Save metadata for an event.
	 *
	 * @param Event $event The event object.
	 * @return bool True on success, false on failure.
	 */
	public static function save_metadata( Event $event ): bool {
		// Delete all existing associated records in the event_meta table.
		$ok = Event_Meta_Repository::delete_by_event_id( $event->event_id );

		// Return on error.
		if ( ! $ok ) {
			return false;
		}

		// If we have any metadata, insert new records.
		if ( ! empty( $event->event_meta ) ) {
			foreach ( $event->event_meta as $meta_key => $meta_value ) {

				// Construct the new Event_Meta object.
				$event_meta_object = new Event_Meta( $event->event_id, $meta_key, $meta_value );

				// Save the object.
				$ok = Event_Meta_Repository::save( $event_meta_object );

				// Rollback and return on error.
				if ( ! $ok ) {
					return false;
				}
			}
		}

		return true;
	}

	// =============================================================================================
	// Methods for loading and saving properties.

	/**
	 * Load the properties for an event.
	 *
	 * @param Event $event The event object.
	 */
	public static function load_properties( Event $event ) {
		$event->properties = Property_Repository::load_by_event_id( $event->event_id );
	}

	/**
	 * Update the properties table.
	 *
	 * @param Event $event The event object.
	 * @return bool True on success, false on failure.
	 */
	public static function save_properties( Event $event ): bool {
		// Delete all associated records in the properties table.
		$ok = Property_Repository::delete_by_event_id( $event->event_id );

		// Return on error.
		if ( ! $ok ) {
			return false;
		}

		// If we have any properties, insert new records.
		if ( ! empty( $event->properties ) ) {
			foreach ( $event->properties as $property ) {

				// Ensure the event_id is set in the property object.
				$property->event_id = $event->event_id;

				// Update the property record.
				$ok = Property_Repository::save( $property );

				// Return on error.
				if ( ! $ok ) {
					return false;
				}
			}
		}

		return true;
	}

	// =============================================================================================
	// Table-related methods.

	/**
	 * Get the table name.
	 *
	 * @return string The table name.
	 */
	public static function get_table_name(): string {
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
            event_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date_time     DATETIME        NOT NULL,
            user_id       BIGINT UNSIGNED NOT NULL,
            user_name     VARCHAR(255)    NOT NULL,
            user_role     VARCHAR(255)    NOT NULL,
            user_ip       VARCHAR(40)     NULL,
            user_location VARCHAR(255)    NULL,
            user_agent    VARCHAR(255)    NULL,
            event_type    VARCHAR(255)    NOT NULL,
            object_type   VARCHAR(10)     NULL,
            object_id     BIGINT UNSIGNED NULL,
            object_name   VARCHAR(255)    NULL,
            PRIMARY KEY (event_id),
            KEY user_id (user_id)
        ) $charset_collate;";

		dbDelta( $sql );
	}

	// =============================================================================================
	// Methods to convert between database records and entity objects.

	/**
	 * Event constructor.
	 *
	 * @param array $data The database record as an associative array.
	 * @return Event The new Event object.
	 */
	public static function record_to_object( array $data ): Event {
		$event                = new Event();
		$event->event_id      = (int) $data['event_id'];
		$event->date_time     = DateTimes::create_datetime( $data['date_time'] );
		$event->user_id       = (int) $data['user_id'];
		$event->user_name     = $data['user_name'];
		$event->user_role     = $data['user_role'];
		$event->user_ip       = $data['user_ip'];
		$event->user_location = $data['user_location'];
		$event->user_agent    = $data['user_agent'];
		$event->event_type    = $data['event_type'];
		$event->object_type   = $data['object_type'];
		$event->object_id     = $data['object_id'];
		$event->object_name   = $data['object_name'];
		return $event;
	}

	/**
	 * Converts an Event object to a data array for saving to the database.
	 *
	 * The event_id property isn't included, as it isn't required for the insert or update operations.
	 *
	 * @param Event $event The Event object.
	 * @return array The database record as an associative array.
	 */
	public static function object_to_record( Event $event ): array {
		return array(
			'date_time'     => DateTimes::format_datetime_mysql( $event->date_time ),
			'user_id'       => $event->user_id,
			'user_name'     => $event->user_name,
			'user_role'     => $event->user_role,
			'user_ip'       => $event->user_ip,
			'user_location' => $event->user_location,
			'user_agent'    => $event->user_agent,
			'event_type'    => $event->event_type,
			'object_type'   => $event->object_type,
			'object_id'     => $event->object_id,
			'object_name'   => $event->object_name,
		);
	}
}
