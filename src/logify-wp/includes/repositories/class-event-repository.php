<?php
/**
 * Contains the Event_Repository class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DateTime;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class responsible for managing events in the database.
 */
class Event_Repository extends Repository {

	/**
	 * Do initialization tasks.
	 *
	 * @return void
	 */
	public static function init() {
		// Check if the object subtypes have been set already.
		$object_subtypes_set = get_option( 'logify_wp_object_subtypes_set', false );
		if ( ! $object_subtypes_set ) {
			self::maybe_add_column_object_subtype();
			self::fix_post_types();
			self::fix_taxonomies();
			update_option( 'logify_wp_object_subtypes_set', true );
		}
	}

	// =============================================================================================
	// CRUD methods.

	/**
	 * Load an Event from the database by ID.
	 *
	 * @param int $event_id The ID of the event.
	 * @return ?Event The Event object, or null if not found.
	 */
	public static function load( int $event_id ): ?Event {
		global $wpdb;

		$record = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %d', self::get_table_name(), $event_id ),
			ARRAY_A
		);

		// If the record is not found, return null.
		if ( ! $record ) {
			return null;
		}

		// Construct the new Event object.
		$event = self::record_to_object( $record );

		// Load the properties.
		$event->properties = Property_Repository::load_by_event_id( $event->id );

		// Load the eventmetas.
		$event->eventmetas = Eventmeta_Repository::load_by_event_id( $event->id );

		// Load the notes.
		$event->eventnotes = Note_Repository::load_by_event_id( $event->id );

