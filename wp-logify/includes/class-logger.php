<?php
/**
 * Contains the Logger class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class WP_Logify\Logger
 *
 * This class is responsible for logging events to the database.
 */
class Logger {

	/**
	 * The valid object types for which events can be logged.
	 */
	public const VALID_OBJECT_TYPES = array( 'post', 'user', 'category', 'plugin', 'theme' );

	/**
	 * Initializes the class by adding WordPress actions.
	 */
	public static function init() {
		// Ensure table is created on plugin activation.
		self::create_table();
	}

	/**
	 * Create the table used to store log events.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            date_time datetime NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            user_role varchar(255) NOT NULL,
            user_ip varchar(100) NOT NULL,
            user_location varchar(255) NULL,
            user_agent varchar(255) NULL,
            event_type varchar(255) NOT NULL,
            object_type varchar(20) NULL,
            object_id varchar(20) NULL,
            object_name varchar(255) NULL,
            details text NULL,
            changes text NULL,
            PRIMARY KEY (ID)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get the name of the table used to store log events.
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wp_logify_events';
	}

	/**
	 * Logs an event to the database.
	 *
	 * @param string          $event_type  The type of event.
	 * @param ?string         $object_type The type of object associated with the event.
	 * @param null|int|string $object_id   The ID of the object associated with the event. This can
	 *                                     be a string for non-integer object identifiers, such as
	 *                                     the machine name of a theme or plugin.
	 * @param ?string         $object_name The name of the object associated with the event.
	 * @param ?array          $details     Additional details about the event.
	 *
	 * @throws InvalidArgumentException If the object type is invalid.
	 */
	public static function log_event(
		string $event_type,
		?string $object_type = null,
		null|int|string $object_id = null,
		?string $object_name = null,
		?array $details = null,
		?array $changes = null,
	) {
		global $wpdb;

		// Check object type is valid.
		if ( $object_type !== null && ! in_array( $object_type, self::VALID_OBJECT_TYPES, true ) ) {
			throw new InvalidArgumentException( 'Invalid object type.' );
		}

		// Get the datetime.
		$date_time = DateTimes::format_datetime_mysql( DateTimes::current_datetime() );

		// Get the current user.
		$user = wp_get_current_user();

		// If the current user could not be loaded, this may be a login or logout event.
		if ( $user->ID === 0 && $object_type === 'user' ) {
			debug_log( 'User not found, trying to get user by ID', $object_id );
			$user = get_userdata( $object_id );
		}

		// This shouldn't happen.
		if ( empty( $user ) ) {
			throw new RuntimeException( 'User not found' );
		}

		// Collect other user info.
		$user_id       = $user->ID;
		$user_role     = implode( ', ', array_map( 'sanitize_text_field', $user->roles ) );
		$user_ip       = Users::get_user_ip();
		$user_location = Users::get_user_location( $user_ip );
		$user_agent    = Users::get_user_agent();

		// Encode the event details as JSON.
		if ( $details !== null ) {
			$details_json = wp_json_encode( $details );
			if ( ! $details_json ) {
				throw new InvalidArgumentException( 'Failed to encode details as JSON.' );
			}
		} else {
			$details_json = null;
		}

		// Encode the object changes as JSON.
		if ( $changes !== null ) {
			$changes_json = wp_json_encode( $changes );
			if ( ! $changes_json ) {
				throw new InvalidArgumentException( 'Failed to encode changes as JSON.' );
			}
		} else {
			$changes_json = null;
		}

		// Insert the new record.
		$wpdb->insert(
			self::get_table_name(),
			array(
				'date_time'     => $date_time,
				'user_id'       => $user_id,
				'user_role'     => $user_role,
				'user_ip'       => $user_ip,
				'user_location' => $user_location,
				'user_agent'    => $user_agent,
				'event_type'    => $event_type,
				'object_type'   => $object_type,
				'object_id'     => $object_id,
				'object_name'   => $object_name,
				'details'       => $details_json,
				'changes'       => $changes_json,
			)
		);
	}
}
