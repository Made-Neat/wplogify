<?php
/**
 * Contains the Widget_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Widget_Tracker
 *
 * Provides tracking of events related to comments.
 */
class Widget_Tracker {

	/**
	 * Widget events.
	 *
	 * @var Event[]
	 */
	private static $events = array();

	/**
	 * Widget areas.
	 *
	 * @var array
	 */
	private static $areas = array();

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Changes to widget options.
		add_action( 'update_option', array( __CLASS__, 'on_update_option' ), 10, 3 );
		add_action( 'updated_option', array( __CLASS__, 'on_updated_option' ), 10, 3 );
		add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 10, 0 );
	}

	/**
	 * Fires immediately before an option value is updated.
	 *
	 * @param string $option           Name of the option to update.
	 * @param array  $old_option_value The old option value.
	 * @param array  $new_option_value The new option value.
	 */
	public static function on_update_option( $option, $old_option_value, $new_option_value ) {
		// Record changes to the widget locations.
		if ( $option === 'sidebars_widgets' ) {
			self::record_widget_areas( $old_option_value );
			self::record_widget_areas( $new_option_value );
			return;
		}

		// Beyond this point we're only interested in changes to widget details.
		if ( ! Strings::starts_with( $option, 'widget_' ) ) {
			return;
		}

		// Get the widget type from the option name.
		$widget_type = substr( $option, strlen( 'widget_' ) );

		// Look for widget updates or deletions.
		foreach ( $old_option_value as $widget_number => $widget ) {
			// Ignore non-integer keys like '_multiwidget'.
			if ( ! is_int( $widget_number ) && ! Strings::looks_like_int( $widget_number ) ) {
				continue;
			}

			// Get the widget ID.
			$widget_id = $widget_type . '-' . $widget_number;

			// Load the widget details from the option values, so we get the extra properties.
			$old_widget = Widget_Utility::get_from_option( $widget_id, $old_option_value );
			$new_widget = Widget_Utility::get_from_option( $widget_id, $new_option_value );

			if ( ! key_exists( $widget_number, $new_option_value ) ) {
				// The widget is being deleted.

				// Create the event.
				self::$events[ $widget_id ] = Event::create( 'Widget Deleted', $old_widget );
			} elseif ( $old_widget !== $new_widget ) {
				// The widget is being updated.

				// Store the property changes.
				$props = array();

				// Record the changed widget settings in the event properties.
				$keys = array_unique( array_merge( array_keys( $old_widget ), array_keys( $new_widget ) ) );
				foreach ( $keys as $key ) {

					// Get the old and new values.
					$old_widget_value = $old_widget[ $key ] ?? null;
					$new_widget_value = $new_widget[ $key ] ?? null;

					if ( $key === 'content' ) {
						// Strip tags from content.
						$old_widget_value = Strings::strip_tags( $old_widget_value );
						$new_widget_value = Strings::strip_tags( $new_widget_value );
					} elseif ( $key === 'nav_menu' ) {
						// For menus, convert to an object reference.
						$old_widget_value = empty( $old_widget_value ) ? null : new Object_Reference( 'term', $old_widget_value );
						$new_widget_value = empty( $new_widget_value ) ? null : new Object_Reference( 'term', $new_widget_value );
					}

					// Compare.
					$diff = Types::get_diff( $old_widget_value, $new_widget_value );

					if ( $diff ) {
						// Record the property change.
						Property::update_array( $props, $key, null, $old_widget_value, $new_widget_value );
					}
				}

				// If there were property changes, create an event.
				if ( ! empty( $props ) ) {
					self::$events[ $widget_id ] = Event::create( 'Widget Updated', $old_widget, null, $props );
				}
			}
		}
	}

	/**
	 * Fires immediately after an option value is updated.
	 *
	 * @param string $option           Name of the option to update.
	 * @param array  $old_option_value The old option value.
	 * @param array  $new_option_value The new option value.
	 */
	public static function on_updated_option( $option, $old_option_value, $new_option_value ) {
		// Beyond this point we're only interested in changes to widget details.
		if ( ! Strings::starts_with( $option, 'widget_' ) ) {
			return;
		}

		// Get the widget type from the option name.
		$widget_type = substr( $option, strlen( 'widget_' ) );

		// Look for widget additions.
		foreach ( $new_option_value as $widget_number => $widget ) {
			// Ignore non-integer keys like '_multiwidget'.
			if ( ! is_int( $widget_number ) && ! Strings::looks_like_int( $widget_number ) ) {
				continue;
			}

			// Check if this is new.
			if ( ! key_exists( $widget_number, $old_option_value ) ) {
				// Get the widget ID.
				$widget_id = $widget_type . '-' . $widget_number;

				// Load the widget details from the option values, so we get the extra properties.
				$new_widget = Widget_Utility::get_from_option( $widget_id, $new_option_value );

				// Create the event.
				self::$events[ $widget_id ] = Event::create( 'Widget Created', $new_widget );
			}
		}
	}

	/**
	 * Record the widget areas.
	 *
	 * @param array $sidebars_widgets The value of the sidebars_widgets option.
	 */
	public static function record_widget_areas( $sidebars_widgets ) {
		// Is this the first time recording the widget areas?
		$first_time = empty( self::$areas );

		foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
			// Ignore non-array values.
			if ( ! is_array( $widgets ) ) {
				continue;
			}

			// Loop through the widget positions.
			foreach ( $widgets as $widget_id ) {
				// The first time through, get the old area; after that, get the new one.
				$key                               = $first_time ? 'old' : 'new';
				self::$areas[ $widget_id ][ $key ] = $sidebar_id;
			}
		}
	}

	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown() {

		// Update and create events for area changes.
		foreach ( self::$areas as $widget_id => $area ) {

			// Compare the old and new areas.
			$old_area = $area['old'] ?? null;
			$new_area = $area['new'] ?? null;

			// Check for an area change.
			if ( $old_area !== $new_area ) {

				// Create the event for this widget ID if it hasn't been done already.
				if ( ! isset( self::$events[ $widget_id ] ) ) {

					// As this event is an area change only, find a more descriptive event type.
					if ( $old_area === 'wp_inactive_widgets' ) {
						$event_type = 'Widget Activated';
					} elseif ( $new_area === 'wp_inactive_widgets' ) {
						$event_type = 'Widget Deactivated';
					} else {
						$event_type = 'Widget Moved';
					}

					// Create a new event for this widget.
					$widget                     = Widget_Utility::load( $widget_id );
					self::$events[ $widget_id ] = Event::create( $event_type, $widget );
				}

				// Add the changed area to the properties.
				$old_area_name = Widget_Utility::get_area_name( $old_area );
				$new_area_name = Widget_Utility::get_area_name( $new_area );
				self::$events[ $widget_id ]->set_prop( 'area', null, $old_area_name, $new_area_name );
			}
		}

		// Save the events.
		foreach ( self::$events as $event ) {
			$event->save();
		}
	}
}
