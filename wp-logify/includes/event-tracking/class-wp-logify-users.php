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
	}

	/**
	 * Tracks user logins.
	 *
	 * @param string  $user_login The username of the user that logged in.
	 * @param WP_User $user The WP_User object of the user that logged in.
	 */
	public static function track_login( $user_login, $user ) {
		WP_Logify_Logger::log_event( 'Login' );
	}

	public static function track_user_registration( $user_id ) {
		$data = array(
			'event_type' => 'User registered',
			'object'     => "User ID: $user_id",
			'user_id'    => $user_id,
			'source_ip'  => $_SERVER['REMOTE_ADDR'],
			'date_time'  => current_time( 'mysql', true ),
		);
		// self::send_data_to_saas( $data );
	}

	/**
	 * Retrieves a username for a given user.
	 *
	 * First preference is the display_name, second preference is the user_login, third preference
	 * is the user_nicename.
	 *
	 * @param int|WP_User $user The ID of the user or the user object or a row from the users table.
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
	 * @param int|object $user The ID of the user or the user object or a row from the users table.
	 * @return string The link to the user's profile.
	 */
	public static function get_user_profile_link( int|object $user ) {
		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = get_userdata( $user );
		}

		// Construct the link.
		$user_profile_url  = site_url( "/?author={$user->ID}" );
		$user_display_name = self::get_username( $user );
		return "<a href='$user_profile_url'>$user_display_name</a>";
	}

	/**
	 * Retrieves the IP address of the user.
	 *
	 * @return string The IP address of the user or null if not found.
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
}
