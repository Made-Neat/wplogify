<?php
/**
 * Contains the Users class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;
use Exception;
use WP_User;

/**
 * Class WP_Logify\Users
 *
 * Provides tracking of events related to users.
 */
class Users {

	/**
	 * Array of changed properties.
	 *
	 * @var array
	 */
	private static $changes = array();

	/**
	 * Initializes the class by adding WordPress actions.
	 */
	public static function init() {
		add_action( 'wp_login', array( __CLASS__, 'track_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'track_logout' ), 10, 1 );
		add_action( 'user_register', array( __CLASS__, 'track_register' ), 10, 2 );
		add_action( 'delete_user', array( __CLASS__, 'track_delete' ), 10, 3 );
		add_action( 'profile_update', array( __CLASS__, 'track_update' ), 10, 3 );
		add_action( 'update_user_meta', array( __CLASS__, 'track_meta_update' ), 10, 4 );

		// Track user activity on every HTTP request.
		self::track_activity();
	}

	// ---------------------------------------------------------------------------------------------
	// Tracking methods.

	/**  * Track user login.
	 *
	 * @param string  $user_login The username of the user that logged in.
	 * @param WP_User $user       The WP_User object of the user that logged in.
	 */
	public static function track_login( string $user_login, WP_User $user ) {
		Logger::log_event( 'User Login', 'user', $user->ID, self::get_name( $user ) );
	}

	/**
	 * Track user logout.
	 *
	 * @param int $user_id The ID of the user that logged out.
	 */
	public static function track_logout( int $user_id ) {
		Logger::log_event( 'User Logout', 'user', $user_id, self::get_name( $user_id ) );
	}

	/**
	 * Track user registration.
	 *
	 * @param int   $user_id  The ID of the user that was registered.
	 * @param array $userdata The data for the user that was registered.
	 */
	public static function track_register( int $user_id, array $userdata ) {
		// Get the user's properties.
		$properties = self::get_properties( $user_id );

		// Log the event.
		Logger::log_event( 'User Registered', 'user', $user_id, self::get_name( $user_id ), null, $properties );
	}

	/**
	 * Track user deletion.
	 *
	 * @param int     $user_id  The ID of the user that was deleted.
	 * @param ?int    $reassign The ID of the user that the data was reassigned to.
	 * @param WP_User $user     The WP_User object of the user that was deleted.
	 */
	public static function track_delete( int $user_id, ?int $reassign, WP_User $user ) {
		// Get the user's properties.
		$properties = self::get_properties( $user );

		// If the user's data is being reassigned, record that information in the event details.
		$details = array();
		if ( $reassign ) {
			$reassign_ref                  = new Entity( 'user', $reassign, true );
			$details['Data reassigned to'] = $reassign_ref->to_array();
		}

		// Log the event.
		Logger::log_event( 'User Deleted', 'user', $user_id, self::get_name( $user ), $details, $properties );
	}

	/**
	 * Track user update.
	 *
	 * @param int     $user_id       The ID of the user that was updated.
	 * @param WP_User $old_user_data The WP_User object of the user before the update.
	 * @param array   $userdata      The data for the user after the update.
	 */
	public static function track_update( int $user_id, WP_User $old_user_data, array $userdata ) {
		// Get the user's properties.
		$properties = self::get_properties( $old_user_data );

		// Compare values and make note of any changes.
		foreach ( $old_user_data->data as $key => $value ) {
			// Not sure if we need these conversions to strings, keep an eye on it.
			if ( value_to_string( $value ) !== value_to_string( $userdata[ $key ] ) ) {
				$properties[ $key ]->old_value = $value;
				$properties[ $key ]->new_value = $userdata[ $key ];
			}
		}

		// Log the event.
		Logger::log_event( 'User Updated', 'user', $user_id, self::get_name( $user_id ), null, $properties );
	}

	/**
	 * Track user meta update.
	 *
	 * @param int    $meta_id    The ID of the meta data.
	 * @param int    $user_id    The ID of the user.
	 * @param string $meta_key   The key of the meta data.
	 * @param mixed  $meta_value The new value of the meta data.
	 */
	public static function track_meta_update( int $meta_id, int $user_id, string $meta_key, mixed $meta_value ) {
		// Get the current value.
		$current_value = get_user_meta( $user_id, $meta_key, true );

		// Track the change, if any.
		if ( value_to_string( $current_value ) !== value_to_string( $meta_value ) ) {
			self::$changes[ $meta_key ] = Property::create( null, $meta_key, 'meta', $current_value, $meta_value );
		}
	}

	/**
	 * Track user activity.
	 *
	 * This function is called via AJAX to track user activity.
	 */
	public static function track_activity() {
		global $wpdb;
		$user_id    = get_current_user_id();
		$table_name = Event_Repository::$table_name;
		$event_type = 'User Session';

		// Get the current datetime.
		$now           = DateTimes::current_datetime();
		$formatted_now = DateTimes::format_datetime_mysql( $now );

		// Check if this is a continuing session.
		$continuing      = false;
		$sql             = $wpdb->prepare(
			'SELECT ID, details FROM %i WHERE user_id = %d AND event_type = %s ORDER BY date_time DESC',
			$table_name,
			$user_id,
			$event_type
		);
		$existing_record = $wpdb->get_row( $sql );

		if ( $existing_record && ! empty( $existing_record->details ) ) {

			// Extract the current session end datetime from the event details.
			$details                = Json::decode( $existing_record->details );
			$session_start_datetime = $details['Session start'];
			$session_end_datetime   = $details['Session end'];

			// If the current value for session end time is less than 10 minutes ago, we'll assume
			// the current session is continuing, and update the session end time in the existing
			// log entry to now.
			$seconds_diff = $now->getTimestamp() - $session_end_datetime->getTimestamp();
			if ( $seconds_diff <= 600 ) {
				$continuing = true;

				// Update the session end time and duration.
				$details['Session end']      = $formatted_now;
				$details['Session duration'] = DateTimes::get_duration_string( $session_start_datetime, $now );

				// Update the record.
				$wpdb->update(
					$table_name,
					array( 'details' => Json::encode( $details ) ),
					array( 'ID' => $existing_record->ID ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}

		// If we're not continuing an existing session, record the start of a new one.
		if ( ! $continuing ) {
			$details = array(
				'Session start'    => $now,
				'Session end'      => $now,
				'Session duration' => '0 minutes',
			);
			Logger::log_event( $event_type, 'user', $user_id, self::get_name( $user_id ), $details );
		}
	}

	// ---------------------------------------------------------------------------------------------
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
			throw new Exception( "User {$user_id} could not be loaded." );
		}

		return $user;
	}

	/**
	 * Get the properties of a user to show in the log.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return array The properties of the user.
	 */
	private static function get_properties( WP_User|int $user ): array {
		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = self::get_user( $user );
		}

		// Start building the properties array.
		$properties = array();

		// Add the base properties.
		foreach ( $user as $key => $value ) {
			$properties[ $key ] = Property::create( null, $key, 'base', $value );
		}

		// Add the meta properties.
		$usermeta = get_user_meta( $user->ID );
		foreach ( $usermeta as $key => $value ) {
			$properties[ $key ] = Property::create( null, $key, 'meta', $value );
		}

		return $properties;
	}

	/**
	 * Retrieves a username for a given user.
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

		$body = wp_remote_retrieve_body( $response );
		$data = Json::decode( $body );

		// Construct the location string.
		if ( $data['status'] === 'success' ) {
			$location = array(
				$data['city'],
				$data['regionName'],
				$data['country'],
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
		$table_name       = Event_Repository::$table_name;
		$sql              = $wpdb->prepare(
			"SELECT * FROM %i WHERE user_id = %d AND event_type = 'User Login' ORDER BY date_time DESC LIMIT 1",
			$table_name,
			$user->ID
		);
		$last_login_event = $wpdb->get_row( $sql );
		return $last_login_event === null ? null : DateTimes::create_datetime( $last_login_event->date_time );
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

		// Get the most recent session end datetime from the wp_logify_events table.
		$table_name         = Event_Repository::$table_name;
		$sql                = $wpdb->prepare(
			"SELECT * FROM %i WHERE user_id = %d AND event_type = 'User Session' ORDER BY date_time DESC LIMIT 1",
			$table_name,
			$user->ID
		);
		$last_session_event = $wpdb->get_row( $sql );
		if ( $last_session_event !== null && $last_session_event->details !== null ) {
			$details = Json::decode( $last_session_event->details );
			return DateTimes::create_datetime( $details['Session end'] );
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
