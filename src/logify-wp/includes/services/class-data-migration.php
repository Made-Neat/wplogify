<?php
/**
 * Contains the Data_Migration class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class encapsulating data migration functionality.
 */
class Data_Migration {

	/**
	 * Initializes the class.
	 *
	 * @return void
	 */
	public static function init() {
		// Add a hook to migrate data.
		add_action( 'admin_post_logify_wp_migrate_data', array( __CLASS__, 'migrate_data' ), 10, 0 );
	}

	/**
	 * Migrates data from the old WP Logify plugin to the new Logify WP plugin.
	 *
	 * @return void
	 */
	public static function migrate_data() {
		// Fix the altered column name in the old wp_logify_properties table.
		self::repair_wp_logify_properties_table();

		// // Migrate the data.
		self::migrate_table( 'events' );
		self::migrate_table( 'eventmeta', array( 'meta_value' ) );
		self::migrate_table( 'properties', array( 'val', 'new_val' ) );

		// Drop an old table.
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'wp_logify_event_meta' ) );
		Debug::info( 'Dropped wp_logify_event_meta table.' );

		wp_safe_redirect( admin_url( 'admin.php?page=logify-wp-settings&migrated=success' ) );
		exit;
	}

	/**
	 * Updates the wp_wp_logify_properties table by renaming the primary key column to prop_id.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public static function repair_wp_logify_properties_table() {
		global $wpdb;

		Debug::info( 'repair_wp_logify_properties_table' );

		// Get the correct table name with the WordPress table prefix.
		$table_name = $wpdb->prefix . 'wp_logify_properties';

		// Check if the table exists.
		$sql          = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		$table_exists = $wpdb->get_var( $sql );

		Debug::info( "Table exists: $table_exists" );

		if ( $table_exists !== $table_name ) {
			// Table does not exist, so nothing to do.
			Debug::info( 'Table does not exist' );
			return;
		}

		// Check the current structure of the table.
		$sql     = $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name );
		$columns = $wpdb->get_results( $sql );

		Debug::info( 'Columns: ' . print_r( $columns, true ) );

		$has_property_id = false;
		$has_prop_id     = false;
		$primary_key     = '';

		foreach ( $columns as $column ) {
			if ( $column->Field === 'property_id' ) {
				$has_property_id = true;
				if ( $column->Key === 'PRI' ) {
					$primary_key = 'property_id';
				}
			}
			if ( $column->Field === 'prop_id' ) {
				$has_prop_id = true;
				if ( $column->Key === 'PRI' ) {
					$primary_key = 'prop_id';
				}
			}
		}

		// Check if the primary key is 'property_id' and 'prop_id' does not exist.
		if ( $has_property_id && ! $has_prop_id && $primary_key === 'property_id' ) {
			Debug::info( 'Renaming primary key' );

			// Execute the SQL query to rename the column.
			$wpdb->query( "ALTER TABLE $table_name CHANGE `property_id` `prop_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT" );
		}
	}

	/**
	 * Migrate data from the old wp-logify table, if present and not done already.
	 *
	 * @param string $table_key        The table key (e.g. 'events', 'properties', or 'eventmeta').
	 * @param array  $fields_to_update The fields that require a namespace update.
	 * @return void
	 */
	public static function migrate_table( string $table_key, array $fields_to_update = array() ): void {

		Debug::info( 'migrate_table', $table_key );

		global $wpdb;

		// Check if the new table exists.
		$new_table_name   = "{$wpdb->prefix}logify_wp_$table_key";
		$new_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$new_table_name'" ) === $new_table_name;

		if ( ! $new_table_exists ) {
			Debug::info( 'new table does not exist', $new_table_name );
			return;
		}

		// Truncate the new table.
		$wpdb->query( "TRUNCATE TABLE $new_table_name" );

		// Check if the old table exists.
		$old_table_name   = "{$wpdb->prefix}wp_logify_$table_key";
		$old_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$old_table_name'" ) === $old_table_name;

		if ( ! $old_table_exists ) {
			Debug::info( 'old table does not exist', $old_table_name );
			return;
		}

		// Select all records from the old table.
		$old_records = $wpdb->get_results( "SELECT * FROM $old_table_name", ARRAY_A );

		Debug::info( 'fields to update', $fields_to_update );

		// Iterate through the records, copying them to the new table.
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

		Debug::info( "Migration of $table_key table complete" );
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
