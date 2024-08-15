<?php
/**
 * Contains the Core_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Core_Tracker
 *
 * Provides tracking of events related to WordPress core.
 */
class Core_Tracker extends Object_Tracker {

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Core update.
		add_action( 'load-update-core.php', array( __CLASS__, 'on_update_core_page_loaded' ) );
		add_action( '_core_updated_successfully', array( __CLASS__, 'on_core_updated_successfully' ), 10, 1 );
	}

	/**
	 * Fires when wp-admin/update-core.php is loaded.
	 */
	public static function on_update_core_page_loaded() {
		// Get the current version of core.
		$current_wp_version = get_bloginfo( 'version' );

		// Store it in a transient.
		set_transient( 'wp_logify_core_version', $current_wp_version );
	}

	/**
	 * Fires after WordPress core has been successfully updated.
	 *
	 * @param string $wp_version The current WordPress version.
	 */
	public static function on_core_updated_successfully( string $wp_version ) {

		// Get the old version.
		$old_version = get_transient( 'wp_logify_core_version' );

		// Get the new version.
		$new_version = $wp_version; // get_bloginfo( 'version' );

		// debug( $wp_version );

		// Check if this is an upgrade, downgrade, or re-install.
		$old_version_numeric = Types::version_to_float( $old_version );
		$new_version_numeric = Types::version_to_float( $new_version );

		if ( $old_version_numeric < $new_version_numeric ) {
			$verb = 'Upgraded';
		} elseif ( $old_version_numeric > $new_version_numeric ) {
			$verb = 'Downgraded';
		} else {
			$verb = 'Re-installed';
		}

		// Get the event type.
		$event_type = "WordPress Core $verb";

		// Get the properties.
		$props = array();
		Property::update_array( $props, 'version', null, $old_version, $new_version );

		// Log the event.
		$core_ref = new Object_Reference( 'core', null, null );
		Logger::log_event( $event_type, $core_ref, null, $props );
	}
}
