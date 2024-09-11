<?php
/**
 * Contains the User_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Error;
use WP_User;

/**
 * Class WP_Logify\User_Tracker
 *
 * Provides tracking of events related to users.
 */
class User_Tracker {

	/**
	 * The maximum break period in seconds. If there has been no activity for this period, we'll
	 * assume the user has left the site and is starting a new session when they return.
	 *
	 * @var int
	 */
	private const MAX_BREAK_PERIOD = 1200; // 20 minutes

	/**
	 * Array to remember properties between different events.
	 *
	 * @var array
	 */
	private static $properties = array();

	/**
	 * Array to remember metadata between different events.
	 *
	 * @var array
	 */
	private static $eventmetas = array();

	/**
	 * User profile update event.
	 *
	 * @var Event
	 */
	private static $profile_update_event;

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// User login.
		add_action( 'wp_login', array( __CLASS__, 'on_wp_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( __CLASS__, 'on_wp_login_failed' ), 10, 2 );

		// User logout.
		add_action( 'wp_logout', array( __CLASS__, 'on_wp_logout' ), 10, 1 );

		// User activity.
		add_action( 'wp_loaded', array( __CLASS__, 'on_wp_loaded' ) );

		// New user registration.
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 10, 2 );

		// User deletion.
		add_action( 'delete_user', array( __CLASS__, 'on_delete_user' ), 10, 3 );

		// User update.
		add_action( 'profile_update', array( __CLASS__, 'on_profile_update' ), 10, 3 );
		add_action( 'update_user_meta', array( __CLASS__, 'on_update_user_meta' ), 10, 4 );
		add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 10, 0 );
	}

	/**
	 * User login.
	 *
	 * @param string  $user_login The username of the user that logged in.
	 * @param WP_User $user       The WP_User object of the user that logged in.
	 */
	public static function on_wp_login( string $user_login, WP_User $user ) {
		Logger::log_event( 'User Login', $user, acting_user: $user );
	}

	/**
	 * Fires after a user login has failed.
	 *
	 * @param string   $username Username or email address.
	 * @param WP_Error $error    A WP_Error object with the authentication failure details.
	 */
	public static function on_wp_login_failed( string $username, WP_Error $error ) {
		// Create an object reference for the possibly unknown user.
		$user_data = User_Utility::get_user_data( $username );
		$event     = Event::create( 'Failed Login', $user_data['ref'], null, null, $user_data['ref'] );

		// Store the reason for the login failure in the event metadata.
		$event->set_meta( 'error_code', $error->get_error_code() );
		$event->set_meta( 'error_message', Strings::strip_tags( $error->get_error_message() ) );

		// Log the event.
		$event->save();
	}

	/**
	 * User logout.
	 *
	 * @param int $user_id The ID of the user that logged out.
	 */
	public static function on_wp_logout( int $user_id ) {
		$user = User_Utility::load( $user_id );
		Logger::log_event( 'User Logout', $user, acting_user: $user );
	}

	/**
	 * User registration.
	 *
	 * @param int   $user_id  The ID of the user being registered.
	 * @param array $userdata The data for the user that was registered.
	 */
	public static function on_user_register( int $user_id, array $userdata ) {
		Logger::log_event( 'User Registered', User_Utility::load( $user_id ) );
	}

	/**
	 * User deletion.
	 *
	 * @param int     $user_id  The ID of the user that was deleted.
	 * @param ?int    $reassign The ID of the user that the data was reassigned to.
	 * @param WP_User $user     The WP_User object of the user that was deleted.
	 */
	public static function on_delete_user( int $user_id, ?int $reassign, WP_User $user ) {
		global $wpdb;

		// Get the user's properties.
		$properties = User_Utility::get_properties( $user );

		// Get the posts authored by this user.
		$sql_posts = $wpdb->prepare( "SELECT ID FROM %i WHERE post_author = %d AND post_parent = 0 AND post_status != 'auto-draft'", $wpdb->posts, $user_id );
		$post_ids  = $wpdb->get_col( $sql_posts );
		$post_refs = array_map(
			fn ( $post_id ) => new Object_Reference( 'post', $post_id ),
			$post_ids
		);
		Eventmeta::update_array( self::$eventmetas, 'posts_authored', $post_refs );

		// Get the comments authored by this user.
		$sql_comments = $wpdb->prepare( 'SELECT comment_ID FROM %i WHERE user_id = %d', $wpdb->comments, $user_id );
		$comment_ids  = $wpdb->get_col( $sql_comments );
		$comment_refs = array_map(
			fn ( $comment_id ) =>new Object_Reference( 'comment', $comment_id ),
			$comment_ids
		);
		Eventmeta::update_array( self::$eventmetas, 'comments_authored', $comment_refs );

		// If the user's data is being reassigned, record that information in the eventmetas.
		if ( $reassign ) {
			Eventmeta::update_array( self::$eventmetas, 'content_reassigned_to', new Object_Reference( 'user', $reassign ) );
		}

		// Log the event.
		Logger::log_event( 'User Deleted', $user, self::$eventmetas, $properties );
	}

	/**
	 * User update.
	 *
	 * @param int     $user_id  The ID of the user being updated.
	 * @param WP_User $user     The WP_User object of the user before the update.
	 * @param array   $userdata The data for the user after the update.
	 */
	public static function on_profile_update( int $user_id, WP_User $user, array $userdata ) {
		global $wpdb;

		// Compare values and make note of any changes.
		foreach ( $user->data as $key => $value ) {

			// Process meta values into correct types.
			$old_val = Types::process_database_value( $key, $value );
			$new_val = Types::process_database_value( $key, $userdata[ $key ] );

			// If the value has changed, add the before and after values to the properties.
			if ( ! Types::are_equal( $old_val, $new_val ) ) {

				// Create the event if it doesn't already exist.
				if ( ! self::$profile_update_event ) {
					// Note, the event will not be created if the current user isn't logged in or if the
					// current user has a role that isn't being tracked.
					self::$profile_update_event = Event::create( 'User Updated', $user );

					// If no event was created, no need to log an event.
					if ( ! self::$profile_update_event ) {
						return;
					}
				}

				// Add the property.
				self::$profile_update_event->set_prop( $key, $wpdb->users, $old_val, $new_val );
			}
		}
	}

	/**
	 * User meta update.
	 *
	 * @param int    $meta_id    The ID of the meta data.
	 * @param int    $user_id    The ID of the user.
	 * @param string $meta_key   The key of the meta data.
	 * @param mixed  $meta_value The new value of the meta data.
	 */
	public static function on_update_user_meta( int $meta_id, int $user_id, string $meta_key, mixed $meta_value ) {
		global $wpdb;

		// Ignore changes to session tokens.
		if ( $meta_key === 'session_tokens' ) {
			return;
		}

		// Get the current value.
		$current_value = get_user_meta( $user_id, $meta_key );

		// In theory both values should be arrays or both should be scalars.
		if ( ! is_array( $meta_value ) && is_array( $current_value ) ) {
			$current_value = $current_value[0];
		}

		// Process meta values into correct types.
		$old_val = Types::process_database_value( $meta_key, $current_value );
		$new_val = Types::process_database_value( $meta_key, $meta_value );

		// If the value has changed, add the before and after values to the properties array.
		if ( ! Types::are_equal( $old_val, $new_val ) ) {

			// Create the event if it doesn't already exist.
			if ( ! self::$profile_update_event ) {
				// Note, the event will not be created if the current user isn't logged in or if the
				// current user has a role that isn't being tracked.
				$user_ref                   = new Object_Reference( 'user', $user_id );
				self::$profile_update_event = Event::create( 'User Updated', $user_ref );

				// If no event was created, no need to log an event.
				if ( ! self::$profile_update_event ) {
					return;
				}
			}

			// If we have an event, add the new property.
			self::$profile_update_event->set_prop( $meta_key, $wpdb->usermeta, $old_val, $new_val );
		}
	}

	/**
	 * User activity.
	 */
	public static function on_wp_loaded() {
		global $wpdb;

		// Ignore AJAX requests, as it's likely WP doing something and not the user.
		// The user has probably just left the web page open in the browser while they do something
		// else.
		if ( wp_doing_ajax() ) {
			return;
		}

		// Prepare some values.
		$user       = wp_get_current_user();
		$table_name = Event_Repository::get_table_name();
		$event_type = 'User Active';
		$now        = DateTimes::current_datetime();

		// Check if this is a new or continuing session.
		$continuing = false;
		$sql        = $wpdb->prepare(
			'SELECT event_id FROM %i WHERE user_id = %d AND event_type = %s ORDER BY when_happened DESC LIMIT 1',
			$table_name,
			$user->ID,
			$event_type
		);
		$record     = $wpdb->get_row( $sql, ARRAY_A );
		if ( $record ) {
			// Construct the Event object.
			$event = Event_Repository::load( $record['event_id'] );

			// Check we have the info we need.
			if ( $event->has_meta( 'activity_start' ) && $event->has_meta( 'activity_end' ) ) {

				// Extract the current activity_end datetime from the event details.
				$activity_start_datetime = $event->get_meta_val( 'activity_start' );
				$activity_end_datetime   = $event->get_meta_val( 'activity_end' );

				// Get the duration in seconds.
				$seconds_diff = $now->getTimestamp() - $activity_end_datetime->getTimestamp();

				// If the current value for activity_end time is less than 10 minutes ago, we'll
				// assume the current session is continuing, and update the activity_end time in the
				// existing log entry to now.
				if ( $seconds_diff <= self::MAX_BREAK_PERIOD ) {
					$continuing = true;

					// Update the activity_end time and duration.
					$event->set_meta( 'activity_end', $now );
					// This could be calculated, but for now we'll just record the string.
					$event->set_meta( 'activity_duration', DateTimes::get_duration_string( $activity_start_datetime, $now ) );

					// Update the event meta data.
					Event_Repository::save_eventmetas( $event );
				}
			}
		}

		// If we're not continuing an existing session, record the start of a new one.
		if ( ! $continuing ) {

			// Create the array of eventmetas.
			Eventmeta::update_array( self::$eventmetas, 'activity_start', $now );
			Eventmeta::update_array( self::$eventmetas, 'activity_end', $now );
			Eventmeta::update_array( self::$eventmetas, 'activity_duration', '0 minutes' );

			// Log the event.
			Logger::log_event( $event_type, $user, self::$eventmetas );
		}
	}

	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown() {
		// Save the profile update event if it exists.
		if ( self::$profile_update_event ) {
			self::$profile_update_event->save();
		}
	}
}
