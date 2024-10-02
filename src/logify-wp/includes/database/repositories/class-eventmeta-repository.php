<?php
/**
 * Contains the Eventmeta_Repository class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Class Logify_WP\Eventmeta_Repository
 *
 * This class provides CRUD operations for Eventmeta entities.
 */
class Eventmeta_Repository extends Repository {

	// =============================================================================================
	// CRUD methods.

	/**
	 * Load an Eventmeta entity from the database by ID.
	 *
	 * @param int $eventmeta_id The ID of the entity.
	 * @return ?Eventmeta The entity, or null if not found.
	 */
	public static function load( int $eventmeta_id ): ?Eventmeta {
		global $wpdb;

		$sql    = $wpdb->prepare( 'SELECT * FROM %i WHERE eventmeta_id = %d', self::get_table_name(), $eventmeta_id );
		$record = $wpdb->get_row( $sql, ARRAY_A );

		// If the record is not found, return null.
		if ( ! $record ) {
			return null;
		}

		// Construct the new Eventmeta object.
		$eventmeta = self::record_to_object( $record );

		return $eventmeta;
	}

	/**
	 * Save an Eventmeta object to the database.
	 *
	 * If the object has an ID, it will be updated. Otherwise, it will be inserted.
	 *
	 * If inserting, and the insert is successful, the entity's ID property will be set.
	 *
	 * @param object $eventmeta The Eventmeta to update or insert.
	 * @return bool True on success, false on failure.
	 * @throws InvalidArgumentException If the entity is not an instance of Eventmeta.
	 */
	public static function save( object $eventmeta ): bool {
		global $wpdb;

		// Check entity type.
		if ( ! $eventmeta instanceof Eventmeta ) {
			throw new InvalidArgumentException( 'Entity must be an instance of Eventmeta.' );
		}

		// Get the table name.
		$table_name = self::get_table_name();

		// Check if we're inserting or updating.
		$inserting = false;
		if ( empty( $eventmeta->id ) ) {
			// See if there is an existing record we should update.
			$sql                   = $wpdb->prepare(
				'SELECT eventmeta_id FROM %i WHERE event_id = %d AND meta_key = %s',
				$table_name,
				$eventmeta->event_id,
				$eventmeta->meta_key
			);
			$existing_eventmeta_id = $wpdb->get_var( $sql );
			if ( $existing_eventmeta_id ) {
				$eventmeta->id = $existing_eventmeta_id;
			} else {
				$inserting = true;
			}
		}

		// Update or insert the eventmeta record.
		$record  = self::object_to_record( $eventmeta );
		$formats = array( '%d', '%s', '%s' );
		if ( $inserting ) {
			// Do the insert.
			$ok = $wpdb->insert( $table_name, $record, $formats ) !== false;

			// If the new record was inserted ok, update the Eventmeta object with the new ID.
			if ( $ok ) {
				$eventmeta->id = $wpdb->insert_id;
			}
		} else {
			// Do the update.
			$ok = $wpdb->update( $table_name, $record, array( 'eventmeta_id' => $eventmeta->id ), $formats, array( '%d' ) ) !== false;
		}

		return $ok;
	}

	/**
	 * Delete an eventmeta record by ID.
	 *
	 * @param int $eventmeta_id The ID of the eventmeta record to delete.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( int $eventmeta_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::get_table_name(), array( 'eventmeta_id' => $eventmeta_id ), array( '%d' ) );
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
		return $wpdb->prefix . 'logify_wp_eventmeta';
	}

	/**
	 * Create the table used to store eventmetas.
	 */
	public static function create_table() {
		global $wpdb;

		// Create or update the table.
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE $table_name (
			eventmeta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id      BIGINT UNSIGNED NOT NULL,
			meta_key      VARCHAR(255) NOT NULL,
			meta_value    LONGTEXT NOT NULL,
			PRIMARY KEY (eventmeta_id),
			KEY event_id (event_id),
			KEY meta_key (meta_key(191))
		) $charset_collate;";
		dbDelta( $sql );

		// Migrate data from the old wp-logify table, if present and not done already.
		self::migrate_data( 'eventmeta' );
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

	// =============================================================================================
	// Methods to convert between database records and entity objects.

	/**
	 * Convert a database record to an Eventmeta entity.
	 *
	 * @param array $record The database record.
	 * @return Eventmeta The Eventmeta entity.
	 * @throws UnexpectedValueException If the meta value cannot be unserialized.
	 */
	protected static function record_to_object( array $record ): Eventmeta {
		// Unserialize the meta value.
		if ( ! Serialization::try_unserialize( $record['meta_value'], $meta_value ) ) {
			throw new UnexpectedValueException( 'Failed to unserialize eventmeta value.' );
		}

		// Create the Eventmeta object.
		$eventmeta = new Eventmeta( (int) $record['event_id'], $record['meta_key'], $meta_value );

		// Set the ID.
		$eventmeta->id = (int) $record['eventmeta_id'];

		return $eventmeta;
	}

	/**
	 * Convert an Eventmeta entity to a database record.
	 *
	 * @param Eventmeta $eventmeta The Eventmeta entity.
	 * @return array The database record.
	 */
	protected static function object_to_record( Eventmeta $eventmeta ): array {
		return array(
			'event_id'   => $eventmeta->event_id,
			'meta_key'   => $eventmeta->meta_key,
			'meta_value' => Serialization::serialize( $eventmeta->meta_value ),
		);
	}

	// =============================================================================================
	// Methods relating to events.

	/**
	 * Load metadata for an event.
	 *
	 * @param int $event_id The ID of the event.
	 * @return ?array Array of metadata or null if none found.
	 */
	public static function load_by_event_id( int $event_id ): ?array {
		global $wpdb;

		// Get the metadata records for the event from the eventmeta table.
		$sql_meta  = $wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %d', self::get_table_name(), $event_id );
		$recordset = $wpdb->get_results( $sql_meta, ARRAY_A );

		// If none found, return null.
		if ( ! $recordset ) {
			return null;
		}

		// Convert the query result into an associative array.
		$eventmetas = array();
		foreach ( $recordset as $record ) {
			$eventmetas[ $record['meta_key'] ] = self::record_to_object( $record );
		}

		return $eventmetas;
	}

	/**
	 * Delete all eventmeta records relating to an event.
	 *
	 * @param int $event_id The ID of the event.
	 * @return bool True on success, false on failure.
	 * @throws RuntimeException If the delete fails.
	 */
	public static function delete_by_event_id( int $event_id ): bool {
		global $wpdb;

		// Do the delete.
		$result = $wpdb->delete( self::get_table_name(), array( 'event_id' => $event_id ), array( '%d' ) );

		// Check for error.
		if ( $result === false ) {
			throw new RuntimeException( "Failed to delete eventmetas for event $event_id" );
		}

		return (bool) $result;
	}
}
