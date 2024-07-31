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
	public const VALID_OBJECT_TYPES = array( 'post', 'user', 'term', 'plugin', 'theme' );

	/**
	 * Check if we're logging actions of the current user.
	 *
	 * @param ?string  $object_type The type of obejct, e.g. 'post', 'user', 'term'.
	 * @param ?int     $object_id   The ID of the object.
	 * @param ?WP_User &$current_user The current user or null if the user is anonymous.
	 * @return bool True if we're logging the current user's actions, false otherwise.
	 */
	public static function log_current_user( ?string $object_type = null, ?int $object_id = null, ?WP_User &$current_user ): bool {

		// Get the current user.
		$current_user = wp_get_current_user();

		// If the current user could not be loaded, this may be a login or logout event.
		// In such cases, we should be able to get the user from the object information.
		if ( empty( $current_user->ID ) && $object_type === 'user' ) {
			$current_user = get_userdata( $object_id );
		}

		// If we still don't have a known user (i.e. it's an anonymous user), we don't need to log
		// the event.
		if ( empty( $current_user->ID ) ) {
			$current_user = null;
			return false;
		}

		// Check if we're interested in tracking this user's actions.
		if ( ! Users::user_has_role( $current_user, Settings::get_roles_to_track() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Logs an event to the database.
	 *
	 * @param string          $event_type  The type of event.
	 * @param ?string         $object_type The type of obejct, e.g. 'post', 'user', 'term'.
	 * @param null|int|string $object_id   The object's identifier (int or string) or null if N/A.
	 * @param ?string         $object_name The name of the object (in case it gets deleted).
	 * @param ?array          $eventmetas  The event metadata.
	 * @param ?array          $properties  The event properties.
	 *
	 * @throws InvalidArgumentException If the object type is invalid.
	 */
	public static function log_event(
		string $event_type,
		?string $object_type = null,
		null|int|string $object_id = null,
		?string $object_name = null,
		?array $eventmetas = null,
		?array $properties = null,
	) {
		// Check if we're logging actions of the current user.
		$log_the_event = self::log_current_user( $object_type, $object_id, $current_user );
		if ( ! $log_the_event ) {
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
