<?php
/**
 * Contains the User_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use WP_Error;
use WP_User;

/**
 * Class Logify_WP\User_Tracker
 *
 * Provides tracking of events related to users.
 */
class User_Tracker
{

	/**
	 * The maximum break period in seconds. If there has been no activity for this period, we'll
	 * assume the user has left the site and is starting a new session when they return.
	 *
	 * @var int
	 */
	private const MAX_BREAK_PERIOD = 2; // 20 minutes

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
	public static function init()
	{

		//User Login
		add_action('wp_login', [__NAMESPACE__ . '\Async_Tracker', 'async_wp_login'], 10, 2);
		add_action('middle_wp_login', array(__CLASS__, 'on_wp_login'), 10, 2);

		add_action('wp_login_failed', [__NAMESPACE__ . '\Async_Tracker', 'async_wp_login_failed'], 10, 2);
		add_action('middle_wp_login_failed', array(__CLASS__, 'on_wp_login_failed'), 10, 2);

		// User logout.
		add_action('wp_logout', [__NAMESPACE__ . '\Async_Tracker', 'async_wp_logout'], 10, 1);
		add_action('middle_wp_logout', array(__CLASS__, 'on_wp_logout'), 10, 1);

		// User activity.
		add_action('wp_loaded', [__NAMESPACE__ . '\Async_Tracker', 'async_wp_loaded']);
		add_action('middle_wp_loaded', array(__CLASS__, 'on_wp_loaded'),10,1);

		// New user registration.
		add_action('user_register', [__NAMESPACE__ . '\Async_Tracker', 'async_user_register'], 10, 2);
		add_action('middle_user_register', array(__CLASS__, 'on_user_register'), 10, 2);

		// User deletion.
		add_action('delete_user', [__NAMESPACE__ . '\Async_Tracker', 'async_delete_user'], 10, 3);
		add_action('middle_delete_user', array(__CLASS__, 'on_delete_user'), 10, 3);

		// User update.
		add_action('profile_update', [__NAMESPACE__ . '\Async_Tracker', 'async_profile_update'], 10, 3);
		add_action('middle_profile_update', array(__CLASS__, 'on_profile_update'), 10, 3);

		add_action('update_user_meta', [__NAMESPACE__ . '\Async_Tracker', 'async_update_user_meta'], 10, 4);
		add_action('middle_update_user_meta', array(__CLASS__, 'on_update_user_meta'), 10, 4);

		// Shutdown hook.
		add_action('shutdown', [__NAMESPACE__ . '\Async_Tracker', 'async_shutdown_user'], 10, 0);
		add_action('middle_shutdown_user', array(__CLASS__, 'on_shutdown'), 10, 0);
	}


	/**
	 * User login.
	 *
	 * @param string  $user_login The username of the user that logged in.
	 * @param WP_User $user       The WP_User object of the user that logged in.
	 */

	public static function on_wp_login($user_login, $user_id)
	{
		// This event does not require an object, since the acting user *is* the object, but it does
		// require an object type ('user') in order to be grouped properly.
		// Also, the acting user must be provided, because there is no current logged in user at the
		// time this event occurs.

		//get User Object by user_id
		$user = get_user_by('ID', $user_id);

		$event = Event::create('User Login', 'user', null, null, $user);

		// If the event could not be created, return.
		if (!$event) {
			return;
		}

		$event->save();
	}


	/**
	 * Fires after a user login has failed.
	 *
	 * @param string   $username Username or email address.
	 * @param WP_Error $error    A WP_Error object with the authentication failure details.
	 */


	public static function on_wp_login_failed($username, $error_s)
	{
		//Unserialize Error object
		$error = unserialize($error_s);
		// Create the event.
		// This event does not require an object, since the acting user *is* the object, but it does
		// require an object type ('user') in order to be grouped properly.


		$event = Event::create('Failed Login', 'user', all_users: true);
		// If the event could not be created, exit. This shouldn't happen, because we track all
		// failed logins.
		if (!$event) {
			return;
		}
		// Store deatils of the login failure in the event metadata.
		$event->set_meta('username_entered', $username);
		$event->set_meta('error_code', $error->get_error_code());
		$event->set_meta('error_message', Strings::strip_tags($error->get_error_message()));

		// Log the event.
		$event->save();
	}

	/**
	 * User logout.
	 *
	 * @param int $user_id The ID of the user that logged out.
	 */

	public static function on_wp_logout(int $user_id)
	{
		// This event does not require an object, since the acting user *is* the object, but it does
		// require an object type ('user') in order to be grouped properly.
		// Also, the acting user must be provided, because there is no current logged-in user at the
		// time this event occurs.
		$event = Event::create('User Logout', 'user', null, null, $user_id);

		// If the event could not be created, we aren't tracking this user.
		if (!$event) {
			return;
		}

		$event->save();
	}

	/**
	 * User registration.
	 *
	 * @param int   $user_id  The ID of the user being registered.
	 * @param array $userdata The data for the user that was registered.
	 */
	public static function on_user_register(int $user_id, array $userdata)
	{
		Logger::log_event('User Registered', User_Utility::load($user_id));
	}

	/**
	 * User deletion.
	 *
	 * @param int     $user_id  The ID of the user that was deleted.
	 * @param ?int    $reassign The ID of the user that the data was reassigned to.
	 * @param WP_User $user     The WP_User object of the user that was deleted.
	 */
	public static function on_delete_user(int $user_id, ?int $reassign, $serialize_user)
	{
		global $wpdb;

		//Unserialize User Object
		$user = unserialize($serialize_user);
		// Get the user's properties.
		$props = User_Utility::get_properties($user);

		// Get the posts authored by this user.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare("SELECT ID FROM %i WHERE post_author = %d AND post_parent = 0 AND post_status != 'auto-draft'", $wpdb->posts, $user_id)
		);
		$post_refs = array_map(
			fn($post_id) => new Object_Reference('post', $post_id),
			$post_ids
		);
		Eventmeta::update_array(self::$eventmetas, 'posts_authored', $post_refs);

