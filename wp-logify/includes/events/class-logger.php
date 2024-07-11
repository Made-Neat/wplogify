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
	public const VALID_OBJECT_TYPES = array( 'post', 'user', 'term', 'plugin', 'theme' );

	/**
	 * Logs an event to the database.
	 *
	 * @param string  $event_type  The type of event.
	 * @param ?string $object_type The type of obejct, e.g. 'post', 'user', 'term'.
	 * @param ?int    $object_id   The ID of the object.
	 * @param ?string $object_name The name of the object (in case it gets deleted).
	 * @param ?array  $event_meta  Metadata relating to the event.
	 * @param ?array  $properties  Properties of the relevant object.
	 *
	 * @throws InvalidArgumentException If the object type is invalid.
	 */
	public static function log_event(
		string $event_type,
		?string $object_type = null,
		?int $object_id = null,
		?string $object_name = null,
		?array $event_meta = null,
		?array $properties = null,
	) {
		// Get the current user.
		$user = wp_get_current_user();

		// If the current user could not be loaded, this may be a login or logout event.
		// In such cases, we should be able to get the user from the object information.
		if ( ( empty( $user ) || empty( $user->ID ) ) && $object_type === 'user' ) {
			$user = get_userdata( $object_id );
		}

		// If we still don't have a known user (i.e. it's an anonymous user), we don't need to log
		// the event.
		if ( empty( $user ) || empty( $user->ID ) ) {
			return;
		}

		// Check if we're interested in tracking this user's actions.
		if ( ! Users::user_has_role( $user, Settings::get_roles_to_track() ) ) {
			return;
		}

		// Construct the new Event object.
		$event                = new Event();
		$event->date_time     = DateTimes::current_datetime( 'UTC' );
		$event->user_id       = $user->ID;
		$event->user_name     = Users::get_name( $user );
		$event->user_role     = implode( ', ', $user->roles );
		$event->user_ip       = Users::get_ip();
		$event->user_location = Users::get_location( $event->user_ip );
		$event->user_agent    = Users::get_user_agent();
		$event->event_type    = $event_type;
		$event->object_type   = $object_type;
		$event->object_id     = $object_id;
		$event->object_name   = $object_name;
		$event->event_meta    = $event_meta;
		$event->properties    = $properties;

		// Save the object.
		$ok = Event_Repository::save( $event );

		if ( ! $ok ) {
			debug( 'Event insert failed.', func_get_args() );
		}

		return $ok;
	}
}
