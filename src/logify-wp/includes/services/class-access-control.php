<?php
/**
 * Contains the Access_Control class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use WP_User;

/**
 * Class Logify_WP\Access_Control
 *
 * This class encapsulates access control methods used by the plugin.
 */
class Access_Control {

	/**
	 * Checks if the user has access based on their roles.
	 *
	 * @param null|int|WP_User $user  The user object or ID, or null for the current user.
	 * @param array|string     $roles A role or array of roles to check against.
	 * @return bool Returns true if the user has any of the specified roles, false otherwise.
	 */
	public static function user_has_role( null|int|WP_User $user, array|string $roles ): bool {
		// Get the user.
		$user_data = User_Utility::get_user_data( $user );

		// Check we have a user.
		if ( empty( $user_data['id'] ) ) {
			return false;
		}

		if ( is_string( $roles ) ) {
			// If only a single role is given, check if the user has it.
			return in_array( $roles, $user_data['roles'], true );
		} else {
			// If an array of roles is given, check for overlap.
			return count( array_intersect( $user_data['roles'], $roles ) ) > 0;
		}
	}

	/**
	 * Check if a user has access to the log page via their role(s).
	 *
	 * @param null|int|WP_User $user The user object or ID, or null for the current user.
	 * @return bool True if the user has access via their role(s), false otherwise.
	 */
	public static function user_has_access_via_role( null|int|WP_User $user = null ): bool {
		// Get the user.
		$user_data = User_Utility::get_user_data( $user );

		// Check we have a user.
		if ( empty( $user_data['id'] ) ) {
			return false;
		}

		// Check if the user is an admin.
		if ( self::user_has_role( $user_data['object'], 'administrator' ) ) {
			return true;
		}

		// Get the roles with access.
		$roles_with_access = Plugin_Settings::get_roles_with_access();

		// Check if the user has any of the roles with access.
		return self::user_has_role( $user_data['object'], $roles_with_access );
	}

	/**
	 * Check if a user has individual access to the log page.
	 *
	 * @param null|int|WP_User $user  The user object or ID, or null for the current user.
	 * @return bool True if the user has individual access, false otherwise.
	 */
	public static function user_has_individual_access( null|int|WP_User $user = null ): bool {
		// Get the user.
		$user_data = User_Utility::get_user_data( $user );

		// Check we have a user.
		if ( empty( $user_data['id'] ) ) {
			return false;
		}

		// Get the users with access.
		$users_with_access = Plugin_Settings::get_users_with_access();

		// Check if the user has individual access.
		return in_array( $user_data['id'], $users_with_access, true );
	}

	/**
	 * Check if a user can access the log page.
	 *
	 * @param null|int|WP_User $user  The user object or ID, or null for the current user.
	 * @return bool True if the user has access to the log page, false otherwise.
	 */
	public static function can_access_log_page( null|int|WP_User $user = null ): bool {
		// Get the user.
		$user_data = User_Utility::get_user_data( $user );

		// Check we have a user.
		if ( empty( $user_data['id'] ) ) {
			return false;
		}

		// Check if the user has access via their role(s) or individually.
		return self::user_has_access_via_role( $user_data['object'] ) || self::user_has_individual_access( $user_data['object'] );
	}

	/**
	 * Check if a user can access the settings page.
	 *
	 * @param null|int|WP_User $user  The user object or ID, or null for the current user.
	 * @return bool True if the user has access to the settings page, false otherwise.
	 */
	public static function can_access_settings_page( null|int|WP_User $user = null ): bool {
		// Get the user.
		$user_data = User_Utility::get_user_data( $user );

		// Check we have a user.
		if ( empty( $user_data['id'] ) ) {
			return false;
		}

		return self::can_access_log_page( $user_data['object'] ) && user_can( $user_data['object'], 'manage_options' );
	}
}
