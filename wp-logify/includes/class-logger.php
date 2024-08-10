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
	 * @param ?object          $wp_object   The object the event is about.
	 * @param ?array           $eventmetas  The event metadata.
	 * @param ?array           $properties  The event properties.
	 * @param null|WP_User|int $acting_user The use object or ID of the user who performed the action, or null for the current user.
	 * @throws InvalidArgumentException If the object type is invalid.
	 */
	public static function log_event(
		string $event_type,
		?object $wp_object,
		?array $eventmetas = null,
		?array $properties = null,
		null|WP_User|int $acting_user = null
	) {
		// If the event is about an object deletion, this is where we'd store the details of the
		// deleted object in the database. We want to do it before user checking so every object
		// deletion is tracked regardless of who did it. Then we will definitely have the old name.

		// If the acting user isn't specifid, use the current user.
		if ( $acting_user === null ) {
			$acting_user = wp_get_current_user();
		} elseif ( is_int( $acting_user ) ) {
			// If only the user ID for the acting user is specified, load the user object.
			$acting_user = User_Manager::load( $acting_user );
		}

		// If we don't have a user (i.e. they're anonymous), we don't need to log the event.
		if ( empty( $acting_user->ID ) ) {
			return;
		}

		// If we aren't tracking this user's role, we don't need to log the event.
		// This shouldn't happen; it should be checked earlier.
		if ( ! User_Manager::user_has_role( $acting_user, Plugin_Settings::get_roles_to_track() ) ) {
			return;
		}

		// Get the object reference.
		if ( $wp_object === null || $wp_object instanceof Object_Reference ) {
			$object_ref = $wp_object;
		} else {
			$object_ref = Object_Reference::new_from_wp_object( $wp_object );
		}

		// Get the core properties.
		if ( $object_ref instanceof Object_Reference ) {
			$object_props = $object_ref->get_core_properties();
		} else {
			$object_props = array();
		}

		// Copy across the properties we received.
		if ( ! empty( $properties ) ) {
			foreach ( $properties as $prop ) {
				Property::update_array_from_prop( $object_props, $prop );
			}
		}

		// Construct the new Event object.
		$event                = new Event();
		$event->when_happened = DateTimes::current_datetime();
		$event->user_id       = $acting_user->ID;
		$event->user_name     = User_Manager::get_name( $acting_user );
		$event->user_role     = implode( ', ', $acting_user->roles );
		$event->user_ip       = User_Manager::get_ip();
		$event->user_location = User_Manager::get_location( $event->user_ip );
		$event->user_agent    = User_Manager::get_user_agent();
		$event->event_type    = $event_type;
		$event->object_type   = $object_ref?->type;
		$event->object_id     = $object_ref?->id;
		$event->object_name   = $object_ref?->name;
		$event->eventmetas    = empty( $eventmetas ) ? null : $eventmetas;
		$event->properties    = empty( $object_props ) ? null : $object_props;

		// Save the object.
		$ok = Event_Repository::save( $event );

		debug( 'EVENT LOGGED: ' . $event_type );

		if ( ! $ok ) {
			debug( 'Event insert failed.', func_get_args() );
		}

		return $ok;
	}
}
