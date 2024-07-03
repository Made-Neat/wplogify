<?php
/**
 * Contains the Logger class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use InvalidArgumentException;

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

		// Get the current user.
		$user = wp_get_current_user();

		// If the current user could not be loaded, this may be a login or logout event.
		// In such cases, we should be able to get the user from the object information.
		if ( $user->ID === 0 && $object_type === 'user' ) {
			$user = get_userdata( $object_id );
		}

		// If we still don't have a known user (i.e. it's an anonymous user), we don't need to log
		// the event.
		if ( ! $user || $user->ID === 0 ) {
			return;
		}

		// Check if we're interested in tracking this user's actions.
		if ( ! Users::user_has_role( $user, Settings::get_roles_to_track() ) ) {
			return;
		}

		// Construct the Event object.
		$event                = new Event();
		$event->date_time     = DateTimes::current_datetime();
		$event->user_id       = $user->ID;
		$event->user_role     = implode( ', ', $user->roles );
		$event->user_ip       = Users::get_user_ip();
		$event->user_location = Users::get_user_location( $event->user_ip );
		$event->user_agent    = Users::get_user_agent();
		$event->event_type    = $event_type;
		$event->object_type   = $object_type;
		$event->object_id     = $object_id;
		$event->object_name   = $object_name;
		$event->details       = $details;
		$event->changes       = $changes;

		// Insert the new record.
		EventRepository::save( $event );
	}
}
