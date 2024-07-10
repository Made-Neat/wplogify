<?php
/**
 * Contains the Event_Meta_Repository class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use InvalidArgumentException;

/**
 * Class WP_Logify\Event_Meta_Repository
 *
 * This class provides CRUD operations for Event_Meta entities.
 */
class Event_Meta_Repository extends Repository {

	// =============================================================================================
	// CRUD methods.

	/**
	 * Select an entity by ID.
	 *
	 * @param int $event_meta_id The ID of the entity.
	 * @return ?Event_Meta The entity, or null if not found.
	 */
	public static function select( int $event_meta_id ): ?Event_Meta {
		global $wpdb;

		$sql  = $wpdb->prepare( 'SELECT * FROM %i WHERE event_meta_id = %d', self::get_table_name(), $event_meta_id );
		$data = $wpdb->get_row( $sql, ARRAY_A );

		// If the record is not found, return null.
		if ( ! $data ) {
			return null;
		}

		// Construct the new Event_Meta object.
		$event_meta = self::record_to_object( $data );

		return $event_meta;
	}

	/**
	 * Update or insert an entity.
	 *
	 * If the object has an ID, it will be updated. Otherwise, it will be inserted.
	 *
	 * If inserting, and the insert is successful, the entity's ID property will be set.
	 *
	 * @param object $event_meta The entity to update or insert.
	 * @return bool True on success, false on failure.
	 * @throws InvalidArgumentException If the entity is not an instance of Event_Meta.
	 */
	public static function upsert( object $event_meta ): bool {
		global $wpdb;

		// Check entity type.
		if ( ! $event_meta instanceof Event_Meta ) {
			throw new InvalidArgumentException( 'Entity must be an instance of Event_Meta.' );
		}

		// Check if we're inserting or updating.
		$inserting = empty( $event_meta->event_meta_id );

		// Update or insert the event_meta record.
		$data    = self::object_to_record( $event_meta );
		$formats = array( '%d', '%d', '%s', '%s' );
		if ( $inserting ) {
			// Do the insert.
			$ok = $wpdb->insert( self::get_table_name(), $data, $formats );

			// If the new record was inserted ok, update the Event_Meta object with the new ID.
			if ( $ok ) {
				$event_meta->event_meta_id = $wpdb->insert_id;
			}
		} else {
			// Do the update.
			$ok = $wpdb->update( self::get_table_name(), $data, array( 'event_meta_id' => $event_meta->event_meta_id ), $formats, array( '%d' ) );
		}

		return (bool) $ok;
	}

	/**
	 * Delete an event_meta record by ID.
	 *
	 * @param int $event_meta_id The ID of the event_meta record to delete.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( int $event_meta_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::get_table_name(), array( 'event_meta_id' => $event_meta_id ), array( '%d' ) );
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
		return $wpdb->prefix . 'wp_logify_event_meta';
	}

	/**
	 * Create the table used to store event meta.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			event_meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id      BIGINT UNSIGNED NOT NULL,
			meta_key      VARCHAR(255) NOT NULL,
			meta_value    LONGTEXT NOT NULL,
			PRIMARY KEY (event_meta_id),
			KEY event_id (event_id),
			KEY meta_key (meta_key(191))
		) $charset_collate;";

		dbDelta( $sql );
	}

	// =============================================================================================
	// Methods to convert between database records and entity objects.

	/**
	 * Convert a database record to an Event_Meta entity.
	 *
	 * @param array $data The database record.
	 * @return Event_Meta The Event_Meta entity.
	 */
	protected static function record_to_object( array $data ): Event_Meta {
		$event_meta                = new Event_Meta();
		$event_meta->event_meta_id = (int) $data['event_meta_id'];
		$event_meta->event_id      = (int) $data['event_id'];
		$event_meta->meta_key      = $data['meta_key'];
		$event_meta->meta_value    = Json::decode( $data['meta_value'] );
		return $event_meta;
	}

	/**
	 * Convert an Event_Meta entity to a database record.
	 *
	 * @param Event_Meta $event_meta The Event_Meta entity.
	 * @return array The database record.
	 */
	protected static function object_to_record( Event_Meta $event_meta ): array {
		return array(
			'event_id'   => $event_meta->event_id,
			'meta_key'   => $event_meta->meta_key,
			'meta_value' => Json::encode( $event_meta->meta_value ),
		);
	}

	// =============================================================================================
	// Methods relating to events.

	/**
	 * Select all event_meta records relating to an event.
	 *
	 * @param int $event_id The ID of the event.
	 * @return ?array Array of Event_Meta objects or null if none found.
	 */
	public static function select_by_event_id( int $event_id ): ?array {
		global $wpdb;

		// Get all the event_meta records connectted to the event.
		$sql  = $wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %d', self::get_table_name(), $event_id );
		$data = $wpdb->get_results( $sql, ARRAY_A );

		// If none found, return null.
		if ( ! $data ) {
			return null;
		}

		// Convert the records to objects.
		$event_metas = array_map( fn( $record ) => self::record_to_object( $record ), $data );
		return $event_metas;
	}

	/**
	 * Delete all event_meta records relating to an event.
	 *
	 * @param int $event_id The ID of the event.
	 * @return bool True if the delete completed ok, false on error.
	 */
	public static function delete_by_event_id( int $event_id ): bool {
		global $wpdb;

		// Do the delete.
		$ok = $wpdb->delete( self::get_table_name(), array( 'event_id' => $event_id ), array( '%d' ) );

		return $ok !== false;
	}
}
