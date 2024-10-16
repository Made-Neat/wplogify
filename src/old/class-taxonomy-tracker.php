<?php
/**
 * Contains the Taxonomy_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

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
		// TODO Later.
		// add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 10, 0 );
	}

	/**
	 * Triggered on page load. Tracks any changes to the known taxonomies.
	 */
	public static function on_shutdown() {
		// Get the core properties of the current taxonomies.
		$current_taxonomies = Taxonomy_Utility::get_current_taxonomies_core_properties();

		// Get the taxonomies remembered by Logify WP.
		$known_taxonomies = get_option( 'logify_wp_known_taxonomies', array() );

		// Get all the taxonomy slugs.
		$taxonomy_slugs = array_unique( array_merge( array_keys( $current_taxonomies ), array_keys( $known_taxonomies ) ) );
		sort( $taxonomy_slugs );

		// Get the new expiry datetime.
		$now            = DateTimes::current_datetime();
		$new_expiry     = DateTimes::add_days( $now, 1 );
		$str_new_expiry = DateTimes::format_datetime_mysql( $new_expiry );

		foreach ( $taxonomy_slugs as $taxonomy_slug ) {
			if ( ! key_exists( $taxonomy_slug, $known_taxonomies ) ) {
				// Add a new one.
				$known_taxonomies[ $taxonomy_slug ]           = $current_taxonomies[ $taxonomy_slug ];
				$known_taxonomies[ $taxonomy_slug ]['expiry'] = $str_new_expiry;

				// Create the event.
				$taxonomy_obj = Taxonomy_Utility::load( $taxonomy_slug );
				$event        = Event::create( 'Taxonomy Added', $taxonomy_obj, all_users: true );

				// An event should always be created, but we should check.
				if ( $event ) {
					$event->save();
				}
			} elseif ( ! key_exists( $taxonomy_slug, $current_taxonomies ) ) {
				// Remove an old one.
				// Check expiry.
				$current_expiry = isset( $known_taxonomies[ $taxonomy_slug ]['expiry'] )
					? DateTimes::create_datetime( $known_taxonomies[ $taxonomy_slug ]['expiry'] ) : null;
				if ( ! $current_expiry ) {
					// Don't remove it, just set the expiry. We'll remove it later, if it isn't re-registered.
					$known_taxonomies[ $taxonomy_slug ]['expiry'] = $str_new_expiry;
				} elseif ( $now > $current_expiry ) {
					// It's expired, remove it.
					unset( $known_taxonomies[ $taxonomy_slug ] );

					// Create an event.
					// The usual get_core_properties() won't work here because the taxonomy is no
					// longer registered, so we use the properties remembered in the option.
					$taxonomy_ref = new Object_Reference( 'taxonomy', $taxonomy_slug, $known_taxonomies[ $taxonomy_slug ]['label'] );
					$event        = Event::create( 'Taxonomy Removed', $taxonomy_ref, all_users: true );

					// An event should always be created, but we should check.
					if ( $event ) {
						// Add the taxonomy properties.
						$event->add_props( Taxonomy_Utility::get_core_properties_from_array( $known_taxonomies[ $taxonomy_slug ] ) );
						$event->save();
					}
				}
			} else {
				// The taxonomy is current and known. Update the expiry date.
				$known_taxonomies[ $taxonomy_slug ]['expiry'] = $str_new_expiry;
			}
		}

		// Debug::info( $known_taxonomies );

		// Update the known taxonomies.
		update_option( 'logify_wp_known_taxonomies', $known_taxonomies );
	}
}