		// Get the comments authored by this user.
		$comment_ids = $wpdb->get_col(
			$wpdb->prepare('SELECT comment_ID FROM %i WHERE user_id = %d', $wpdb->comments, $user_id)
		);
		$comment_refs = array_map(
			fn($comment_id) => new Object_Reference('comment', $comment_id),
			$comment_ids
		);
		Eventmeta::update_array(self::$eventmetas, 'comments_authored', $comment_refs);

		// If the user's data is being reassigned, record that information in the eventmetas.
		if ($reassign) {
			Eventmeta::update_array(self::$eventmetas, 'content_reassigned_to', new Object_Reference('user', $reassign));
		}

		// Log the event.
		Logger::log_event('User Deleted', $user, self::$eventmetas, $props);
	}

	/**
	 * User update.
	 *
	 * @param int     $user_id  The ID of the user being updated.
	 * @param WP_User $user     The WP_User object of the user before the update.
	 * @param array   $userdata The data for the user after the update.
	 */
	public static function on_profile_update($user_id, $serialize_user, $userdata)
	{
		global $wpdb;

		//Unserialize User Object
		$user = unserialize($serialize_user);
		// Compare values and make note of any changes.
		foreach ($user->data as $key => $value) {
			// Process values.
			$val = Types::process_database_value($key, $value);
			$new_val = Types::process_database_value($key, $userdata[$key]);
			// error_log("aaa".$new_val);
			// error_log("aaa");

			// If the value has changed, add the before and after values to the properties.
			if (!Types::are_equal($val, $new_val)) {

				// Create the event if it doesn't already exist.
				if (!self::$profile_update_event) {
					self::$profile_update_event = Event::create('User Updated', $user);

					// If the event could not be created, exit the method.
					if (!self::$profile_update_event) {
						return;
					}
				}

				// Add the property.
				self::$profile_update_event->set_prop($key, $wpdb->users, $val, $new_val);
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
	public static function on_update_user_meta(int $meta_id, int $user_id, string $meta_key, mixed $meta_value)
	{
		global $wpdb;

		// Ignore changes to session tokens.
		if ($meta_key === 'session_tokens') {
			return;
		}

		// Get the current value.
		$current_value = get_user_meta($user_id, $meta_key, true);

		// Process values.
		$val = Types::process_database_value($meta_key, $current_value);
		$new_val = Types::process_database_value($meta_key, $meta_value);

		// If the value has changed, add the before and after values to the properties array.
		if (!Types::are_equal($val, $new_val)) {

			// Create the event if it doesn't already exist.
			if (!self::$profile_update_event) {
				// The event will not be created if the current user isn't logged in or if the
				// current user has a role that isn't being tracked.
				$user_ref = new Object_Reference('user', $user_id);
				self::$profile_update_event = Event::create('User Updated', $user_ref);

				// If the event could not be created, bail.
				if (!self::$profile_update_event) {
					return;
				}
			}

			// If we have an event, add the new property.
			self::$profile_update_event->set_prop($meta_key, $wpdb->usermeta, $val, $new_val);
		}
	}

	/**
	 * Log user activity.
	 */
	public static function on_wp_loaded($user)
	{
		// Ignore AJAX requests, as it's likely WP doing something and not the user.
		// The user has probably just left the web page open in the browser while they do something
		// else.
		
		if (wp_doing_ajax()) {
			return;
		}
		
		// Don't record activity if the user isn't logged in.
		if (!$user->exists()) {
			return;
		}
		
		// Prepare event details.
		$event_type = 'User Active';
		$now = DateTimes::current_datetime();
		
		// Flags for whether or not we need to create a new event or update an existing one.
		$create_new_event = true;
		

		$event = Event_Repository::get_most_recent_event($event_type, $user);
		if ($event) {
			// Check we have the info we need.
			if ($event->has_meta('activity_start') && $event->has_meta('activity_end')) {

				// Extract the current activity_end datetime from the event details.
				$activity_start_datetime = $event->get_meta_val('activity_start');
				$activity_end_datetime = $event->get_meta_val('activity_end');

				// Get the duration in seconds.
				$seconds_diff = $now->getTimestamp() - $activity_end_datetime->getTimestamp();

				// If the activity end time is less than 10 minutes ago, no need to create a new
				// event.
				if ($seconds_diff <= self::MAX_BREAK_PERIOD) {
					$create_new_event = false;

					// If the activity end time is more than zero seconds but less than 10 minutes
					// ago, update the existing event.
					if ($seconds_diff > 0) {
						// Update the activity end time.
						$event->set_meta('activity_end', $now);

						// Update the duration.
						// This could be calculated, but for now we'll just record the string.
						$event->set_meta('activity_duration', DateTimes::get_duration_string($activity_start_datetime, $now));

						// Save the updated event.
						$event->save();
					}
				}
			}
		}

		// If we're not continuing an existing session, record the start of a new one.
		if ($create_new_event) {
			// Create a new activity event.
			$event = Event::create($event_type, 'user', null, null, $user);

			// If the event could not be created, bail.
			if (!$event) {
				return;
			}

			// Set the eventmetas.
			$event->set_meta('activity_start', $now);
			$event->set_meta('activity_end', $now);
			$event->set_meta('activity_duration', '0 minutes');

			// Log the event.
			$event->save();
		}
	}

	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown()
	{
		// Save the profile update event if it exists.
		if (self::$profile_update_event) {
			self::$profile_update_event->save();
		}
	}
}
