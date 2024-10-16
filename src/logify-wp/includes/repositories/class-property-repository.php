<?php
/**
 * Contains the Property_Repository class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class responsible for managing properties in the database.
 */
class Property_Repository extends Repository {

	// =============================================================================================
	// CRUD methods.

	/**
	 * Load a Property from the database by ID.
	 *
	 * @param int $prop_id The ID of the property.
	 * @return ?Property The Property object, or null if not found.
	 */
	public static function load( int $prop_id ): ?Property {
		global $wpdb;

		// Get the property record.
		$record = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE prop_id = %d', self::get_table_name(), $prop_id ),
			ARRAY_A
		);

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
	 * @param object $prop The property to update or insert.
	 * @return bool True on success, false on failure.
	 * @throws InvalidArgumentException If the entity is not an instance of Property.
	 */
	public static function save( object $prop ): bool {
		global $wpdb;

		// Check entity type.
		if ( ! $prop instanceof Property ) {
			throw new InvalidArgumentException( esc_html( 'Entity must be an instance of Property.' ) );
		}

		// Check if we're inserting or updating.
		$inserting = false;
		if ( empty( $prop->id ) ) {
			// See if there is an existing record we should update.
			$existing_prop_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT prop_id FROM %i WHERE event_id = %d AND prop_key = %s',
					self::get_table_name(),
					$prop->event_id,
					$prop->key
				)
			);
			if ( $existing_prop_id ) {
				$prop->id = $existing_prop_id;
			} else {
				$inserting = true;
			}
		}

		// Update or insert the property record.
		$record  = self::object_to_record( $prop );
		$formats = array( '%d', '%s', '%s', '%s', '%s' );
		if ( $inserting ) {
			// Do the insert.
			$ok = $wpdb->insert( self::get_table_name(), $record, $formats ) !== false;

			// If the new record was inserted ok, update the Property object with the new ID.
			if ( $ok ) {
				$prop->id = $wpdb->insert_id;
			}
		} else {
			// Do the update.
			$ok = $wpdb->update( self::get_table_name(), $record, array( 'prop_id' => $prop->id ), $formats, array( '%d' ) ) !== false;
		}

		return $ok;
	}

	/**
	 * Delete a property record by ID.
	 *
	 * @param int $prop_id The ID of the property record to delete.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( int $prop_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::get_table_name(), array( 'prop_id' => $prop_id ), array( '%d' ) );
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
		return $wpdb->prefix . 'logify_wp_properties';
	}

	/**
	 * Create the table used to store event properties.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            prop_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            prop_key varchar(100) NOT NULL,
            table_name varchar(100) NULL,
            val LONGTEXT NULL,
            new_val LONGTEXT NULL,
            PRIMARY KEY  (prop_id),
            KEY event_id (event_id)
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

	// =============================================================================================
	// Methods to convert between database records and entity objects.

	/**
	 * Convert a database record to a Property object.
	 *
	 * @param array $record The database record as an associative array.
	 * @return Property The Property object.
	 */
	public static function record_to_object( array $record ): Property {
		// Unserialize the old and new values.
		if ( ! Serialization::try_unserialize( $record['val'], $val ) ) {
			Debug::error( "Failed to unserialize value: {$record['val']}" );
			$val = $record['val'];
		}
		if ( ! Serialization::try_unserialize( $record['new_val'], $new_val ) ) {
			Debug::error( "Failed to unserialize new value: {$record['new_val']}" );
			$new_val = $record['new_val'];
		}

		// Create the Property object.
		$prop = new Property( $record['prop_key'], $record['table_name'], $val, $new_val );

		// Set the ID and event ID.
		$prop->id       = (int) $record['prop_id'];
		$prop->event_id = (int) $record['event_id'];

		return $prop;
	}

	/**
	 * Convert a Property object to a database record.
	 *
	 * The ID property isn't included, as it isn't required for the insert or update operations.
	 *
	 * @param Property $prop The Property object.
	 * @return array The database record as an associative array.
	 */
	public static function object_to_record( Property $prop ): array {
		return array(
			'event_id'   => $prop->event_id,
			'prop_key'   => $prop->key,
			'table_name' => $prop->table_name,
			'val'        => Serialization::serialize( $prop->val ),
			'new_val'    => Serialization::serialize( $prop->new_val ),
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
		$recordset = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %d', self::get_table_name(), $event_id ),
			ARRAY_A
		);

		// If none found, return null.
		if ( ! $recordset ) {
			return null;
		}

		// Convert the records to objects.
		$props = array();
		foreach ( $recordset as $record ) {
			$prop                = self::record_to_object( $record );
			$props[ $prop->key ] = $prop;
		}

		return $props;
	}

	/**
	 * Delete all property records relating to an event.
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
			throw new RuntimeException( esc_html( "Failed to delete properties for event $event_id" ) );
		}

		return (bool) $result;
	}
}
