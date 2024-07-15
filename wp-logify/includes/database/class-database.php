<?php
/**
 * Contains the Database class.
 *
 * @package WP_Logify;
 */

namespace WP_Logify;

/**
 * Database stuff.
 */
class Database {

	public static function create_all_tables() {
		Event_Repository::create_table();
		Event_Meta_Repository::create_table();
		Property_Repository::create_table();
	}

	public static function drop_all_tables() {
		Event_Repository::drop_table();
		Event_Meta_Repository::drop_table();
		Property_Repository::drop_table();
	}

	public static function truncate_all_tables() {
		Event_Repository::truncate_table();
		Event_Meta_Repository::truncate_table();
		Property_Repository::truncate_table();
	}
}
