<?php
/**
 * Contains the Database class.
 *
 * @package Logify_WP;
 */

namespace Logify_WP;

/**
 * Database stuff.
 */
class Database {

	/**
	 * Create all tables.
	 */
	public static function create_all_tables() {
		Event_Repository::create_table();
		Eventmeta_Repository::create_table();
		Property_Repository::create_table();
	}

	/**
	 * Drop all tables.
	 */
	public static function drop_all_tables() {
		Event_Repository::drop_table();
		Eventmeta_Repository::drop_table();
		Property_Repository::drop_table();
	}

	/**
	 * Truncate all tables.
	 */
	public static function truncate_all_tables() {
		Event_Repository::truncate_table();
		Eventmeta_Repository::truncate_table();
		Property_Repository::truncate_table();
	}
}
