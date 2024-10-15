<?php
/**
 * Contains the Taxonomy_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use WP_Taxonomy;

/**
 * Class Logify_WP\Taxonomy_Tracker
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

		// Get the taxonomies remembered by Logify WP.
		$known_taxonomies = get_option( 'logify_wp_known_taxonomies', array() );

		// Find the new ones.
		$new_taxonomies = array_diff_key( $current_taxonomies, $known_taxonomies );

		// Find the removed ones.
		$removed_taxonomies = array_diff_key( $known_taxonomies, $current_taxonomies );

		// Make a note of whether we're tracking this user's role and can therefore create events.
		$tracking_this_user = true;

		// Log events for added taxonomies.
		foreach ( $new_taxonomies as $taxonomy => $taxonomy_info ) {
			$taxonomy_obj = Taxonomy_Utility::load( $taxonomy );
			$event        = Event::create( 'Taxonomy Added', $taxonomy_obj );

			// If the event could not be created, we aren't tracking this user.
			// We could exit the method here except we want to update the remembered taxonomies.
			if ( ! $event ) {
				$tracking_this_user = false;
				break;
			}

			$event->save();
		}

		if ( $tracking_this_user ) {
			// Log events for removed taxonomies.
			foreach ( $removed_taxonomies as $taxonomy => $taxonomy_info ) {
				if ( is_array( $taxonomy_info ) && ! empty( $taxonomy_info['label'] ) ) {
					// The usual get_core_properties() won't work here because the taxonomy is no longer
					// registered, so we use the properties remembered in the logify_wp_known_taxonomies option.
					$taxonomy_ref = new Object_Reference( 'taxonomy', $taxonomy, $taxonomy_info['label'] );
					$event        = Event::create( 'Taxonomy Removed', $taxonomy_ref );

					// If the event could not be created, we aren't tracking this user.
					// We can exit the loop.
					// This shouldn't happen since adding the $tracking_this_user variable, but
					// we'll leave it here for safety.
					if ( ! $event ) {
						break;
					}

					// Add the taxonomy properties.
					$event->add_props( Taxonomy_Utility::get_core_properties_from_array( $taxonomy_info ) );
					$event->save();
				}
			}
		}

		// Update the remembered taxonomy info.
		update_option( 'logify_wp_known_taxonomies', $current_taxonomies );
	}
}
