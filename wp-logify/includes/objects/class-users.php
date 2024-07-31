<?php
/**
 * Contains the Users class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;
use Exception;
use WP_Query;
use WP_User;

/**
 * Class WP_Logify\Users
 *
 * Provides tracking of events related to users.
 */
class Users {

	/**
	 * Current event under construction.
	 *
	 * @var array
	 */
	private static Event $new_event;

	/**
	 * Initializes the class by adding WordPress actions.
	 */
	public static function init() {
		// Track user login.
		add_action( 'wp_login', array( __CLASS__, 'on_wp_login' ), 10, 2 );

		// Track user logout.
		add_action( 'wp_logout', array( __CLASS__, 'on_wp_logout' ), 10, 1 );

		// If the user isn't logged in, we don't need to track anything else.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Track user activity.
		add_action( 'wp_loaded', array( __CLASS__, 'on_wp_loaded' ) );

		// Track registration of a new user.
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 10, 2 );

		// Track deletion of a user.
		add_action( 'delete_user', array( __CLASS__, 'on_delete_user' ), 10, 3 );

		// Track update of a user.
		add_action( 'profile_update', array( __CLASS__, 'on_profile_update' ), 10, 3 );

		// Track update of user metadata.
		add_action( 'update_user_meta', array( __CLASS__, 'on_update_user_meta' ), 10, 4 );
	}

	// ---------------------------------------------------------------------------------------------
	// Tracking methods.

	/**  * Track user login.
	 *
	 * @param string  $user_login The username of the user that logged in.
	 * @param WP_User $user       The WP_User object of the user that logged in.
	 */
	public static function on_wp_login( string $user_login, WP_User $user ) {
		$event             = self::create_event( $user );
		$event->event_type = 'User Login';
		$event->save();
	}

	/**
	 * Track user logout.
	 *
	 * @param int $user_id The ID of the user that logged out.
	 */
	public static function on_wp_logout( int $user_id ) {
		$event             = self::create_event( $user_id );
		$event->event_type = 'User Logout';
		$event->save();
	}

	/**
	 * Track user registration.
	 *
	 * @param int   $user_id  The ID of the user that was registered.
	 * @param array $userdata The data for the user that was registered.
	 */
	public static function on_user_register( int $user_id, array $userdata ) {
		$event             = self::create_event( $user_id );
		$event->event_type = 'User Registered';
		$event->save();
	}

	/**
	 * Track user deletion.
	 *
	 * @param int     $user_id  The ID of the user that was deleted.
	 * @param ?int    $reassign The ID of the user that the data was reassigned to.
	 * @param WP_User $user     The WP_User object of the user that was deleted.
	 */
	public static function on_delete_user( int $user_id, ?int $reassign, WP_User $user ) {
		// Construct the new event if we need to.
		$event = self::$new_event ?? self::create_event( $user );

		// Get the posts created by this user.
		$posts     = get_posts( array( 'author' => $user->ID ) );
		$post_refs = array_map( fn( $post ) => new Object_Reference( 'post', $post->ID, $post->post_title ), $posts );
		$event->set_property( 'posts', null, $post_refs );

		// TODO Get the comments created by this user.

		// If the user's data is being reassigned, record that information in the event metadata.
		if ( $reassign ) {
			$reassign_user_ref = new Object_Reference( 'user', $reassign );
			$event->set_property( 'content_reassigned_to', null, $reassign_user_ref );
		}

		// Save the event.
		$event->save();
		// Logger::log_event( 'User Deleted', 'user', $user_id, self::get_name( $user ), $event_meta, $properties );
	}

	/**
	 * Track user update.
	 *
	 * @param int     $user_id       The ID of the user that was updated.
	 * @param WP_User $old_user_data The WP_User object of the user before the update.
	 * @param array   $userdata      The data for the user after the update.
	 */
	public static function on_profile_update( int $user_id, WP_User $old_user_data, array $userdata ) {
		global $wpdb;

		// Construct the new event if we need to.
		$event = self::$new_event ?? self::create_event( $user_id );

		// Compare values and make note of any changes.
		foreach ( $old_user_data->data as $key => $value ) {

			// Process meta values into correct types.
			$old_value = Types::process_database_value( $key, $value );
			$new_value = Types::process_database_value( $key, $userdata[ $key ] );

			// If the value has changed, add the before and after values to the changes array.
			if ( ! Types::are_equal( $old_value, $new_value ) ) {
				$event->set_property( $key, $wpdb->users, $old_value, $new_value );
			}
		}

		// Save the event.
		$event->save();
		// Logger::log_event( 'User Updated', 'user', $user_id, self::get_name( $user_id ), null, self::$properties );
	}

	/**
	 * Track user meta update.
	 *
	 * @param int    $meta_id    The ID of the meta data.
	 * @param int    $user_id    The ID of the user.
	 * @param string $meta_key   The key of the meta data.
	 * @param mixed  $meta_value The new value of the meta data.
	 */
	public static function on_update_user_meta( int $meta_id, int $user_id, string $meta_key, mixed $meta_value ) {
		global $wpdb;

		// Construct the new event if we need to.
		$event = self::$new_event ?? self::create_event( $user_id );

		// Get the current value.
		$current_value = get_user_meta( $user_id, $meta_key, true );

		// Process meta values into correct types.
		$old_value = Types::process_database_value( $meta_key, $current_value );
		$new_value = Types::process_database_value( $meta_key, $meta_value );

		// If the value has changed, add the before and after values to the changes array.
		if ( ! Types::are_equal( $old_value, $new_value ) ) {
			$event->set_property( $meta_key, $wpdb->usermeta, $old_value, $new_value );
		}

		// Remember the new event.
		self::$new_event = $event;
	}

	/**
	 * Track user activity.
	 */
	public static function on_wp_loaded() {
		global $wpdb;

		// Sometimes (e.g. when editing a post) WordPress triggers two simultaneous HTTP requests,
		// which was causing a database deadlock in this method, which gets called on every request.
		// Therefore, if one request is already tracking activity, we won't try to track activity
		// again until it's done.

		// Check if activity tracking is already in progress.
		if ( get_transient( 'wp_logify_activity_tracking_in_progress' ) ) {
			// Another HTTP request is already handling the activity tracking, so let's go.
			return;
		}

		// Note that activity tracking is in progress.
		set_transient( 'wp_logify_activity_tracking_in_progress', true );

		// Prepare some values.
		$user_id    = get_current_user_id();
		$table_name = Event_Repository::get_table_name();
		$event_type = 'User Session';
		$now        = DateTimes::current_datetime();

		// Check if this is a new or continuing session.
		$continuing = false;
		$sql        = $wpdb->prepare(
			'SELECT event_id FROM %i WHERE user_id = %d AND event_type = %s ORDER BY when_happened DESC LIMIT 1',
			$table_name,
			$user_id,
			$event_type
		);
		$record     = $wpdb->get_row( $sql, ARRAY_A );
		if ( $record ) {
			// Construct the Event object.
			$event = Event_Repository::load( $record['event_id'] );

			// Check we have the info we need.
			if ( ! $event->has_property( 'session_start' ) || ! $event->has_property( 'session_end' ) ) {

				// Extract the current session_end datetime from the event details.
				$session_start_datetime = $event->get_property_value( 'session_start' );
				$session_end_datetime   = $event->get_property_value( 'session_end' );

				// Get the duration in seconds.
				$seconds_diff = $now->getTimestamp() - $session_end_datetime->getTimestamp();

				// If the current value for session_end time is less than 10 minutes ago, we'll
				// assume the current session is continuing, and update the session_end time in the
				// existing log entry to now.
				if ( $seconds_diff <= 600 ) {
					$continuing = true;

					// Update the session_end time and duration.
					$event->set_property_value( 'session_end', $now );
					// This could be calculated, but for now we'll just record the string.
					$event->set_property_value( 'session_duration', DateTimes::get_duration_string( $session_start_datetime, $now ) );

					// Update the event properties.
					$event->save_properties();
				}
			}
		}

		// If we're not continuing an existing session, record the start of a new one.
		if ( ! $continuing ) {

			// Construct the new event if we need to.
			if ( ! isset( $event ) ) {
				$user  = self::get_user( $user_id );
				$event = self::create_event( $user );
			}

			// Create the array of metadata.
			$event->set_property( 'session_start', null, $now );
			$event->set_property( 'session_end', null, $now );
			$event->set_property( 'session_duration', null, '0 minutes' );

			// Save the event.
			$event->save();

			// Log the event.
			// Logger::log_event( $event_type, null, null, null, $event_meta );
		}

		// Note that activity tracking is no longer in progress.
		delete_transient( 'wp_logify_activity_tracking_in_progress' );
	}

	// =============================================================================================
	// Event-related methods.

	/**
	 * Create a user-related event.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return Event The new event about the specified user.
	 */
	public static function create_event( WP_User|int $user ): Event {
		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = self::get_user( $user );
		}

		// Create the new event.
		$event = Event::create( 'user', $user->ID, self::get_name( $user ) );

		// Add the core properties.
		$event->properties = self::get_core_properties( $user );

		// Return the new event.
		return $event;
	}

	// =============================================================================================
	// Methods for getting user information.

	/**
	 * Check if a user exists.
	 *
	 * @param int $user_id The ID of the user.
	 * @return bool True if the user exists, false otherwise.
	 */
	public static function user_exists( int $user_id ): bool {
		global $wpdb;
		$sql   = $wpdb->prepare( 'SELECT COUNT(ID) FROM %i WHERE ID = %d', $wpdb->users, $user_id );
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}

	/**
	 * Get a user by ID.
	 *
	 * @param int $user_id The ID of the user.
	 * @return WP_User The user object.
	 * @throws Exception If the user could not be loaded.
	 */
	public static function get_user( int $user_id ): WP_User {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			throw new Exception( "User $user_id could not be loaded." );
		}

		return $user;
	}

	/**
	 * Get the core properties of a user.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return array The core properties of the user.
	 */
	public static function get_core_properties( WP_User|int $user ): array {
		global $wpdb;

		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = self::get_user( $user );
		}

		// Define the core properties by key.
		$core_properties = array( 'ID', 'display_name', 'user_login', 'user_email', 'user_registered' );

		// Build the array of properties.
		$properties = array();
		foreach ( $core_properties as $key ) {

			// Get the value.
			switch ( $key ) {
				case 'user_email':
					$value = self::get_email_link( $user );
					break;

				case 'user_registered':
					$value = DateTimes::create_datetime( $user->data->user_registered, 'UTC' );
					break;

				default:
					// Process database values into correct types.
					$value = Types::process_database_value( $key, $user->{$key} );
					break;
			}

			// Construct the new Property object and add it to the properties array.
			$properties[ $key ] = new Property( $key, $wpdb->users, $value );
		}

		return $properties;
	}

	/**
	 * Get the properties of a user to show in the log.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return array The properties of the user.
	 */
	private static function get_properties( WP_User|int $user ): array {
		global $wpdb;

		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = self::get_user( $user );
		}

		// Start building the properties array.
		$properties = self::get_core_properties( $user );

		// Add the base properties.
		foreach ( $user->data as $key => $value ) {
			// Skip core properties.
			if ( key_exists( $key, $properties ) ) {
				continue;
			}

			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// For whatever reason, the user_registered column in the users table is in UTC,
			// despite not having the same '_gmt' suffix as UTC datetimes in the posts table.
			if ( $key === 'user_registered' ) {
				DateTimes::set_timezone( $value, 'UTC' );
			}

			// Construct the new Property object and add it to the properties array.
			$properties[ $key ] = new Property( $key, $wpdb->users, $value );
		}

		// Add the meta properties.
		$usermeta = get_user_meta( $user->ID );
		foreach ( $usermeta as $key => $value ) {
			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			$properties[ $key ] = new Property( $key, $wpdb->usermeta, $value );
		}

		return $properties;
	}

	/**
	 * Retrieves a name for a given user.
	 *
	 * First preference is the display_name, second preference is the user_login, third preference
	 * is the user_nicename.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return string The username if found, otherwise 'Unknown'.
	 */
	public static function get_name( WP_User|int $user ) {
		// Load the user if necessary.
		if ( is_int( $user ) ) {
			try {
				$user = self::get_user( $user );
			} catch ( Exception ) {
				return 'Unknown';
			}
		}

		// First preference is the display name, which is their full name.
		if ( ! empty( $user->display_name ) ) {
			return $user->display_name;
		}

		// If that fails, use their login name.
		if ( ! empty( $user->user_login ) ) {
			return $user->user_login;
		}

		// If that fails, use the nice name.
		if ( ! empty( $user->user_nicename ) ) {
			return $user->user_nicename;
		}

		return 'Unknown';
	}

	/**
	 * Retrieves the IP address of the user.
	 *
	 * @return ?string The IP address of the user or null if not found.
	 */
	public static function get_ip(): ?string {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			// Check for shared internet/ISP IP.
			$ip = wp_unslash( $_SERVER['HTTP_CLIENT_IP'] );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Check for IPs passing through proxies.
			// The value might be a comma-separated list of addresses.
			$ip = explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )[0];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			// The remote address (the actual IP).
			$ip = wp_unslash( $_SERVER['REMOTE_ADDR'] );
		} else {
			$ip = null;
		}

		// Trim any whitespace.
		return $ip === null ? null : trim( $ip );
	}

	/**
	 * Retrieves the location of the user based on their IP address.
	 *
	 * @param string $ip The IP address of the user.
	 * @return ?string The location of the user or null if not found.
	 */
	public static function get_location( string $ip ): ?string {
		// Use a geolocation API to get location info from the IP address.
		$response = wp_remote_get( "http://ip-api.com/json/$ip" );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		// Construct the location string.
		if ( $result['status'] === 'success' ) {
			$location = array(
				$result['city'],
				$result['regionName'],
				$result['country'],
			);
			return implode( ', ', array_filter( $location ) );
		}

		// Return null if the location could not be determined.
		return null;
	}

	/**
	 * Retrieves the user agent string from the server variables.
	 *
	 * @return ?string The user agent string or null if not found.
	 */
	public static function get_user_agent(): ?string {
		return isset( $_SERVER['HTTP_USER_AGENT'] )
			? trim( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: null;
	}

	/**
	 * Retrieves the last login datetime of a user.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return ?DateTime The last login datetime of the user or null if not found.
	 */
	public static function get_last_login_datetime( WP_User|int $user ): ?DateTime {
		global $wpdb;

		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = self::get_user( $user );
		}

		// Get the last login datetime from the wp_logify_events table.
		$table_name = Event_Repository::get_table_name();
		$sql        = $wpdb->prepare(
			"SELECT when_happened FROM %i WHERE user_id = %d AND event_type = 'User Login' ORDER BY when_happened DESC LIMIT 1",
			$table_name,
			$user->ID
		);
		$record     = $wpdb->get_row( $sql, ARRAY_A );
		return $record === null ? null : DateTimes::create_datetime( $record['when_happened'] );
	}

	/**
	 * Retrieves the last active datetime of a user.
	 *
	 * @param  WP_User|int $user The user object or ID.
	 * @return ?DateTime The last active datetime of the user or null if not found.
	 */
	public static function get_last_active_datetime( WP_User|int $user ): ?DateTime {
		global $wpdb;

		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = self::get_user( $user );
		}

		// Get the most recent session_end datetime from the wp_logify_events table.
		$table_name = Event_Repository::get_table_name();
		$sql        = $wpdb->prepare(
			"SELECT * FROM %i WHERE user_id = %d AND event_type = 'User Session' ORDER BY when_happened DESC LIMIT 1",
			$table_name,
			$user->ID
		);
		$record     = $wpdb->get_row( $sql, ARRAY_A );

		// If we got a record.
		if ( $record !== null ) {
			// Create the Event.
			$event = Event_Repository::load( $record['event_id'] );

			// Return the session_end datetime if set.
			if ( ! empty( $event->event_meta['session_end'] ) ) {
				return $event->event_meta['session_end'];
			}
		}

		return null;
	}

	/**
	 * Get the URL for a user's edit page.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return string The edit page URL.
	 */
	public static function get_edit_url( WP_User|int $user ) {
		$user_id = is_int( $user ) ? $user : $user->ID;
		return admin_url( "user-edit.php?user_id=$user_id" );
	}

	/**
	 * Get the HTML for the link to the user's edit page.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return string The link HTML tag.
	 */
	public static function get_edit_link( WP_User|int $user ) {
		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = self::get_user( $user );
		}

		// Get the URL for the user's edit page.
		$url = self::get_edit_url( $user );

		// Return the link.
		$name = self::get_name( $user );
		return "<a href='$url' class='wp-logify-user-link'>$name</a>";
	}

	/**
	 * If the user hasn't been deleted, get a link to its edit page; otherwise, get a span with
	 * the old title as the link text.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @param string      $old_name The old name of the user.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( WP_User|int $user, string $old_name ) {
		// If the user exists, return a link to their edit page.
		if ( self::user_exists( $user ) ) {
			return self::get_edit_link( $user );
		}

		// The user no longer exists. Construct the 'deleted' span element.
		$user_id = is_int( $user ) ? $user : $user->ID;
		$name    = empty( $old_name ) ? "User $user_id" : $old_name;
		return "<span class='wp-logify-deleted-object'>$name (deleted)</span>";
	}

	/**
	 * Get the email address of a user as a mailto link.
	 *
	 * @param WP_User|int|string $user The user object or ID or email address.
	 * @return string The email link.
	 */
	public static function get_email_link( WP_User|int|string $user ) {
		// Get the email address.
		if ( is_int( $user ) ) {
			$user_email = self::get_user( $user )->user_email;
		} elseif ( $user instanceof WP_User ) {
			$user_email = $user->user_email;
		} else {
			$user_email = $user;
		}

		return "<a href='mailto:$user_email'>$user_email</a>";
	}

	/**
	 * Get all posts authored by a given user as an array of Object_Reference objects.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return Object_Reference[] Array of Object_Reference objects representing the posts.
	 */
	public static function get_posts_by_user( WP_User|int $user ): array {
		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = self::get_user( $user );
		}

		// Fetch all posts by the user.
		$args = array(
			'author'         => $user->ID,
			'post_type'      => 'any', // Fetch any type of post
			'post_status'    => 'any', // Fetch any posts
			'posts_per_page' => -1, // Fetch all posts
		);

		$query = new WP_Query( $args );
		$posts = $query->posts;

		// Convert the posts to Object_Reference objects.
		$object_references = array();
		foreach ( $posts as $post ) {
			$object_reference    = new Object_Reference( 'post', $post->ID, $post->post_title );
			$object_references[] = $object_reference;
		}

		return $object_references;
	}


	// ---------------------------------------------------------------------------------------------
	// Permission-related methods.

	/**
	 * Checks if the user has access based on their roles.
	 *
	 * @param WP_User      $user The user to check.
	 * @param array|string $roles A role or array of roles to check against.
	 * @return bool Returns true if the user has any of the specified roles, false otherwise.
	 */
	public static function user_has_role( WP_User $user, array|string $roles ): bool {
		if ( is_string( $roles ) ) {
			// If only a single role is given, check if the user has it.
			return in_array( $roles, $user->roles, true );
		} else {
			// If an array of roles is given, check for overlap.
			return count( array_intersect( $user->roles, $roles ) ) > 0;
		}
	}

	/**
	 * Checks if the current user has access based on their roles.
	 *
	 * @param array|string $roles A role or array of roles to check against.
	 * @return bool Returns true if the current user has any of the specified roles, false otherwise.
	 */
	public static function current_user_has_role( array|string $roles ) {
		return self::user_has_role( wp_get_current_user(), $roles );
	}
}
