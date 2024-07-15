<?php
/**
 * Contains the Property_Repository class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

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
		$sql  = $wpdb->prepare( 'SELECT * FROM %i WHERE property_id = %d', self::get_table_name(), $property_id );
		$data = $wpdb->get_row( $sql, ARRAY_A );

		// If the record is not found, return null.
		if ( ! $data ) {
			return null;
		}

		// Construct the new Property object.
		$prop = self::record_to_object( $data );

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
		$inserting = empty( $property->property_id );

		// Update or insert the property record.
		$data    = self::object_to_record( $property );
		$formats = array( '%s', '%s', '%s', '%s' );
		if ( $inserting ) {
			// Do the insert.
			$ok = $wpdb->insert( self::get_table_name(), $data, $formats );

			// If the new record was inserted ok, update the Property object with the new ID.
			if ( $ok ) {
				$property->property_id = $wpdb->insert_id;
			}
		} else {
			// Do the update.
			$ok = $wpdb->update( self::get_table_name(), $data, array( 'property_id' => $property->property_id ), $formats, array( '%d' ) );
		}

		// Return on error.
		if ( ! $ok ) {
			return false;
		}

		return true;
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
	 * Create the table used to store log events.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            property_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id      BIGINT UNSIGNED NOT NULL,
            property_key  VARCHAR(255)    NOT NULL,
            property_type VARCHAR(4)      NOT NULL,
            old_value     LONGTEXT        NOT NULL,
            new_value     LONGTEXT        NULL,
            PRIMARY KEY (property_id),
            KEY event_id (event_id),
			KEY property_key (property_key(191))
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
	 * @param array $data The database record as an associative array.
	 * @return Property The Property object.
	 */
	public static function record_to_object( array $data ): Property {
		$old_value             = Serialization::unserialize( $data['old_value'] );
		$new_value             = Serialization::unserialize( $data['new_value'] );
		$property              = new Property( $data['property_key'], $data['property_type'], $old_value, $new_value );
		$property->property_id = (int) $data['property_id'];
		$property->event_id    = (int) $data['event_id'];
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
			'event_id'      => $property->event_id,
			'property_key'  => $property->property_key,
			'property_type' => $property->property_type,
			'old_value'     => Serialization::serialize( $property->old_value ),
			'new_value'     => Serialization::serialize( $property->new_value ),
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
		$sql  = $wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %d', self::get_table_name(), $event_id );
		$data = $wpdb->get_results( $sql, ARRAY_A );

		// If none found, return null.
		if ( ! $data ) {
			return null;
		}

		// Convert the records to objects.
		$properties = array_map( fn( $record ) => self::record_to_object( $record ), $data );

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
