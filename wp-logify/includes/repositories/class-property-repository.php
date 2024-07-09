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

	// ---------------------------------------------------------------------------------------------
	// Initialisation method.

	/**
	 * Initialize the repository.
	 */
	public static function init() {
		// Set the table name.
		global $wpdb;
		self::$table_name = $wpdb->prefix . 'wp_logify_properties';

		// Ensure table is created on plugin activation.
		self::create_table();
	}

	// ---------------------------------------------------------------------------------------------
	// Implementations of base class CRUD methods.

	/**
	 * Select a property by ID.
	 *
	 * @param int $id The ID of the property.
	 * @return ?object The Property object, or null if not found.
	 */
	public static function select( int $id ): ?object {
		global $wpdb;

		// Get the property record.
		$sql  = $wpdb->prepare( 'SELECT * FROM %i WHERE ID = %d', self::$table_name, $id );
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
	 * Update or insert a property record.
	 *
	 * If the object has an ID, it will be updated. Otherwise, it will be inserted.
	 *
	 * If inserting, and the insert is successful, the entity's ID property will be set.*
	 *
	 * @param object $property The property to update or insert.
	 * @return bool True on success, false on failure.
	 * @throws InvalidArgumentException If the entity is not an instance of Property.
	 */
	public static function upsert( object $property ): bool {
		global $wpdb;

		// Check entity type.
		if ( ! $property instanceof Property ) {
			throw new InvalidArgumentException( 'Entity must be an instance of Property.' );
		}

		// Check if we're inserting or updating.
		$inserting = empty( $property->id );

		// Insert the property.
		$data   = self::object_to_record( $property );
		$format = array( '%s', '%s', '%s', '%s' );
		if ( $inserting ) {
			$ok = $wpdb->insert( self::$table_name, $data, $format );
		} else {
			$ok = $wpdb->update( self::$table_name, $data, array( 'ID' => $property->id ), $format, array( '%d' ) );
		}

		// Return on error.
		if ( ! $ok ) {
			return false;
		}

		// If inserting, set the ID for the new property record.
		if ( $inserting ) {
			$property->id = $wpdb->insert_id;
		}

		return true;
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
            ID        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id  BIGINT UNSIGNED NOT NULL,
            prop_name VARCHAR(25)     NOT NULL,
            prop_type VARCHAR(4)      NOT NULL,
            old_value VARCHAR(255)    NOT NULL,
            new_value VARCHAR(255)    NULL,
            PRIMARY KEY (ID)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// ---------------------------------------------------------------------------------------------
	// Custom methods.

	/**
	 * Select all properties of an object relating to an event.
	 *
	 * @param int $event_id The ID of the event.
	 * @return ?array Array of Property objects or null if none found.
	 */
	public static function select_by_event_id( int $event_id ): ?array {
		global $wpdb;

		// Get all the properties connectted to the event.
		$sql  = $wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %d', self::$table_name, $event_id );
		$data = $wpdb->get_results( $sql, ARRAY_A );

		// If no properties are found, return null.
		if ( ! $data ) {
			return null;
		}

		// Convert the property records to objects.
		$properties = array_map( fn( $record ) => self::record_to_object( $record ), $data );
		return $properties;
	}

	// ---------------------------------------------------------------------------------------------
	// Methods to convert between database records and entity objects.

	/**
	 * Convert a database record to a Property object.
	 *
	 * @param array $data The database record as an associative array.
	 * @return Property The Property object.
	 */
	public static function record_to_object( array $data ): Property {
		$property            = new Property();
		$property->id        = (int) $data['ID'];
		$property->event_id  = (int) $data['event_id'];
		$property->prop_name = $data['prop_name'];
		$property->prop_type = $data['prop_type'];
		$property->old_value = Json::decode( $data['old_value'] );
		$property->new_value = Json::decode( $data['new_value'] );
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
			'event_id'  => $property->event_id,
			'prop_name' => $property->prop_name,
			'prop_type' => $property->prop_type,
			'old_value' => Json::encode( $property->old_value ),
			'new_value' => Json::encode( $property->new_value ),
		);
	}
}
