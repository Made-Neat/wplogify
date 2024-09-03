<?php
/**
 * Contains the Widget_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;
use WP_Widget;

/**
 * Class WP_Logify\Widget_Utility
 *
 * Provides methods for working with widgets.
 */
class Widget_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if a widget exists.
	 *
	 * @param int|string $widget_id The ID of the widget.
	 * @return bool True if the widget exists, false otherwise.
	 */
	public static function exists( int|string $widget_id ): bool {
		$widget = self::load( $widget_id );
		return $widget !== null;
	}

	/**
	 * Get a widget by ID.
	 *
	 * @param int|string $widget_id The ID of the widget.
	 * @return ?array The widget array or null if not found.
	 */
	public static function load( int|string $widget_id ): ?array {
		// Get the widget parameters.
		$widget_info = wp_parse_widget_id( $widget_id );
		$id_base     = $widget_info['id_base'];

		// Look up the option value for this widget type.
		$option_key   = 'widget_' . $id_base;
		$option_value = get_option( $option_key );

		// Get the widget details from the option.
		return self::get_from_option( $widget_id, $option_value );
	}

	/**
	 * Get the widget display name.
	 *
	 * In order of preference:
	 *   1. The widget name from the block metadata.
	 *   2. The widget title.
	 *   3. A snippet generated from the widget content.
	 *   4. The widget ID made readable.
	 *
	 * @param int|string $widget_id The ID of the widget.
	 * @return ?string A suitable display name for the widget.
	 */
	public static function get_name( int|string $widget_id ): ?string {
		// Check if the widget has a name in the block metadata.
		$name = self::get_block_name( $widget_id );
		if ( $name ) {
			return $name;
		}

		// Load the widget.
		$widget = self::load( $widget_id );

		// Use the widget title if set.
		if ( ! empty( $widget['title'] ) ) {
			return $widget['title'];
		}

		// Use a snippet of the widget content if set.
		if ( ! empty( $widget['content'] ) ) {
			return Types::get_snippet( $widget['content'] );
		}

		// Fallback to the widget ID.
		return "Widget $widget_id";
	}

	/**
	 * Get the core properties of a widget.
	 *
	 * @param int|string $widget_id The ID of the widget.
	 * @return array The core properties of the widget.
	 * @throws Exception If the widget no longer exists.
	 */
	public static function get_core_properties( int|string $widget_id ): array {

		// Load the widget.
		$widget = self::load( $widget_id );

		// Handle the case where the widget no longer exists.
		if ( ! $widget ) {
			throw new Exception( "Widget $widget_id not found." );
		}

		// Build the array of properties.
		$props = array();

		// ID. This will show both the type and the instance number.
		Property::update_array( $props, 'widget_id', null, $widget_id );

		// Widget type.
		Property::update_array( $props, 'type', null, $widget['id_base'] );

		// Title (classic widgets).
		if ( ! empty( $widget['title'] ) ) {
			Property::update_array( $props, 'title', null, $widget['title'] );
		}

		// Name (block widgets).
		$block_name = self::get_block_name( $widget_id );
		if ( $block_name ) {
			Property::update_array( $props, 'name', null, $block_name );
		}

		// Content.
		if ( ! empty( $widget['content'] ) ) {
			Property::update_array( $props, 'content', null, wp_strip_all_tags( $widget['content'], true ) );
		}

		// Area.
		$area_id   = self::get_area( $widget_id );
		$area_name = self::get_area_name( $area_id );
		Property::update_array( $props, 'area', null, $area_name );

		// Any others. May as well show them all.
		// foreach ( $widget as $key => $value ) {
		// Skip any we already got.
		// if ( in_array( $key, array( 'widget_id', 'id_base', 'number', 'object_type', 'title', 'name', 'content', 'area' ) ) ) {
		// continue;
		// }

		// Set the property.
		// Property::update_array( $props, $key, null, $value );
		// }

		return $props;
	}

	/**
	 * Get a widget tag.
	 *
	 * @param int|string $widget_id The ID of the widget.
	 * @param ?string    $old_name  The widget name at the time of the event.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $widget_id, ?string $old_name = null ): string {
		// Load the widget.
		$widget = self::load( $widget_id );

		if ( $widget ) {
			// Get the widget display name.
			$name = self::get_name( $widget_id );
			return "<span class='wp-logify-object'>$name</span>";
		}

		// Make a backup name.
		if ( ! $old_name ) {
			$old_name = "Widget $widget_id";
		}

		// The widget no longer exists. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Get the widget area (e.g. sidebar, header, footer section) for a given widget ID.
	 *
	 * @param string $widget_id The ID of the widget.
	 * @return ?string The ID of the widget area (sidebar) the widget belongs to, or null if not found.
	 */
	public static function get_area( string $widget_id ): ?string {
		global $sidebars_widgets;

		// Iterate over all registered widget areas.
		foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
			// Check if the widget ID is in the current widget area.
			if ( in_array( $widget_id, $widgets, true ) ) {
				return $sidebar_id;
			}
		}

		// Return null if the widget is not found in any widget area.
		return null;
	}

	/**
	 * Get the name of a widget area (sidebar) by its ID.
	 *
	 * @param ?string $sidebar_id The ID of the widget area.
	 * @return mixed The name of the widget area or null if not found.
	 */
	public static function get_area_name( ?string $sidebar_id ): ?string {
		global $wp_registered_sidebars;
		return $wp_registered_sidebars[ $sidebar_id ]['name'] ?? null;
	}

	/**
	 * Get a widget by ID from the option value.
	 *
	 * @param string $widget_id    The ID of the widget.
	 * @param array  $option_value The value of the 'widget_{id_base}' option.
	 * @return ?array The widget array or null if not found.
	 */
	public static function get_from_option( string $widget_id, array $option_value ): ?array {
		// Load the widget details from the options table.
		$widget_info   = wp_parse_widget_id( $widget_id );
		$id_base       = $widget_info['id_base'];
		$widget_number = $widget_info['number'];

		// If the widget option doesn't exist, return null.
		if ( ! key_exists( $widget_number, $option_value ) ) {
			return null;
		}

		// Get the widget.
		$widget = $option_value[ $widget_number ];

		// Add some extra details.
		$widget_extra = array(
			'object_type' => 'widget',
			'widget_id'   => $widget_id,
			'id_base'     => $id_base,
			'number'      => $widget_number,
		);

		// Get the block name, or try to.
		if ( ! empty( $widget['content'] ) ) {
			$widget_extra['name'] = self::get_block_name_from_content( $widget['content'] );
		}

		return array_merge( $widget_extra, $widget );
	}

	/**
	 * Get the name from a block widget.
	 *
	 * Note: It's quite likely this will not be set.
	 *
	 * @param int|string $widget_id The ID of the widget.
	 * @return ?string The widget name or null if widget not found or name not set.
	 */
	public static function get_block_name( int|string $widget_id ): ?string {
		// Load the widget.
		$widget = self::load( $widget_id );

		if ( ! empty( $widget['content'] ) ) {
			return self::get_block_name_from_content( $widget['content'] );
		}

		return null;
	}

	/**
	 * Get the name from a widget content.
	 *
	 * @param string $widget_content The widget content.
	 * @return ?string The widget name or null if not set.
	 */
	public static function get_block_name_from_content( string $widget_content ): ?string {
		// Parse the block content.
		$blocks = parse_blocks( $widget_content );

		// Get the name from the metadate of the first block, if set.
		return $blocks[0]['attrs']['metadata']['name'] ?? null;
	}
}
