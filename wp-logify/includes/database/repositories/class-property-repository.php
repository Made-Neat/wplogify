<?php
/**
 * Contains the Property_Repository class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;
use InvalidArgumentException;

/**
 * Class responsible for managing properties in the database.
 */
class Property_Repository extends Repository {

	// =============================================================================================
	// CRUD methods.

	/**
	 * Load a Property from the database by ID.
	 *
	 * @param int $property_id The ID of the property.
	 * @return ?Property The Property object, or null if not found.
	 */
	public static function load( int $property_id ): ?Property {
		global $wpdb;

		// Get the property record.
		$sql    = $wpdb->prepare( 'SELECT * FROM %i WHERE property_id = %d', self::get_table_name(), $property_id );
		$record = $wpdb->get_row( $sql, ARRAY_A );

		// If the record is not found, return null.
		if ( ! $record ) {
			return null;
		}

		// Construct the new Property object.
		$prop = self::record_to_object( $record );

		return $prop;
	}

	/**
	 * Save a Property object to the database.
	 *
	 * If the object has an ID, it will be updated. Otherwise, it will be inserted.
	 *
	 * If inserting, and the insert is successful, the entity's ID property will be set.*
	 *
	 * @param object $property The property to update or insert.
	 * @return bool True on success, false on failure.
	 * @throws InvalidArgumentException If the entity is not an instance of Property.
	 */
	public static function save( object $property ): bool {
		global $wpdb;

		// Check entity type.
		if ( ! $property instanceof Property ) {
			throw new InvalidArgumentException( 'Entity must be an instance of Property.' );
		}

		// Check if we're inserting or updating.
		$inserting = empty( $property->id );

		// Update or insert the property record.
		$record  = self::object_to_record( $property );
		$formats = array( '%s', '%s', '%s', '%s' );
		if ( $inserting ) {
			// Do the insert.
			$ok = $wpdb->insert( self::get_table_name(), $record, $formats ) !== false;

			// If the new record was inserted ok, update the Property object with the new ID.
			if ( $ok ) {
				$property->id = $wpdb->insert_id;
			}
		} else {
			// Do the update.
			$ok = $wpdb->update( self::get_table_name(), $record, array( 'property_id' => $property->id ), $formats, array( '%d' ) ) !== false;
		}

		return $ok;
	}

	/**
	 * Delete a property record by ID.
	 *
	 * @param int $property_id The ID of the property record to delete.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( int $property_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::get_table_name(), array( 'property_id' => $property_id ), array( '%d' ) );
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
		return $wpdb->prefix . 'wp_logify_properties';
	}

	/**
	 * Create the table used to store event properties.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            property_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id      BIGINT UNSIGNED NOT NULL,
            property_key  VARCHAR(100)    NOT NULL,
            table_name    VARCHAR(100)    NOT NULL,
            old_value     LONGTEXT        NULL,
            new_value     LONGTEXT        NULL,
            PRIMARY KEY (property_id),
        ) $charset_collate;";

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

	// =============================================================================================
	// Methods to convert between database records and entity objects.

	/**
	 * Convert a database record to a Property object.
	 *
	 * @param array $record The database record as an associative array.
	 * @return Property The Property object.
	 * @throws Exception If the old or new value cannot be unserialized.
	 */
	public static function record_to_object( array $record ): Property {
		// Unserialize the old and new values.
		if ( ! Serialization::try_unserialize( $record['old_value'], $old_value ) ) {
			throw new Exception( 'Failed to unserialize old property value.' );
		}
		if ( ! Serialization::try_unserialize( $record['new_value'], $new_value ) ) {
			throw new Exception( 'Failed to unserialize new property value.' );
		}

		// Create the Property object.
		$property = new Property( $record['property_key'], $record['table_name'], $old_value, $new_value );

		// Set the ID and event ID.
		$property->id       = (int) $record['property_id'];
		$property->event_id = (int) $record['event_id'];

		return $property;
	}

	/**
	 * Convert a Property object to a database record.
	 *
	 * The ID property isn't included, as it isn't required for the insert or update operations.
	 *
	 * @param Property $property The Property object.
	 * @return array The database record as an associative array.
	 */
	public static function object_to_record( Property $property ): array {
		return array(
			'event_id'     => $property->event_id,
			'property_key' => $property->key,
			'table_name'   => $property->table_name,
			'old_value'    => Serialization::serialize( $property->old_value ),
			'new_value'    => Serialization::serialize( $property->new_value ),
		);
	}

	// =============================================================================================
	// Methods relating to events.

	/**
	 * Select all property records relating to an event.
	 *
	 * @param int $event_id The ID of the event.
	 * @return ?array Array of Property objects or null if none found.
	 */
	public static function load_by_event_id( int $event_id ): ?array {
		global $wpdb;

		// Get all the properties connectted to the event.
		$sql       = $wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %d', self::get_table_name(), $event_id );
		$recordset = $wpdb->get_results( $sql, ARRAY_A );

		// If none found, return null.
		if ( ! $recordset ) {
			return null;
		}

		// Convert the records to objects.
		$properties = array();
		foreach ( $recordset as $record ) {
			$properties[ $record['property_key'] ] = self::record_to_object( $record );
		}

		return $properties;
	}

	/**
	 * Delete all property records relating to an event.
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
