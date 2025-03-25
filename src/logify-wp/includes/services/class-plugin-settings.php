<?php
/**
 * Contains the Plugin_Settings class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class Logify_WP\Plugin_Settings
 *
 * This class encapsulates properties and methods relating to plugin settings.
 */
class Plugin_Settings {

	// =============================================================================================
	// Default values for settings.

	/**
	 * The default value for the 'delete on uninstall' setting.
	 *
	 * @var bool
	 */
	private const DEFAULT_DELETE_ON_UNINSTALL = false;

	/**
	 * The default value for the 'enable notes' setting.
	 *
	 * @var bool
	 */
	private const DEFAULT_ENABLE_NOTES = false;

	/**
	 * The default value for the 'roles with access' setting.
	 *
	 * @var array
	 */
	private const DEFAULT_ROLES_WITH_ACCESS = array( 'administrator' );

	/**
	 * The default value for the 'users with access' setting.
	 *
	 * @var array
	 */
	private const DEFAULT_USERS_WITH_ACCESS = array();

	/**
	 * The default value for the 'show in admin bar' setting.
	 *
	 * @var bool
	 */
	private const DEFAULT_SHOW_IN_ADMIN_BAR = true;

	/**
	 * The default value for the 'keep period quantity' setting.
	 *
	 * @var int
	 */
	private const DEFAULT_KEEP_PERIOD_QUANTITY = 12;

	/**
	 * The default value for the 'keep period units' setting.
	 *
	 * @var string
	 */
	private const DEFAULT_KEEP_PERIOD_UNITS = 'month';
	/**
	 * The default value for the 'keep period error' setting.
	 *
	 * @var string
	 */
	private const DEFAULT_KEEP_PERIOD_ERRORS = '10mins';
	/**
	 * The default value for the 'error type' setting.
	 *
	 * @var string
	 */
	private const DEFAULT_PHP_ERROR_TYPES = array('Fatal Error');
	private const DEFAULT_COMMENT_TRACKING_STATE = false;

	private const DEFAULT_CAPTURE_START_TIME = 1;

	/**
	 * Get the names of all current roles.
	 * These will be the default value for the 'roles to track' setting.
	 *
	 * @return array
	 */
	private static function get_roles() {
		return array_keys( wp_roles()->roles );
	}

	// ---------------------------------------------------------------------------------------------
	// Functions associated with action hooks.

