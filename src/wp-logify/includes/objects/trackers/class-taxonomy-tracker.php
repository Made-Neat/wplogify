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
class Taxonomy_Tracker extends Object_Tracker {

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
		$current_taxonomies = array_values( get_taxonomies() );
		$known_taxonomies   = get_option( 'wp_logify_known_taxonomies', array() );

		$new_taxonomies     = array_diff( $current_taxonomies, $known_taxonomies );
		$removed_taxonomies = array_diff( $known_taxonomies, $current_taxonomies );

		// Log events for added taxonomies.
		foreach ( $new_taxonomies as $taxonomy ) {
			$taxonomy_obj = Taxonomy_Utility::load( $taxonomy );
			Logger::log_event( 'Taxonomy Added', $taxonomy_obj );
		}

		// Log events for removed taxonomies.
		foreach ( $removed_taxonomies as $taxonomy ) {
			$taxonomy_obj = Taxonomy_Utility::load( $taxonomy );
			Logger::log_event( 'Taxonomy Removed', $taxonomy_obj );
		}

		// Update the stored taxonomies.
		update_option( 'wp_logify_known_taxonomies', $current_taxonomies );
	}
}
