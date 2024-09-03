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
	 * @var Event
	 */
	private static $events = array();

	/**
	 * Widget areas.
	 *
	 * @var Event
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
		// We're only interested in widget options.
		if ( ! Types::starts_with( $option, 'widget_' ) ) {
			return;
		}

		// Get the widget type from the option name.
		$widget_type = substr( $option, strlen( 'widget_' ) );

		// Look for widget updates or deletions.
		foreach ( $old_option_value as $widget_number => $widget ) {
			// Ignore non-integer keys like '_multiwidget'.
			if ( ! is_int( $widget_number ) && ! Types::looks_like_int( $widget_number ) ) {
				continue;
			}

			// Get the widget ID.
			$widget_id = $widget_type . '-' . $widget_number;

			// Load the widget details from the option values, so we get the extra properties.
			$old_widget = Widget_Utility::get_from_option( $widget_id, $old_option_value );
			debug( '$old_widget', $old_widget );

			$new_widget = Widget_Utility::get_from_option( $widget_id, $new_option_value );

			if ( ! key_exists( $widget_number, $new_option_value ) ) {
				// The widget is being deleted.

				debug( 'widget deleted', $widget_type, $widget_number, $widget_id, $old_widget );

				// Create the event.
				self::$events[ $widget_id ] = Event::create( 'Widget Deleted', $old_widget );
				debug( self::$events[ $widget_id ] );
			} elseif ( $old_widget !== $new_widget ) {
				// The widget has been updated.

				// debug( 'widget updated', $widget_type, $widget_number, $widget_id, $old_widget );

				// Create the event.
				self::$events[ $widget_id ] = Event::create( 'Widget Updated', $old_widget );

				// Record the changed widget settings in the event properties.
				$keys = array_unique( array_merge( array_keys( $old_widget ), array_keys( $new_widget ) ) );
				foreach ( $keys as $key ) {

					// Get the old and new values.
					$old_widget_value = $old_widget[ $key ] ?? null;
					$new_widget_value = $new_widget[ $key ] ?? null;

					// Strip tags from content.
					if ( $key === 'content' ) {
						$old_widget_value = wp_strip_all_tags( $old_widget_value, true );
						$new_widget_value = wp_strip_all_tags( $new_widget_value, true );
					}

					// Compare.
					if ( $old_widget_value !== $new_widget_value ) {
						// Set the property.
						self::$events[ $widget_id ]->set_prop( $key, null, $old_widget_value, $new_widget_value );
					}
				}

				// Look for changes to block name.

				// debug( self::$events[ $widget_id ] );
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
		// For changes to sidebars_widgets, record widget areas.
		if ( $option === 'sidebars_widgets' ) {
			self::record_widget_areas( $old_option_value, $new_option_value );
			return;
		}

		// We're only interested in widget options.
		if ( ! Types::starts_with( $option, 'widget_' ) ) {
			return;
		}

		debug( 'on_updated_option', $option, $old_option_value, $new_option_value );

		// Get the widget type from the option name.
		$widget_type = substr( $option, strlen( 'widget_' ) );

		// Look for widget additions.
		foreach ( $new_option_value as $widget_number => $widget ) {
			// Ignore non-integer keys like '_multiwidget'.
			if ( ! is_int( $widget_number ) && ! Types::looks_like_int( $widget_number ) ) {
				continue;
			}

			// Check if this is new.
			if ( ! key_exists( $widget_number, $old_option_value ) ) {
				// Get the widget ID.
				$widget_id = $widget_type . '-' . $widget_number;

				// Load the widget details from the option values, so we get the extra properties.
				$new_widget = Widget_Utility::get_from_option( $widget_id, $new_option_value );

				// Log the event.
				debug( 'Widget Created', $widget_type, $widget_number, $widget_id, $new_widget );
				self::$events[ $widget_id ] = Event::create( 'Widget Created', $new_widget );
			}
		}
	}

	/**
	 * Record the widget areas.
	 */
	public static function record_widget_areas( $old_option_value, $new_option_value ) {
		// debug( 'record_widget_areas' );

		// Get the old areas.
		foreach ( $old_option_value as $sidebar_id => $widgets ) {
			// Ignore non-array values.
			if ( ! is_array( $widgets ) ) {
				continue;
			}

			// Loop through the widget positions.
			foreach ( $widgets as $widget_id ) {
				self::$areas[ $widget_id ]['old'] = $sidebar_id;
			}
		}

		// Get the old areas.
		foreach ( $new_option_value as $sidebar_id => $widgets ) {
			// Ignore non-array values.
			if ( ! is_array( $widgets ) ) {
				continue;
			}

			// Loop through the widget positions.
			foreach ( $widgets as $widget_id ) {
				self::$areas[ $widget_id ]['new'] = $sidebar_id;
			}
		}
	}

	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown() {
		// debug( 'on_shutdown' );

		// debug( self::$areas );

		// Get all the widget_ids.
		$widget_ids = array_unique( array_merge( array_keys( self::$events ), array_keys( self::$areas ) ) );
		debug( $widget_ids );

		// Loop through the widgets and log any events.
		foreach ( $widget_ids as $widget_id ) {
			// debug( $widget_id, $area );

			// Compare the old and new areas.
			$old_area = self::$areas[ $widget_id ]['old'] ?? null;
			$new_area = self::$areas[ $widget_id ]['new'] ?? null;

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

				// debug( self::$events[ $widget_id ]->event_type, $widget_id );

				// Add the changed area to the properties.
				$old_area_name = Widget_Utility::get_area_name( $old_area );
				$new_area_name = Widget_Utility::get_area_name( $new_area );
				self::$events[ $widget_id ]->set_prop( 'area', null, $old_area_name, $new_area_name );

				// Save the event.
				self::$events[ $widget_id ]->save();

			} elseif ( isset( self::$events[ $widget_id ] ) ) {
				// debug( self::$events[ $widget_id ]->event_type, $widget_id );

				// Set the area property if not done already.
				if ( $old_area && empty( self::$events[ $widget_id ]->get_prop_val['area'] ) ) {
					$old_area_name = Widget_Utility::get_area_name( $old_area );
					self::$events[ $widget_id ]->set_prop( 'area', null, $old_area_name );
				}

				// Save the event.
				self::$events[ $widget_id ]->save();
			}
		}
	}
}
