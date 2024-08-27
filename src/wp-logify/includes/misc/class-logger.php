<?php
/**
 * Contains the Logger class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use InvalidArgumentException;
use WP_User;

/**
 * Class WP_Logify\Logger
 *
 * This class is responsible for logging events to the database.
 */
class Logger {

	/**
	 * The valid object types for which events can be logged.
	 */
	public const VALID_OBJECT_TYPES = array( 'comment', 'core', 'option', 'plugin', 'post', 'taxonomy', 'term', 'theme', 'user' );

	/**
	 * The current events being logged.
	 *
	 * @var Event[]
	 */
	public static array $current_events = array();

	/**
	 * Logs an event to the database.
	 *
	 * @param string            $event_type  The type of event.
	 * @param null|object|array $wp_object   The WP object the event is about, or an array for plugins.
	 * @param ?array            $eventmetas  The event metadata.
	 * @param ?array            $properties  The event properties.
	 * @param null|WP_User|int  $acting_user The use object or ID of the user who performed the action, or null for the current user.
	 * @return bool True if the event was logged successfully, false otherwise.
	 * @throws InvalidArgumentException If the object type is invalid.
	 */
	public static function log_event(
		string $event_type,
		null|object|array $wp_object,
		?array $eventmetas = null,
		?array $properties = null,
		null|WP_User|int $acting_user = null
	): bool {
		// Create the new event.
		$event = Event::create( $event_type, $wp_object, $eventmetas, $properties, $acting_user );

		// Save the event to the database.
		$ok = Event_Repository::save( $event );

		// Log the result.
		if ( $ok ) {
			debug( 'EVENT LOGGED: ' . $event_type );
		} else {
			debug( 'Event logging failed. Here are the args:', func_get_args() );
		}

		// Return the result.
		return $ok;
	}
}
