<?php
/**
 * Class WP_Logify_Basic
 *
 * This class provides basic tracking functionalities for WordPress.
 * It tracks changes to posts and user logins.
 */
class WP_Logify_Users {
	/**
	 * Initializes the class by adding WordPress actions.
	 */
	public static function init() {
		add_action( 'wp_login', array( __CLASS__, 'track_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'track_logout' ), 10, 1 );
		add_action( 'wp_ajax_track_user_activity', array( __CLASS__, 'track_user_activity' ) );
	}

	/**
	 * Track user login.
	 *
	 * @param string  $user_login The username of the user that logged in.
	 * @param WP_User $user The WP_User object of the user that logged in.
	 */
	public static function track_login( string $user_login, WP_User $user ) {
		// debug_log( '$user_login', $user_login );
		// debug_log( '$user', $user );

		// Log the event.
		WP_Logify_Logger::log_event( 'User Login', 'user', $user->ID );
	}

	/**
	 * Track user logout.
	 *
	 * @param int $user_id The ID of the user that logged out.
	 */
	public static function track_logout( int $user_id ) {
		WP_Logify_Logger::log_event( 'User Logout', 'user', $user_id );
	}

	// /**
	// * Track user registration.
	// */
	// public static function track_user_registration( $user_id ) {
	// $data = array(
	// 'event_type' => 'User registered',
	// 'object'     => "User ID: $user_id",
	// 'user_id'    => $user_id,
	// 'user_ip'    => $_SERVER['REMOTE_ADDR'],
	// 'date_time'  => current_time( 'mysql', true ),
	// );
	// self::send_data_to_saas( $data );

	// WP_Logify_Logger::log_event( 'User Logout', 'user', $user_id );
	// }

	/**
	 * Retrieves a username for a given user.
	 *
	 * First preference is the display_name, second preference is the user_login, third preference
	 * is the user_nicename.
	 *
	 * @param int|WP_User $user The ID of the user, the user object, or a row from the users table.
	 * @return string The username if found, otherwise 'Unknown'.
	 */
	public static function get_username( int|object $user ) {
		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = get_userdata( $user );
		}

		if ( ! empty( $user->display_name ) ) {
			return $user->display_name;
		}

		if ( ! empty( $user->user_login ) ) {
			return $user->user_login;
		}

		if ( ! empty( $user->user_nicename ) ) {
			return $user->user_nicename;
		}

		return 'Unknown';
	}

	/**
	 * Retrieves a link to the user's profile.
	 *
	 * @param int|object $user The ID of the user, the user object, or a row from the users table.
	 * @return ?string The link to the user's profile or null if the user wasn't found.
	 */
	public static function get_user_profile_link( int|object $user ): ?string {
		// Check for a valid parameter.
		if ( empty( $user ) ) {
			return 'Unknown';
		}

		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = get_userdata( $user );
			if ( $user === false ) {
				return 'Unknown';
			}
		}

		// Construct the link.
		$user_profile_url  = site_url( "/?author={$user->ID}" );
		$user_display_name = self::get_username( $user );
		return "<a href='$user_profile_url' class='wp-logify-user-link'>$user_display_name</a>";
	}

	/**
	 * Retrieves the IP address of the user.
	 *
	 * @return ?string The IP address of the user or null if not found.
	 */
	public static function get_user_ip(): ?string {
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
	public static function get_user_location( string $ip ): ?string {
		// Use a geolocation API to get location info from the IP address.
		$response = wp_remote_get( "http://ip-api.com/json/$ip" );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

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
	 * Track user activity.
	 */
	public static function track_user_activity() {
		check_ajax_referer( 'wp_logify_activity_nonce', 'nonce' );

		global $wpdb;
		$user_id    = get_current_user_id();
		$table_name = WP_Logify_Logger::get_table_name();
		$event_type = 'User Session';

		// Get the current datetime.
		$now           = WP_Logify_DateTime::current_datetime();
		$formatted_now = WP_Logify_DateTime::format_datetime_mysql( $now );

		// Check if this is a continuing session.
		$continuing      = false;
		$sql             = $wpdb->prepare(
			'SELECT id, details FROM %i WHERE user_id = %d AND event_type = %s ORDER BY date_time DESC',
			$table_name,
			$user_id,
			$event_type
		);
		$existing_record = $wpdb->get_row( $sql );
		if ( $existing_record && $existing_record->details !== null ) {

			// Extract the current session end datetime from the event details.
			$details                = json_decode( $existing_record->details, true );
			$session_start_datetime = WP_Logify_DateTime::create_datetime( $details['Session start'] );
			$session_end_datetime   = WP_Logify_DateTime::create_datetime( $details['Session end'] );

			// If the current value for session end time is less than 5 minutes (300 seconds) before
			// now, we'll say the session is continuing, and update the session end time to now.
			$seconds_diff = $now->getTimestamp() - $session_end_datetime->getTimestamp();
			// debug_log( 'seconds diff', $seconds_diff );
			if ( $seconds_diff <= 300 ) {
				$continuing = true;

				// Update the session end time to now.
				$details['Session end'] = $formatted_now;

				// Calculate the new duration.
				$duration_seconds            = $now->getTimestamp() - $session_start_datetime->getTimestamp();
				$duration_minutes            = ceil( $duration_seconds / 60 );
				$details['Session duration'] = $duration_minutes . ' minute' . ( $duration_minutes === 1 ? '' : 's' );

				// Encode the details as JSON and update the record.
				$wpdb->update(
					$table_name,
					array( 'details' => wp_json_encode( $details ) ),
					array( 'id' => $existing_record->id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}

		// If we're not continuing an existing session, record the start of a new one.
		if ( ! $continuing ) {
			$details = array(
				'Session start'    => $formatted_now,
				'Session end'      => $formatted_now,
				'Session duration' => '0 minutes',
			);
			WP_Logify_Logger::log_event( $event_type, 'user', $user_id, $details );
		}

		wp_send_json_success();
	}
}
