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
	public const VALID_OBJECT_TYPES = array( 'post', 'user', 'term', 'plugin', 'theme', 'setting', 'comment' );

	/**
	 * Logs an event to the database.
	 *
	 * @param string           $event_type  The type of event.
	 * @param ?string          $object_type The type of object, e.g. 'post', 'user', 'plugin', etc.
	 * @param ?int             $object_id   The object's ID or null if N/A.
	 * @param ?string          $object_name The name or title of the object.
	 * @param ?array           $eventmetas  The event metadata.
	 * @param ?array           $properties  The event properties.
	 * @param null|WP_User|int $acting_user The use object or ID of the user who performed the action, or null for the current user.
	 * @throws InvalidArgumentException If the object type is invalid.
	 */
	public static function log_event(
		string $event_type,
		?string $object_type = null,
		?int $object_id = null,
		?string $object_name = null,
		?array $eventmetas = null,
		?array $properties = null,
		null|WP_User|int $acting_user = null
	) {
		// If the acting user isn't specifid, use the current user.
		if ( $acting_user === null ) {
			$acting_user = wp_get_current_user();
		} elseif ( is_int( $acting_user ) ) {
			// If only the user ID for the acting user is specified, load the user object.
			$acting_user = get_userdata( $acting_user );
		}

		// If we don't have a user (i.e. they're anonymous), we don't need to log the event.
		if ( empty( $acting_user->ID ) ) {
			return;
		}

		// If we aren't tracking this user's role, we don't need to log the event.
		// This shouldn't happen; it should be checked earlier.
		if ( ! Users::user_has_role( $acting_user, Settings::get_roles_to_track() ) ) {
			return;
		}

		// Construct the new Event object.
		$event                = new Event();
		$event->when_happened = DateTimes::current_datetime();
		$event->user_id       = $acting_user->ID;
		$event->user_name     = Users::get_name( $acting_user );
		$event->user_role     = implode( ', ', $acting_user->roles );
		$event->user_ip       = Users::get_ip();
		$event->user_location = Users::get_location( $event->user_ip );
		$event->user_agent    = Users::get_user_agent();
		$event->event_type    = $event_type;
		$event->object_type   = $object_type;
		$event->object_id     = $object_id;
		$event->object_name   = $object_name;
		$event->eventmetas    = $eventmetas;
		$event->properties    = $properties;

		// Save the object.
		$ok = Event_Repository::save( $event );

		if ( ! $ok ) {
			debug( 'Event insert failed.', func_get_args() );
		}

		return $ok;
	}
}
