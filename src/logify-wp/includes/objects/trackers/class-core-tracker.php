<?php
/**
 * Contains the Core_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class Logify_WP\Core_Tracker
 *
 * Provides tracking of events related to WordPress core.
 */
class Core_Tracker {

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Core update.
		add_action( '_core_updated_successfully', array( __CLASS__, 'on_core_updated_successfully' ), 10, 1 );
	}

	/**
	 * Fires after WordPress core has been successfully updated.
	 *
	 * @param string $wp_version The current WordPress version.
	 */
	public static function on_core_updated_successfully( string $wp_version ) {
		// Get the old version.
		$old_version = get_bloginfo( 'version' );

		// Check if this is an upgrade, downgrade, or re-install.
		$old_version_numeric = Strings::version_to_float( $old_version );
		$new_version_numeric = Strings::version_to_float( $wp_version );

		if ( $old_version_numeric < $new_version_numeric ) {
			$verb = 'Upgraded';
		} elseif ( $old_version_numeric > $new_version_numeric ) {
			$verb = 'Downgraded';
		} else {
			$verb = 'Re-installed';
		}

		// Get the event type.
		$event_type = "Core $verb";

		// Get the properties.
		$props = array();
		Property::update_array( $props, 'version', null, $old_version, $wp_version );

		// Log the event.
		$core_ref = new Object_Reference( 'core', $wp_version );
		Logger::log_event( $event_type, $core_ref, null, $props );
	}
}
