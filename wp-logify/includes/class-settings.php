<?php
/**
 * Contains the Settings class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Settings
 *
 * This class encapsulates properties and methods relating to plugin settings.
 */
class Settings {

	/**
	 * The default value for the API key.
	 *
	 * @var array
	 */
	private const DEFAULT_API_KEY = '';

	/**
	 * The default value for the delete on uninstall setting.
	 *
	 * @var array
	 */
	private const DEFAULT_DELETE_ON_UNINSTALL = false;

	/**
	 * The default value for the access control setting.
	 *
	 * @var string
	 */
	private const DEFAULT_ACCESS_CONTROL = 'only_me';

	/**
	 * The default value for the roles to track setting.
	 *
	 * @var array
	 */
	private const DEFAULT_ROLES_TO_TRACK = array( 'administrator' );

	/**
	 * The default value for the view roles setting.
	 *
	 * @var array
	 */
	private const DEFAULT_VIEW_ROLES = array( 'administrator' );

	/**
	 * The default value for the keep forever setting.
	 *
	 * @var bool
	 */
	private const DEFAULT_KEEP_FOREVER = true;

	/**
	 * The default value for the keep period quantity setting.
	 *
	 * @var int
	 */
	private const DEFAULT_KEEP_PERIOD_QUANTITY = 1;

	/**
	 * The default value for the keep period units setting.
	 *
	 * @var string
	 */
	private const DEFAULT_KEEP_PERIOD_UNITS = 'year';

	/**
	 * The default value for the WP-Cron tracking setting.
	 *
	 * @var bool
	 */
	private const DEFAULT_WP_CRON_TRACKING = false;

	/**
	 * Initializes the class by adding WordPress actions.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Registers the settings for the WP Logify plugin.
	 */
	public static function register_settings() {
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => self::DEFAULT_API_KEY,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_delete_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => self::DEFAULT_DELETE_ON_UNINSTALL,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_access_control',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => self::DEFAULT_ACCESS_CONTROL,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_roles_to_track',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WP_Logify\Admin', 'sanitize_roles' ),
				'default'           => self::DEFAULT_ROLES_TO_TRACK,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_view_roles',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WP_Logify\Admin', 'sanitize_roles' ),
				'default'           => self::DEFAULT_VIEW_ROLES,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_keep_forever',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => self::DEFAULT_KEEP_FOREVER,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_keep_period_quantity',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => self::DEFAULT_KEEP_PERIOD_QUANTITY,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_keep_period_units',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => self::DEFAULT_KEEP_PERIOD_UNITS,
			)
		);
		register_setting(
			'wp_logify_settings_group',
			'wp_logify_wp_cron_tracking',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'absint',
				'default'           => self::DEFAULT_WP_CRON_TRACKING,
			)
		);
	}

	/**
	 * Retrieves the API key for the WP Logify plugin.
	 *
	 * @return string The API key.
	 */
	public static function get_api_key(): string {
		return get_option( 'wp_logify_api_key', self::DEFAULT_API_KEY );
	}

	/**
	 * Retrieves the delete on uninstall setting for the WP Logify plugin.
	 *
	 * @return bool The delete on uninstall setting.
	 */
	public static function get_delete_on_uninstall(): bool {
		return get_option( 'wp_logify_delete_on_uninstall', self::DEFAULT_DELETE_ON_UNINSTALL );
	}

	/**
	 * Retrieves the access control setting for the WP Logify plugin.
	 *
	 * @return string The access control setting.
	 */
	public static function get_access_control(): string {
		return get_option( 'wp_logify_access_control', self::DEFAULT_ACCESS_CONTROL );
	}

	/**
	 * Retrieves the roles to track for the WP Logify plugin.
	 *
	 * @return array The roles to track.
	 */
	public static function get_roles_to_track(): array {
		return get_option( 'wp_logify_roles_to_track', self::DEFAULT_ROLES_TO_TRACK );
	}

	/**
	 * Retrieves the view roles setting for the WP Logify plugin.
	 *
	 * @return array The view roles setting.
	 */
	public static function get_view_roles(): array {
		return get_option( 'wp_logify_view_roles', self::DEFAULT_VIEW_ROLES );
	}

	/**
	 * Retrieves the keep forever setting for the WP Logify plugin.
	 *
	 * @return bool The keep forever setting.
	 */
	public static function get_keep_forever(): bool {
		return get_option( 'wp_logify_keep_forever', self::DEFAULT_KEEP_FOREVER );
	}

	/**
	 * Retrieves the keep period quantity setting for the WP Logify plugin.
	 *
	 * @return int The keep period quantity setting.
	 */
	public static function get_keep_period_quantity(): int {
		return get_option( 'wp_logify_keep_period_quantity', self::DEFAULT_KEEP_PERIOD_QUANTITY );
	}

	/**
	 * Retrieves the keep period units setting for the WP Logify plugin.
	 *
	 * @return string The keep period units setting.
	 */
	public static function get_keep_period_units(): string {
		return get_option( 'wp_logify_keep_period_units', self::DEFAULT_KEEP_PERIOD_UNITS );
	}

	/**
	 * Retrieves the WP-Cron tracking setting for the WP Logify plugin.
	 *
	 * @return bool The WP-Cron tracking setting.
	 */
	public static function get_wp_cron_tracking(): bool {
		return get_option( 'wp_logify_wp_cron_tracking', self::DEFAULT_WP_CRON_TRACKING );
	}

	/**
	 * Displays the settings page for the WP Logify plugin.
	 */
	public static function display_settings_page() {
		// Include the settings-page.php template file to render the settings page.
		include plugin_dir_path( __FILE__ ) . '../templates/settings-page.php';
	}
}
