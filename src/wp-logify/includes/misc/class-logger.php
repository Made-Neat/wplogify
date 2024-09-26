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
	 * This list is in alphabetical order by value.
	 *
	 * @var string[]
	 */
	public const VALID_OBJECT_TYPES = array(
		'comment'     => 'Comment',
		'plugin'      => 'Plugin',
		'post'        => 'Post',
		'option'      => 'Setting',
		'taxonomy'    => 'Taxonomy',
		'term'        => 'Term',
		'theme'       => 'Theme',
		'translation' => 'Translation',
		'user'        => 'User',
		'widget'      => 'Widget',
		'core'        => 'WP Core',
	);

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
	 * Initialize the class.
	 */
	public static function init() {
		// Register shutdown function.
		add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 10, 0 );
	}

	/**
	 * Logs an event to the database.
	 *
	 * @param string                   $event_type  The type of event.
	 * @param null|object|array|string $wp_object   The object or object type the event is about.
	 * @param ?array                   $eventmetas  The event metadata.
	 * @param ?array                   $properties  The event properties.
	 * @param null|int|WP_User         $acting_user The user who performed the action. This can be a user ID or WP_User object, or null for the current user.
	 * @return bool True if the event was logged successfully, false otherwise.
	 * @throws InvalidArgumentException If the object type is invalid.
	 */
	public static function log_event(
		string $event_type,
		null|object|array|string $wp_object = null,
		?array $eventmetas = null,
		?array $properties = null,
		null|int|WP_User $acting_user = null
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


	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown() {
		// Save any unsaved events.
		foreach ( self::$current_events as $event ) {
			if ( $event->is_new() ) {
				debug( "Saving '{$event->event_type}' event in Logger::on_shutdown" );
				$event->save();
			}
		}
	}
}
