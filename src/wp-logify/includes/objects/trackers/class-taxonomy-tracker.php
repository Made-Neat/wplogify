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
		// Get the current taxonomies as objects.
		$current_taxonomy_objects = get_taxonomies( array(), 'objects' );

		// Convert to an array containing just the core properties we want to show in the log.
		// Keyed by taxonomy name so we canuse array_diff_key() later.
		$current_taxonomies = array();
		foreach ( $current_taxonomy_objects as $taxonomy_obj ) {
			$current_taxonomies[ $taxonomy_obj->name ] = array(
				'name'    => $taxonomy_obj->name,
				'label'   => $taxonomy_obj->label,
				'show_ui' => $taxonomy_obj->show_ui,
			);
		}
		// debug( 'current_taxonomies', $current_taxonomies );

		// Get the taxonomies remembered by WP Logify.
		$known_taxonomies = get_option( 'wp_logify_known_taxonomies', array() );
		// debug( 'known_taxonomies', $known_taxonomies );

		// Find the new ones.
		$new_taxonomies = array_diff_key( $current_taxonomies, $known_taxonomies );
		// debug( 'new_taxonomies', $new_taxonomies );

		// Find the removed ones.
		$removed_taxonomies = array_diff_key( $known_taxonomies, $current_taxonomies );
		// debug( 'removed_taxonomies', $removed_taxonomies );

		// Log events for added taxonomies.
		foreach ( $new_taxonomies as $taxonomy => $taxonomy_info ) {
			$taxonomy_obj = Taxonomy_Utility::load( $taxonomy );
			Logger::log_event( 'Taxonomy Added', $taxonomy_obj );
		}

		// Log events for removed taxonomies.
		foreach ( $removed_taxonomies as $taxonomy => $taxonomy_info ) {
			// The usual get_core_properties() won't work here because the taxonomy is no longer
			// registered, so we use the properties remembered in the wp_logify_known_taxonomies option.
			$taxonomy_ref = new Object_Reference( 'taxonomy', $taxonomy, $taxonomy_info['label'] );
			$event        = Event::create( 'Taxonomy Removed', $taxonomy_ref );
			$event->set_props( Taxonomy_Utility::get_core_properties_from_array( $taxonomy_info ) );
			$event->save();
		}

		// Update the remembered taxonomy info.
		update_option( 'wp_logify_known_taxonomies', $current_taxonomies );
	}
}
