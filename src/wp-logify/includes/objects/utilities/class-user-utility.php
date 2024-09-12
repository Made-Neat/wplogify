<?php
/**
 * Contains the User_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;
use Exception;
use WP_Query;
use WP_User;

/**
 * Class WP_Logify\User_Utility
 *
 * Provides methods for working with users.
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

		// Display name.
		Property::update_array( $properties, 'display_name', $wpdb->users, Object_Reference::new_from_wp_object( $user ) );

		// ID.
		Property::update_array( $properties, 'ID', $wpdb->users, (int) $user->ID );

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
	 * @param int|string $user_id  The ID of the user.
	 * @param ?string    $old_name The username at the time of the event.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $user_id, ?string $old_name = null ): string {
		// Unknown user.
		if ( ! $user_id && ! $old_name ) {
			return "<span class='wp-logify-deleted-object'>Unknown</span>";
		}

		// Load the user.
		$user = $user_id ? self::load( $user_id ) : null;

		// Try to load the user by email or username if it wasn't found.
		if ( ! $user ) {
			if ( Strings::looks_like_email( $old_name ) ) {
				// Load by email.
				$user = self::load_user_by_email( $old_name );
			} else {
				// Load by username.
				$user = self::load_user_by_username( $old_name );
			}
		}

		// If the user exists, get a link.
		if ( $user ) {
			// Get the name.
			$name = self::get_name( $user->ID );

			// Get the user edit URL.
			$url = admin_url( "user-edit.php?user_id={$user->ID}" );

			// Return the link.
			return "<a href='$url' class='wp-logify-object'>$name</a>";
		}

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = "User $user_id";
		}

		// The user no longer exists. Construct the 'unknown' span element.
		return "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Load a user by username.
	 *
	 * @param string $username The username of the user.
	 * @return ?WP_User The user object if found, null otherwise.
	 */
	public static function load_user_by_username( string $username ): ?WP_User {
		$user = get_user_by( 'login', $username );
		return $user ? $user : null;
	}

	/**
	 * Load a user by email address.
	 *
	 * @param string $email The email address of the user.
	 * @return ?WP_User The user object if found, null otherwise.
	 */
	public static function load_user_by_email( string $email ) {
		$user = get_user_by( 'email', $email );
		return $user ? $user : null;
	}

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
		if ( ! empty( $result['status'] ) && $result['status'] === 'success' ) {
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

	/**
	 * Given a user object, ID, or username, get the user data required to log an event.
	 *
	 * This doesn't necessarily have to be a valid user; it could be one that is deleted or unknown.
	 *
	 * If no user is specified, return null.
	 *
	 * @param null|int|string|Object_Reference|WP_User $user
	 * @return ?array Array with the user's id, name, and roles.
	 */
	public static function get_user_data( null|int|string|Object_Reference|WP_User $user ): ?array {
		// Default values for the user data.
		$user_id    = 0;
		$user_name  = '';
		$user_roles = array();

		// Extract the user information from the provided value.
		if ( $user === null ) {
			// If no user is specified, default to the current user.
			$user = wp_get_current_user();
		} elseif ( is_int( $user ) || ( is_string( $user ) && Strings::looks_like_int( $user ) ) ) {
			// Given a user ID, load the user object.
			$user_id = (int) $user;
			if ( $user_id > 0 ) {
				$user = self::load( $user_id );
			}
		} elseif ( is_string( $user ) && $user !== '' ) {
			// If a string is provided (which is not an integer), it will be either the login name
			// or email address. Try to load the user.
			$user_name = $user;
			if ( Strings::looks_like_email( $user_name ) ) {
				$user = self::load_user_by_email( $user_name );
			} else {
				$user = self::load_user_by_username( $user_name );
			}
		} elseif ( $user instanceof Object_Reference ) {
			// If an Object_Reference is given, get the user ID and name, and try to load the user
			// object.

			// If the object type is not user, this is invalid.
			if ( $user->type !== 'user' ) {
				throw new Exception( "Invalid object type: '{$user->type}'" );
			}

			$user_id   = (int) $user->key;
			$user_name = $user->name ?? '';
			if ( $user_id > 0 ) {
				$user = self::load( $user_id );
			}
		}

		// If we have a WP_User object, extract the desired info.
		if ( $user instanceof WP_User ) {
			$user_id    = (int) $user->ID;
			$user_name  = self::get_name( $user_id ) ?? '';
			$user_roles = $user->roles;
		}

		// Return the user data.
		return array(
			'id'     => $user_id,
			'name'   => $user_name,
			'roles'  => empty( $user_roles ) ? array( 'none' ) : $user_roles,
			'ref'    => new Object_Reference( 'user', $user_id, $user_name ),
			'object' => $user instanceof WP_User ? $user : null,
		);
	}
}