		return $event;
	}

	/**
	 * Save an Event object to the database.
	 *
	 * If the object has an ID, it will be updated. Otherwise, it will be inserted.
	 *
	 * If inserting, and the insert is successful, the entity's ID property will be set.
	 *
	 * Using transactions here because the overall operation requires a number of SQL commands.
	 * If one fails, it's probably best to rollback the whole thing.
	 *
	 * @param object $event The entity to update or insert.
	 * @return bool True on success, false on failure.
	 * @throws InvalidArgumentException If the entity is not an instance of Event.
	 */
	public static function save( object $event ): bool {
		global $wpdb;

		// Check entity type.
		if ( ! $event instanceof Event ) {
			throw new InvalidArgumentException( esc_html( 'Entity must be an instance of Event.' ) );
		}

		// Check if we're inserting or updating.
		$inserting = empty( $event->id );

		// Start a transaction.
		$wpdb->query( 'START TRANSACTION' );

		// Update or insert the events record.
		$record  = self::object_to_record( $event );
		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
		if ( $inserting ) {
			// Do the insert.
			$ok = $wpdb->insert( self::get_table_name(), $record, $formats ) !== false;

			// If the new record was inserted ok, update the Event object with the new ID.
			if ( $ok ) {
				$event->id = $wpdb->insert_id;
			}
		} else {
			// Do the update.
			$ok = $wpdb->update( self::get_table_name(), $record, array( 'event_id' => $event->id ), $formats, array( '%d' ) ) !== false;
		}

		// Rollback and return on error.
		if ( ! $ok ) {
			Debug::error( 'Database error', $wpdb->last_query, $wpdb->last_error );
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Update the properties table.
		$ok = self::save_properties( $event );

		// Rollback and return on error.
		if ( ! $ok ) {
			Debug::error( 'Database error', $wpdb->last_query, $wpdb->last_error );
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Update the eventmetas table.
		$ok = self::save_eventmetas( $event );

		// Rollback and return on error.
		if ( ! $ok ) {
			Debug::error( 'Database error', $wpdb->last_query, $wpdb->last_error );
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// Commit the transaction.
		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Delete an event record by ID.
	 *
	 * @param int $event_id The ID of the event record to delete.
	 * @return bool True on success, false on failure.
	 * @throws RuntimeException If there is an error deleting the record.
	 */
	public static function delete( int $event_id ): bool {
		global $wpdb;

		// If the event ID is 0 or null, can't delete anything.
		if ( ! $event_id ) {
			return false;
		}

		// Delete the event record.
		$result = $wpdb->delete( self::get_table_name(), array( 'event_id' => $event_id ), array( '%d' ) );

		// Check for error.
		if ( $result === false ) {
			throw new RuntimeException( esc_html( esc_html( "Error deleting event record $event_id" ) ) );
		}

		// Delete the property records.
		Property_Repository::delete_by_event_id( $event_id );

		// Delete the eventmeta records.
		Eventmeta_Repository::delete_by_event_id( $event_id );

		// Delete the notes records
		Note_Repository::delete_by_event_id( $event_id );

		// Return success.
		return (bool) $result;
	}

	// =============================================================================================
	// Methods for loading and saving properties and eventmetas.

	/**
	 * Update the properties table.
	 *
	 * @param Event $event The event object.
	 * @return bool True on success, false on failure.
	 */
	public static function save_properties( Event $event ): bool {
		global $wpdb;

		// Get all properties currently attached to this event in the database.
		$prop_table = Property_Repository::get_table_name();
		$records    = $wpdb->get_results(
			$wpdb->prepare( 'SELECT prop_id, prop_key FROM %i WHERE event_id = %d', $prop_table, $event->id ),
			ARRAY_A
		);

		// Delete any we don't need anymore.
		foreach ( $records as $record ) {
			if ( ! $event->has_prop( $record['prop_key'] ) ) {
				$del_result = $wpdb->delete( $prop_table, array( 'prop_id' => $record['prop_id'] ), '%d' );
				if ( $del_result === false ) {
					Debug::error( 'Error deleting property record.' );
				}
			}
		}

		// If we have any properties, insert new records.
		if ( ! empty( $event->properties ) ) {
			foreach ( $event->properties as $prop ) {
				// Ensure the event_id is set in the property object.
				$prop->event_id = $event->id;

				// Save the property record.
				$ok = Property_Repository::save( $prop );

				// Return on error.
				if ( ! $ok ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Save metadata for an event.
	 *
	 * @param Event $event The event object.
	 * @return bool True on success, false on failure.
	 */
	public static function save_eventmetas( Event $event ): bool {
		global $wpdb;

		// Get all eventmetas currently attached to this event in the database.
		$meta_table = Eventmeta_Repository::get_table_name();
		$records    = $wpdb->get_results(
			$wpdb->prepare( 'SELECT eventmeta_id, meta_key FROM %i WHERE event_id = %d', $meta_table, $event->id ),
			ARRAY_A
		);

		// Delete any we don't need anymore.
		foreach ( $records as $record ) {
			if ( ! $event->has_meta( $record['meta_key'] ) ) {
				$del_result = $wpdb->delete( $meta_table, array( 'eventmeta_id' => $record['eventmeta_id'] ), '%d' );
				if ( $del_result === false ) {
					Debug::error( 'Error deleting eventmeta record.' );
				}
			}
		}

		// Save current eventmetas.
		if ( ! empty( $event->eventmetas ) ) {
			foreach ( $event->eventmetas as $eventmeta ) {
				// Ensure the event_id is set in the eventmeta object.
				$eventmeta->event_id = $event->id;

				// Save the object.
				$ok = Eventmeta_Repository::save( $eventmeta );

				// Rollback and return on error.
				if ( ! $ok ) {
					return false;
				}
			}
		}

		return true;
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
		return $wpdb->prefix . 'logify_wp_events';
	}

	/**
	 * Create the table used to store log events.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            when_happened datetime NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            user_name varchar(255) NOT NULL,
            user_role varchar(255) NOT NULL,
            user_ip varchar(40) NULL,
            user_location varchar(255) NULL,
            user_agent varchar(255) NULL,
            event_type varchar(255) NOT NULL,
            object_type varchar(10) NULL,
            object_subtype varchar(50) NULL,
            object_key varchar(50) NULL,
            object_name varchar(100) NULL,
            PRIMARY KEY  (event_id),
            KEY user_id (user_id)
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
	 * Event constructor.
	 *
	 * @param array $record The database record as an associative array.
	 * @return Event The new Event object.
	 */
	public static function record_to_object( array $record ): Event {
		$event                 = new Event();
		$event->id             = (int) $record['event_id'];
		$event->when_happened  = DateTimes::create_datetime( $record['when_happened'] );
		$event->user_id        = (int) $record['user_id'];
		$event->user_name      = $record['user_name'];
		$event->user_role      = $record['user_role'];
		$event->user_ip        = $record['user_ip'];
		$event->user_location  = $record['user_location'];
		$event->user_agent     = $record['user_agent'];
		$event->event_type     = $record['event_type'];
		$event->object_type    = $record['object_type'];
		$event->object_subtype = $record['object_subtype'];
		$event->object_key     = Types::process_database_value( 'object_key', $record['object_key'] );
		$event->object_name    = $record['object_name'];
		return $event;
	}

	/**
	 * Converts an Event object to a data array for saving to the database.
	 *
	 * The event_id property isn't included, as it isn't required for the insert or update operations.
	 *
	 * @param Event $event The Event object.
	 * @return array The database record as an associative array.
	 */
	public static function object_to_record( Event $event ): array {
		return array(
			'when_happened'  => DateTimes::format_datetime_mysql( $event->when_happened ),
			'user_id'        => $event->user_id,
			'user_name'      => $event->user_name,
			'user_role'      => $event->user_role,
			'user_ip'        => $event->user_ip,
			'user_location'  => $event->user_location,
			'user_agent'     => $event->user_agent,
			'event_type'     => $event->event_type,
			'object_type'    => $event->object_type,
			'object_subtype' => $event->object_subtype,
			'object_key'     => $event->object_key,
			'object_name'    => $event->object_name,
		);
	}

	// =============================================================================================
	// Methods for filtering events.

	/**
	 * Get the earliest date in the events table.
	 *
	 * @return ?DateTime
	 */
	public static function get_earliest_date(): ?DateTime {
		global $wpdb;
		$min_date = $wpdb->get_var(
			$wpdb->prepare( 'SELECT MIN(when_happened) FROM %i', self::get_table_name() )
		);
		return $min_date ? DateTimes::create_datetime( $min_date ) : null;
	}

	/**
	 * Get the latst date in the events table.
	 *
	 * @return ?DateTime
	 */
	public static function get_latest_date(): ?DateTime {
		global $wpdb;
		$max_date = $wpdb->get_var(
			$wpdb->prepare( 'SELECT MAX(when_happened) FROM %i', self::get_table_name() )
		);
		return $max_date ? DateTimes::create_datetime( $max_date ) : null;
	}

	// =============================================================================================
	// Methods for setting the object subtypes.

	/**
	 * Add the object_subtypes column to the events table, if it doesn't already exist.
	 */
	private static function maybe_add_column_object_subtype() {
		global $wpdb;

		// Check if the column exists
		$column_exists = $wpdb->get_results(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', self::get_table_name(), 'object_subtype' ),
			ARRAY_A
		);

		// If the column doesn't exist, add it
		if ( empty( $column_exists ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$wpdb->query(
				$wpdb->prepare( 'ALTER TABLE %i ADD object_subtype varchar(50)', self::get_table_name() )
			);
		}
	}

	/**
	 * Look through the events table for any events that have an object type of 'post' but an
	 * object_subtype of NULL, and set the object_subtype.
	 */
	public static function fix_post_types() {
		global $wpdb;

		// Check in the events table for any events with an object_type of 'post' and a object_subtype of null.
		$records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_id, object_key
				FROM %i
				WHERE object_type = 'post' and object_subtype IS null",
				self::get_table_name()
			),
			ARRAY_A
		);
		foreach ( $records as $record ) {
			// Get the post.
			$post = Post_Utility::load( $record['object_key'] );

			// Get the post type.
			$post_type = null;
			if ( $post ) {
				$post_type = $post->post_type;
			} else {
				// Look in the properties.
				$event = self::load( $record['event_id'] );
				if ( $event->has_prop( 'post_type' ) ) {
					$post_type = $event->get_prop_val( 'post_type' );
				}
			}

			// Set the object subtype to the post type.
			if ( $post_type ) {
				$wpdb->query(
					$wpdb->prepare(
						'UPDATE %i SET object_subtype = %s WHERE event_id = %d',
						self::get_table_name(),
						$post_type,
						$record['event_id']
					)
				);
			}
		}
	}

	/**
	 * Look through the events table for any events that have an object type of 'term' but an
	 * object_subtype of NULL, and set the object_subtype.
	 */
	public static function fix_taxonomies() {
		global $wpdb;

		// Check in the events table for any events with an object_type of 'term' and a object_subtype of null.
		$records = $wpdb->get_results(
			$wpdb->prepare( "SELECT event_id, object_key FROM %i WHERE object_type = 'term' and object_subtype IS null", self::get_table_name() ),
			ARRAY_A
		);
		foreach ( $records as $record ) {
			// Get the term.
			$term = Term_Utility::load( $record['object_key'] );

			// Get the taxonomy.
			$taxonomy = null;
			if ( $term ) {
				$taxonomy = $term->taxonomy;
			} else {
				// Look in the properties.
				$event = self::load( $record['event_id'] );
				if ( $event->has_prop( 'taxonomy' ) ) {
					$taxonomy = $event->get_prop_val( 'taxonomy' );
				}
			}

			// Set the object subtype to the taxonomy.
			if ( $taxonomy ) {
				$wpdb->query(
					$wpdb->prepare( 'UPDATE %i SET object_subtype = %s WHERE event_id = %d', self::get_table_name(), $taxonomy, $record['event_id'] )
				);
			}
		}
	}

	// =============================================================================================
	// Methods for getting values for search filters.

	/**
	 * Get the post types that have been used in the events table.
	 *
	 * @return array An array with the post types as keys and the singular names as values.
	 */
	public static function get_post_types(): array {
		global $wpdb;

		// Get the post types.
		$post_types = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT object_subtype
				FROM %i
				WHERE object_type = 'post' and object_subtype IS NOT null",
				self::get_table_name()
			)
		);

		// Construct the array of names.
		$result = array();
		foreach ( $post_types as $post_type ) {
			$result[ $post_type ] = Post_Utility::get_post_type_singular_name( $post_type );
		}
		asort( $result );
		return $result;
	}

	/**
	 * Get the taxonomies that have been used in the events table.
	 *
	 * @return array An array with the taxonomies as keys and the singular names as values.
	 */
	public static function get_taxonomies(): array {
		global $wpdb;

		// Get the taxonomies.
		$taxonomies = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT object_subtype
				FROM %i
				WHERE object_type = 'term' and object_subtype IS NOT null",
				self::get_table_name()
			)
		);

		// Construct the array of names.
		$result = array();
		foreach ( $taxonomies as $taxonomy ) {
			// Get the taxonomy name.
			$result[ $taxonomy ] = Taxonomy_Utility::get_singular_name( $taxonomy );
		}
		asort( $result );
		return $result;
	}

	/**
	 * Get all the event types in the events table.
	 *
	 * @return array An array of event types..
	 */
	public static function get_event_types(): array {
		global $wpdb;

		// Get the event types.
		return $wpdb->get_col(
			$wpdb->prepare( 'SELECT DISTINCT event_type FROM %i ORDER BY event_type', self::get_table_name() )
		);
	}

	/**
	 * Get all the users in the events table.
	 */
	public static function get_users(): array {
		global $wpdb;

		// Get the acting users.
		$users = $wpdb->get_results(
			$wpdb->prepare( 'SELECT DISTINCT user_id, user_name FROM %i ORDER BY event_id DESC', self::get_table_name() ),
			ARRAY_A
		);

		// Construct the array of user IDs and names.
		$result = array();
		foreach ( $users as $user ) {
			$user_id = $user['user_id'];

			// Check if we already have this user. It's possible, if their display name has changed.
			if ( key_exists( $user_id, $result ) ) {
				continue;
			}

			if ( $user_id ) {
				// Get the user's name.
				$name = User_Utility::get_name( $user_id );

				// If we can't get the name (e.g. they were deleted), use the user_name from the events table.
				if ( $name === null ) {
					$name = $user['user_name'];
				}
			} else {
				$name = 'Unknown';
			}

			// Add the user to the result array.
			$result[ $user_id ] = $name;
		}

		// Sort the array by user name.
		asort( $result );

		return $result;
	}

	/**
	 * Get all the roles in the events table.
	 */
	public static function get_roles(): array {
		global $wpdb;

		// Get the roles.
		$roles = $wpdb->get_col(
			$wpdb->prepare( 'SELECT DISTINCT user_role FROM %i ORDER BY user_role', self::get_table_name() )
		);

		// Put 'none' at the start.
		if ( in_array( 'none', $roles ) ) {
			$roles = array_diff( $roles, array( 'none' ) );
			array_unshift( $roles, 'none' );
		}

		return $roles;
	}

	/**
	 * Get the most recent event caused by the current user. The event type can be specified.
	 *
	 * @param ?string $event_type The event type. If null, get the most recent event of any type.
	 * @return ?Event The most recent event matching the provided arguments.
	 */
	public static function get_most_recent_event( ?string $event_type = null, ?int $user_id = null ): ?Event {
		global $wpdb;

		// If we don't have a user ID, we can't get the most recent event they caused.
		if ( ! $user_id ) {
			return null;
		}

		if ( $event_type ) {
			// Get the most recent event of the given type, caused by this user.
			$event_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT event_id FROM %i WHERE user_id = %d AND event_type = %s ORDER BY when_happened DESC LIMIT 1',
					self::get_table_name(),
					$user_id,
					$event_type
				)
			);
		} else {
			// Get the most recent event caused by this user.
			$event_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT event_id FROM %i WHERE user_id = %d ORDER BY when_happened DESC LIMIT 1',
					self::get_table_name(),
					$user_id
				)
			);
		}

		// Return the event, if found.
		return $event_id ? self::load( $event_id ) : null;
	}
}
