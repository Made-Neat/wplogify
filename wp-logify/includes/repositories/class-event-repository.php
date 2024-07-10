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

	// ---------------------------------------------------------------------------------------------
	// Initialization method.

	/**
	 * Initialize the repository.
	 */
	public static function init() {
		// Set the table name.
		global $wpdb;
		self::$table_name = $wpdb->prefix . 'wp_logify_events';
	}

	// ---------------------------------------------------------------------------------------------
	// CRUD methods.

	/**
	 * Select an entity by ID.
	 *
	 * @param int $event_id The ID of the event.
	 * @return ?object The Event object, or null if not found.
	 */
	public static function select( int $event_id ): ?object {
		global $wpdb;

		$sql  = $wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %d', self::$table_name, $event_id );
		$data = $wpdb->get_row( $sql, ARRAY_A );

		// If the record is not found, return null.
		if ( ! $data ) {
			return null;
		}

		// Construct the new Event object.
		$event = self::record_to_object( $data );

		// Load the event metadata.
		$event->event_meta = Event_Meta_Repository::select_by_event_id( $event->event_id );

		// Load the object properties.
		$event->properties = Property_Repository::select_by_event_id( $event->event_id );

		return $event;
	}

	/**
	 * Update or insert an event record.
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
	public static function upsert( object $event ): bool {
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
			$ok = $wpdb->insert( self::$table_name, $data, $formats );

			// If the new record was inserted ok, update the Event object with the new ID.
			if ( $ok ) {
				$event->event_id = $wpdb->insert_id;
			}
		} else {
			// Do the update.
			$ok = $wpdb->update( self::$table_name, $data, array( 'event_id' => $event->event_id ), $formats, array( '%d' ) );
		}

		// Rollback and return on error.
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Update the event_meta table.
		$ok = self::upsert_meta( $event );

		// Rollback and return on error.
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Update the properties table.
		$ok = self::upsert_properties( $event );

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
	 * Update the event_meta table.
	 *
	 * @param Event $event The event object.
	 * @return bool True on success, false on failure.
	 */
	public static function upsert_meta( Event $event ): bool {
		// Delete all existing associated records in the event_meta table.
		$ok = Event_Meta_Repository::delete_by_event_id( $event->event_id );

		// Return on error.
		if ( ! $ok ) {
			return false;
		}

		// If we have any metadata, insert new records.
		if ( ! empty( $event->event_meta ) ) {
			foreach ( $event->event_meta as $event_meta ) {

				// Ensure the event_id is set in the event_meta object.
				$event_meta->event_id = $event->event_id;

				// Update the event_meta record.
				$ok = Event_Meta_Repository::upsert( $event_meta );

				// Rollback and return on error.
				if ( ! $ok ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Update the properties table.
	 *
	 * @param Event $event The event object.
	 * @return bool True on success, false on failure.
	 */
	public static function upsert_properties( Event $event ): bool {
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
				$ok = Property_Repository::upsert( $property );

				// Return on error.
				if ( ! $ok ) {
					return false;
				}
			}
		}

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
		return (bool) $wpdb->delete( self::$table_name, array( 'event_id' => $event_id ), array( '%d' ) );
	}

	// ---------------------------------------------------------------------------------------------
	// Table-related methods.

	/**
	 * Create the table used to store log events.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::$table_name;
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
            PRIMARY KEY (event_id)
        ) $charset_collate;";

		dbDelta( $sql );
	}

	// ---------------------------------------------------------------------------------------------
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
