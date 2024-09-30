<?php
/**
 * Contains the Taxonomy_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Taxonomy;

/**
 * Class WP_Logify\Taxonomy_Tracker
 *
 * Provides tracking of events related to taxonomies.
 */
class Taxonomy_Tracker {

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Track taxonomy addition or removal.
		add_action( 'init', array( __CLASS__, 'on_init' ), 20, 0 );
	}

	/**
	 * Triggered on page load. Tracks any changes to the known taxonomies.
	 */
	public static function on_init() {
		// Get the core properties of the current taxonomies.
		$current_taxonomies = Taxonomy_Utility::get_current_taxonomies_core_properties();

		// Get the taxonomies remembered by WP Logify.
		$known_taxonomies = get_option( 'wp_logify_known_taxonomies', array() );

		// Find the new ones.
		$new_taxonomies = array_diff_key( $current_taxonomies, $known_taxonomies );

		// Find the removed ones.
		$removed_taxonomies = array_diff_key( $known_taxonomies, $current_taxonomies );

		// Log events for added taxonomies.
		foreach ( $new_taxonomies as $taxonomy => $taxonomy_info ) {
			$taxonomy_obj = Taxonomy_Utility::load( $taxonomy );
			Logger::log_event( 'Taxonomy Added', $taxonomy_obj );
		}

		// Log events for removed taxonomies.
		foreach ( $removed_taxonomies as $taxonomy => $taxonomy_info ) {
			if ( is_array( $taxonomy_info ) && ! empty( $taxonomy_info['label'] ) ) {
				// The usual get_core_properties() won't work here because the taxonomy is no longer
				// registered, so we use the properties remembered in the wp_logify_known_taxonomies option.
				$taxonomy_ref = new Object_Reference( 'taxonomy', $taxonomy, $taxonomy_info['label'] );
				$event        = Event::create( 'Taxonomy Removed', $taxonomy_ref );
				$event->add_props( Taxonomy_Utility::get_core_properties_from_array( $taxonomy_info ) );
				$event->save();
			}
		}

		// Update the remembered taxonomy info.
		update_option( 'wp_logify_known_taxonomies', $current_taxonomies );
	}
}
