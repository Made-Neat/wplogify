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
class User_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if a user exists.
	 *
	 * @param int|string $user_id The ID of the user.
	 * @return bool True if the user exists, false otherwise.
	 */
	public static function exists( int|string $user_id ): bool {
		global $wpdb;
		$sql   = $wpdb->prepare( 'SELECT COUNT(ID) FROM %i WHERE ID = %d', $wpdb->users, $user_id );
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}

	/**
	 * Get a user by ID.
	 *
	 * @param int|string $user_id The ID of the user.
	 * @return ?WP_User The user object if found, null otherwise.
	 */
	public static function load( int|string $user_id ): ?WP_User {
		// Get the user by ID.
		$user = get_userdata( $user_id );

		// Return the user or null if it doesn't exist.
		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * Retrieves a name for a given user.
	 *
	 * First preference is the display_name, second preference is the user_login, third preference
	 * is the user_nicename.
	 *
	 * @param int|string $user_id The ID of the user.
	 * @return ?string The username if found, otherwise null.
	 */
	public static function get_name( int|string $user_id ): ?string {
		// Load the user.
		$user = self::load( $user_id );

		// Handle the case where the user no longer exists.
		if ( ! $user ) {
			return null;
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

		return null;
	}

	/**
	 * Get the core properties of a user.
	 *
	 * @param int|string $user_id The ID of the user.
	 * @return array The core properties of the user.
	 * @throws Exception If the user doesn't exist.
	 */
	public static function get_core_properties( int|string $user_id ): array {
		global $wpdb;

		// Load the user.
		$user = self::load( $user_id );

		// Handle the case where the user no longer exists.
		if ( ! $user ) {
			throw new Exception( "User $user_id not found." );
		}

		// Build the array of properties.
		$properties = array();

		// ID.
		Property::update_array( $properties, 'ID', $wpdb->users, (int) $user->ID );

		// Display name.
		Property::update_array( $properties, 'display_name', $wpdb->users, $user->display_name );

		// User login.
		Property::update_array( $properties, 'user_login', $wpdb->users, $user->user_login );

		// User email.
		Property::update_array( $properties, 'user_email', $wpdb->users, self::get_email_link( $user ) );

		// User registered.
		$user_registered = DateTimes::create_datetime( $user->data->user_registered, 'UTC' );
		Property::update_array( $properties, 'user_registered', $wpdb->users, $user_registered );

		return $properties;
	}

	/**
	 * If the user hasn't been deleted, get a link to its edit page; otherwise, get a span with
	 * the old title as the link text.
	 *
	 * @param int|string $user_id The ID of the user.
	 * @param ?string    $old_name The old name of the user.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $user_id, ?string $old_name ): string {
		// Load the user.
		$user = self::load( $user_id );

		// If the user exists, get a link.
		if ( $user ) {
			// Get the user name.
			$name = self::get_name( $user_id );

			// Get the user edit URL.
			$url = admin_url( "user-edit.php?user_id=$user_id" );

			// Return the link.
			return "<a href='$url' class='wp-logify-object'>$name</a>";
		}

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = "User $user_id";
		}

		// The user no longer exists. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Methods for getting information about users.

	/**
	 * Get the properties of a user to show in the log.
	 *
	 * @param WP_User|int $user The user object or ID.
	 * @return array The properties of the user.
	 */
	public static function get_properties( WP_User|int $user ): array {
		global $wpdb;

		// Load the user if necessary.
		if ( is_int( $user ) ) {
			$user = self::load( $user );
		}

		$properties = array();

		// Add the base properties.
		foreach ( $user->data as $key => $value ) {
			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// For whatever reason, the user_registered column in the users table is in UTC,
			// despite not having the same '_gmt' suffix as UTC datetimes in the posts table.
			if ( $key === 'user_registered' ) {
				DateTimes::set_timezone( $value, 'UTC' );
			}

			// Construct the new Property object and add it to the properties array.
			Property::update_array( $properties, $key, $wpdb->users, $value );
		}

		// Add the meta properties.
		$usermeta = get_user_meta( $user->ID );
		foreach ( $usermeta as $key => $value ) {
			// Process meta values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			Property::update_array( $properties, $key, $wpdb->usermeta, $value );
		}

		return $properties;
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
			$user = self::load( $user );
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
			$user = self::load( $user );
		}

		// Get the most recent activity_end datetime from the wp_logify_events table.
		$table_name = Event_Repository::get_table_name();
		$sql        = $wpdb->prepare(
			"SELECT * FROM %i WHERE user_id = %d AND event_type = 'User Active' ORDER BY when_happened DESC LIMIT 1",
			$table_name,
			$user->ID
		);
		$record     = $wpdb->get_row( $sql, ARRAY_A );

		// If we got a record.
		if ( $record !== null ) {
			// Create the Event.
			$event = Event_Repository::load( $record['event_id'] );

			// Return the activity_end datetime if set.
			if ( $event->has_meta( 'activity_end' ) ) {
				return $event->get_meta_val( 'activity_end' );
			}
		}

		return null;
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
			$user_email = self::load( $user )->user_email;
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
			$user = self::load( $user );
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

	// =============================================================================================
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
