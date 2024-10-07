<?php
/**
 * Contains the Repository_Interface interface.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Interface Logify_WP\Repository_Interface
 *
 * This interface defines the methods common to all repositories.
 */
abstract class Repository {

	// =============================================================================================
	// CRUD methods.

	/**
	 * Load an entity from the database by ID.
	 *
	 * @param int $id The ID of the entity.
	 * @return ?object The entity, or null if not found.
	 */
	abstract public static function load( int $id ): ?object;

	/**
	 * Save an entity to the database.
	 *
	 * If the object has an ID, it will be updated. Otherwise, it will be inserted.
	 *
	 * If inserting, and the insert is successful, the entity's ID property will be set.
	 *
	 * @param object $entity The entity to insert.
	 * @return bool True on success, false on failure.
	 */
	abstract public static function save( object $entity ): bool;

	/**
	 * Delete an entity by ID.
	 *
	 * @param int $id The ID of the entity to delete.
	 * @return bool True on success, false on failure.
	 */
	abstract public static function delete( int $id ): bool;

	// =============================================================================================
	// Table-related methods.

	/**
	 * Get the table name.
	 *
	 * @return string The table name.
	 */
	abstract public static function get_table_name(): string;

	/**
	 * Create the table.
	 */
	abstract public static function create_table();

	/**
	 * Drop the table.
	 */
	abstract public static function drop_table();

	/**
	 * Empty the table.
	 */
	abstract public static function truncate_table();

	/**
	 * Migrate data from the old wp-logify table, if present and not done already.
	 *
	 * @param string $table_key        The table key (e.g. 'events', 'properties', or 'eventmeta').
	 * @param array  $fields_to_update The fields that require a namespace update.
	 * @return void
	 */
	public static function migrate_data( string $table_key, array $fields_to_update = array() ): void {
		$option = "logify_wp_{$table_key}_data_migrated";
		if ( ! get_option( $option, false ) ) {

			global $wpdb;

			// Check if the new table is empty.
			$new_table_name  = "{$wpdb->prefix}logify_wp_$table_key";
			$new_table_empty = $wpdb->get_var( "SELECT COUNT(*) FROM $new_table_name" ) === '0';

			// Check if the old wp_logify table is present and not empty.
			$old_table_name   = "{$wpdb->prefix}wp_logify_$table_key";
			$old_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$old_table_name'" ) === $old_table_name;
			$old_table_empty  = $old_table_exists && $wpdb->get_var( "SELECT COUNT(*) FROM $old_table_name" ) === '0';

			// If the new table is empty and the old table is present, copy the data.
			if ( $new_table_empty && ! $old_table_empty ) {
				// Select all records from the old table.
				$old_records = $wpdb->get_results( "SELECT * FROM $old_table_name", ARRAY_A );

				Debug::info( $fields_to_update );
				// Iterate through the records.
				foreach ( $old_records as $record ) {
					// Update the namespace in the specified fields.
					foreach ( $fields_to_update as $field ) {
						if ( is_string( $record[ $field ] ) && strpos( $record[ $field ], 'WP_Logify' ) !== false ) {
							$record[ $field ] = str_replace( 'WP_Logify', 'Logify_WP', $record[ $field ] );
						}
					}

					// Remove any invalid fields.
					$valid_fields = self::get_table_column_names( $new_table_name );
					foreach ( $record as $key => $value ) {
						if ( ! in_array( $key, $valid_fields ) ) {
							unset( $record[ $key ] );
						}
					}

					// Insert the transformed record into the new table.
					$wpdb->insert( $new_table_name, $record );
				}
			}

			// Set the flag to indicate that the data has been migrated.
			update_option( $option, true );
		}
	}

	/**
	 * Retrieves the column names from a given database table.
	 *
	 * @param string $table_name The name of the table (with prefix if necessary).
	 * @return array An array of column names.
	 */
	public static function get_table_column_names( $table_name ): array {
		global $wpdb;

		// Prepare the SQL query to get columns from the table.
		$sql = $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name );

		// Execute the query and get the results.
		$results = $wpdb->get_results( $sql );

		// Initialize an array to hold the column names.
		$column_names = array();

		// Loop through the results and extract the column names.
		if ( ! empty( $results ) ) {
			foreach ( $results as $column ) {
				$column_names[] = $column->Field;
			}
		}

		return $column_names;
	}
}
