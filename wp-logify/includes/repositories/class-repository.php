<?php
/**
 * Contains the Repository_Interface interface.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Interface WP_Logify\Repository_Interface
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
	 * Create the table used to store log events.
	 */
	abstract public static function create_table();

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
}
