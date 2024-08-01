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
	 * @param ?string          $object_type The type of obejct, e.g. 'post', 'user', 'term'.
	 * @param null|int|string  $object_id   The object's identifier (int or string) or null if N/A.
	 * @param ?string          $object_name The name of the object (in case it gets deleted).
	 * @param ?array           $eventmetas  The event metadata.
	 * @param ?array           $properties  The event properties.
	 * @param null|WP_User|int $current_user The user who performed the action (object or ID) or null to use the curernt user.
	 * @throws InvalidArgumentException If the object type is invalid.
	 */
	public static function log_event(
		string $event_type,
		?string $object_type = null,
		null|int|string $object_id = null,
		?string $object_name = null,
		?array $eventmetas = null,
		?array $properties = null,
		null|WP_User|int $current_user = null
	) {
		// If the acting user isn't specifid, use the current user.
		if ( $current_user === null ) {
			$current_user = wp_get_current_user();
		} elseif ( is_int( $current_user ) ) {
			// If only the user ID for the acting user is specified, load the user object.
			$current_user = get_userdata( $current_user );
		}

		// If we don't have a user (i.e. they're anonymous), we don't need to log the event.
		if ( empty( $current_user->ID ) ) {
			return;
		}

		// If we aren't tracking this user's role, we don't need to log the event.
		// This shouldn't happen; it should be checked earlier.
		if ( ! Users::user_has_role( $current_user, Settings::get_roles_to_track() ) ) {
			return;
		}

		// Construct the new Event object.
		$event                = new Event();
		$event->when_happened = DateTimes::current_datetime();
		$event->user_id       = $current_user->ID;
		$event->user_name     = Users::get_name( $current_user );
		$event->user_role     = implode( ', ', $current_user->roles );
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