	/**
	 * Initializes the class by adding WordPress actions.
	 */
	public static function init() {
		// Register the settings.
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Registers the settings for the Logify WP plugin.
	 */
	public static function register_settings() {
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_enable_notes',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => self::DEFAULT_ENABLE_NOTES,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_delete_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => self::DEFAULT_DELETE_ON_UNINSTALL,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_roles_to_track',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_roles' ),
				'default'           => self::get_roles(),
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_roles_with_access',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_roles' ),
				'default'           => self::DEFAULT_ROLES_WITH_ACCESS,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_users_with_access',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_users' ),
				'default'           => self::DEFAULT_USERS_WITH_ACCESS,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_show_in_admin_bar',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => self::DEFAULT_SHOW_IN_ADMIN_BAR,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_keep_period_quantity',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => self::DEFAULT_KEEP_PERIOD_QUANTITY,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_keep_period_units',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => self::DEFAULT_KEEP_PERIOD_UNITS,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_keep_period_errors',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => self::DEFAULT_KEEP_PERIOD_ERRORS,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_php_error_types',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_errors' ),
				'default'           => self::DEFAULT_PHP_ERROR_TYPES,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_comment_tracking',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => self::DEFAULT_COMMENT_TRACKING_STATE,
			)
		);
		register_setting(
			'logify_wp_settings_group',
			'logify_wp_capture_start_time',
			array(
				'type'	=> 'integer',
				'sanitize_callback'=>'absint',
				'default' => self::DEFAULT_CAPTURE_START_TIME,
			)
			);
	}

	/**
	 * Deletes all settings options for the Logify WP plugin.
	 */
	public static function delete_all() {
		// Delete all options that start with logify_wp.
		global $wpdb;
		$options = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_name FROM %i WHERE option_name LIKE %s',
				$wpdb->options,
				'logify_wp%'
			)
		);
		foreach ( $options as $option ) {
			delete_option( $option->option_name );
		}
	}

	/**
	 * Displays the settings page for the Logify WP plugin.
	 */
	public static function display_settings_page() {
		if ( ! Access_Control::can_access_settings_page() ) {
			// Disallow access.
			wp_die( esc_html( 'Sorry, you are not allowed to access this page.' ), 403 );
		}

		// Include the settings-page.php template file to render the settings page.
		include LOGIFY_WP_PLUGIN_DIR . 'templates/settings-page.php';
	}

	// ---------------------------------------------------------------------------------------------
	// Sanitize functions.

	/**
	 * Sanitizes the given array of roles by filtering out any invalid roles.
	 *
	 * @param ?array $roles The array of roles to be sanitized.
	 * @return array The sanitized array of roles.
	 */
	public static function sanitize_roles( ?array $roles ): array {
		// Handle null or empty array.
		if ( empty( $roles ) ) {
			return array();
		}

		$valid_roles = self::get_roles();
		return array_intersect( $roles, $valid_roles );
	}
	/**
	 * Sanitizes the given array of roles by filtering out any invalid roles.
	 *
	 * @param ?array $roles The array of roles to be sanitized.
	 * @return array The sanitized array of roles.
	 */
	public static function sanitize_errors( ?array $errors ): array {
		// Handle null or empty array.
		if ( empty( $errors ) ) {
			return array();
		}
		return  $errors;
	}

	/**
	 * Sanitizes the given array of user IDs by filtering out any invalid user IDs.
	 *
	 * @param ?array $user_ids The array of user IDs to be sanitized.
	 * @return array The sanitized array of user IDs.
	 */
	public static function sanitize_users( ?array $user_ids ) {
		// Handle null or empty array.
		if ( empty( $user_ids ) ) {
			return array();
		}

		// Make sure the array of user IDs only contains integers.
		$user_ids = array_map( 'intval', $user_ids );

		// Get the IDs of all users, and make sure the array only contains integers, too.
		$valid_user_ids = get_users( array( 'fields' => 'ID' ) );
		$valid_user_ids = array_map( 'intval', $valid_user_ids );

		// Return the intersection of the two arrays.
		return array_intersect( $user_ids, $valid_user_ids );
	}

	// ---------------------------------------------------------------------------------------------
	// Functions to get the current values of settings.

	/**
	 * Retrieves the value of the 'delete on uninstall' setting.
	 *
	 * @return bool The delete on uninstall setting.
	 */
	public static function get_delete_on_uninstall(): bool {
		return get_option( 'logify_wp_delete_on_uninstall', self::DEFAULT_DELETE_ON_UNINSTALL );
	}

	/**
	 * Retrieves the value of the 'enable notes' setting.
	 *
	 * @return bool Enabled or disable notes feature.
	 */
	public static function get_enable_notes(): bool {
		return get_option( 'logify_wp_enable_notes', self::DEFAULT_ENABLE_NOTES );
	}

	/**
	 * Retrieves the value of the 'roles to track' setting.
	 *
	 * @return array The roles to track.
	 */
	public static function get_roles_to_track(): array {
		return get_option( 'logify_wp_roles_to_track', self::get_roles() );
	}

	/**
	 * Retrieves the value of the 'roles with access' setting.
	 *
	 * @return array The roles with access to the log page.
	 */
	public static function get_roles_with_access(): array {
		return get_option( 'logify_wp_roles_with_access', self::DEFAULT_ROLES_WITH_ACCESS );
	}

	/**
	 * Retrieves the value of the 'users with access' setting.
	 *
	 * @return array The users with access to the log page.
	 */
	public static function get_users_with_access(): array {
		return get_option( 'logify_wp_users_with_access', self::DEFAULT_USERS_WITH_ACCESS );
	}

	/**
	 * Retrieves the value of the 'show in admin bar' setting.
	 *
	 * @return array If the Logify WP submenu should be shown in the admin bar.
	 */
	public static function get_show_in_admin_bar(): bool {
		return get_option( 'logify_wp_show_in_admin_bar', self::DEFAULT_SHOW_IN_ADMIN_BAR );
	}

	/**
	 * Retrieves the value of the 'keep period quantity' setting.
	 *
	 * @return int The keep period quantity setting.
	 */
	public static function get_keep_period_quantity(): int {
		return get_option( 'logify_wp_keep_period_quantity', self::DEFAULT_KEEP_PERIOD_QUANTITY );
	}

	/**
	 * Retrieves the keep period units setting for the Logify WP plugin.
	 *
	 * @return string The keep period units setting.
	 */
	public static function get_keep_period_units(): string {
		return get_option( 'logify_wp_keep_period_units', self::DEFAULT_KEEP_PERIOD_UNITS );
	}
	/**
	 * Retrieves the keep period units setting for the Logify WP plugin.
	 *
	 * @return string The keep period units setting.
	 */
	public static function get_keep_period_errors(): string {
		return get_option( 'logify_wp_keep_period_errors', self::DEFAULT_KEEP_PERIOD_ERRORS );
	}

	public static function get_php_error_types(): array {
		return get_option( 'logify_wp_php_error_types', self::DEFAULT_PHP_ERROR_TYPES );
	}
	public static function get_comment_tracking_state(): bool {
		return get_option( 'logify_wp_comment_tracking', self::DEFAULT_COMMENT_TRACKING_STATE );
	}
	public static function get_capture_start_time(): int {
		return get_option('logify_wp_capture_start_time', self::DEFAULT_CAPTURE_START_TIME );
	}
}
