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
	 *
	 * @var string[]
	 */
	public const VALID_OBJECT_TYPES = array( 'comment', 'core', 'option', 'plugin', 'post', 'taxonomy', 'term', 'theme', 'user', 'widget' );

	/**
	 * The maximum length of an object name.
	 *
	 * @var int
	 */
	public const MAX_OBJECT_NAME_LENGTH = 50;

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

		// An event won't be created if the user is not logged in or if they don't have a role we're tracking.
		if ( ! $event ) {
			return false;
		}

		// Remember the event.
		self::$current_events[] = $event;

		// Save the event to the database and return the result.
		return $event->save();
	}

	/**
	 * Finds the first current event matching the provided event type.
	 *
	 * @param string $event_type The type of event.
	 * @return ?Event The event object, or null if not found.
	 */
	public static function get_current_event_by_event_type( string $event_type ): ?Event {
		foreach ( self::$current_events as $event ) {
			if ( $event->event_type === $event_type ) {
				return $event;
			}
		}
		return null;
	}
}
