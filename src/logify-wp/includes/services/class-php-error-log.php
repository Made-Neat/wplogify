<?php
/**
 * Contains the Notes_Page class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class Logify_WP\Notes_Page
 *
 * Contains methods for formatting notes entries for display in the admin area.
 */
class PHP_Error_Log {

	/**
	 * Initialize the log page.
	 */
	public static function init() {
	}

    private static function ensure_feature_enabled() {
		if (!get_option('logify_wp_enable_notes', false)) {
			wp_send_json_error(['message' => 'PHP Error Log feature is not enabled in settings.']);
		}
	}	
	/**
	 * Display the log page.
	 */
	public static function display_php_error_log_page() {
		self::ensure_feature_enabled();
		
		if ( ! Access_Control::can_access_log_page() ) {
			// Disallow access.
			wp_die( esc_html( 'Sorry, you are not allowed to access this page.' ), 403 );
		}

		// Get all the data required for the log page.
		//$post_types  = Note_Repository::get_post_types();
		//$taxonomies  = Note_Repository::get_taxonomies();
		//$note_types = Note_Repository::get_event_types();
		$users       = Event_Repository::get_users();
		$roles       = Event_Repository::get_roles();

		// Include the log page template.
		include LOGIFY_WP_PLUGIN_DIR . 'templates/php-error-log-page.php';
	}

	/**
	 * Get the user's preference for the number of items to show per page.
	 *
	 * @return int The number of items to show per page.
	 */
	public static function get_items_per_page(): int {
		$page_length = (int) get_user_option( 'logify_wp_events_per_page', get_current_user_id() );
		return $page_length ? $page_length : 20;
	}

}
