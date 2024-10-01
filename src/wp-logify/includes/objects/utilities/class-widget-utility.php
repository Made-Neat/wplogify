<?php
/**
 * Contains the Widget_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

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
		// Load the widget.
		$widget = self::load( $widget_id );

		// Use the title if set.
		if ( ! empty( $widget['title'] ) ) {
			return $widget['title'];
		}

		// Get a name from the content, if set.
		if ( ! empty( $widget['content'] ) ) {

			// Use the block name if set.
			$block_details = self::get_block_details( $widget['content'] );
			if ( ! empty( $block_details['block_name'] ) ) {
				return $block_details['block_name'];
			}

			// If no block name, get a snippet.
			return Strings::get_snippet( $widget['content'] );
		}

		// Fallback to the widget ID.
		return "Widget $widget_id";
	}

	/**
	 * Get the core properties of a widget.
	 *
	 * @param int|string $widget_id The ID of the widget.
	 * @return ?Property[] The core properties of the widget, or null if not found.
	 */
	public static function get_core_properties( int|string $widget_id ): ?array {

		// Load the widget.
		$widget = self::load( $widget_id );

		// Handle the case where the widget no longer exists.
		if ( ! $widget ) {
			return null;
		}

		// Build the array of properties.
		$props = array();

		// ID. This will show both the type and the instance number.
		Property_Array::set( $props, 'widget_id', null, $widget_id );

		// Widget type.
		Property_Array::set( $props, 'type', null, $widget['id_base'] );

		// Block type (block widgets).
		if ( ! empty( $widget['block_type'] ) ) {
			Property_Array::set( $props, 'block_type', null, $widget['block_type'] );
		}

		// Block name (block widgets).
		if ( ! empty( $widget['block_name'] ) ) {
			Property_Array::set( $props, 'block_name', null, $widget['block_name'] );
		}

		// Title (classic widgets).
		if ( ! empty( $widget['title'] ) ) {
			Property_Array::set( $props, 'title', null, $widget['title'] );
		}

		// Content.
		if ( ! empty( $widget['content'] ) ) {
			$content = Strings::strip_tags( $widget['content'] );
			if ( $content ) {
				Property_Array::set( $props, 'content', null, $content );
			}
		}

		// Area.
		Property_Array::set( $props, 'area', null, $widget['area'] );

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
	 * @return ?string The ID of the widget area (a.k.a. sidebar) the widget is located in, or null if not found.
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
	 * @return ?string The name of the widget area or null if not found.
	 */
	public static function get_area_name( ?string $sidebar_id ): ?string {
		// Handle empty input.
		if ( ! $sidebar_id ) {
			return null;
		}

		// As 'wp_inactive_widgets' won't be in $wp_registered_sidebars, we need to handle it separately.
		if ( $sidebar_id === 'wp_inactive_widgets' ) {
			return 'Inactive Widgets';
		}

		global $wp_registered_sidebars;

		// If the sidebar ID is in the registered sidebars, get the name.
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

		// Get some extra details.
		$widget_extra = array(
			'object_type' => 'widget',
			'widget_id'   => $widget_id,
			'id_base'     => $id_base,
			'number'      => $widget_number,
		);

		// Get the block type and name, if set.
		if ( ! empty( $widget['content'] ) ) {
			$block_details = self::get_block_details( $widget['content'] );
			if ( $block_details ) {
				$widget_extra = array_merge( $widget_extra, $block_details );
			}
		}

		// Merge the extra details into the widget.
		$widget = array_merge( $widget_extra, $widget );

		// Get the area.
		$widget['sidebar_id'] = self::get_area( $widget_id );
		$widget['area']       = self::get_area_name( $widget['sidebar_id'] );

		return $widget;
	}

	/**
	 * Get the block details from a widget content.
	 *
	 * @param string $widget_content The widget content.
	 * @return ?array The block details or null if no block found.
	 */
	public static function get_block_details( string $widget_content ): ?array {
		// Parse the block content.
		$blocks = parse_blocks( $widget_content );

		if ( ! isset( $blocks[0] ) ) {
			return null;
		}

		$details = array();

		// Get the block type.
		$block_type = $blocks[0]['blockName'] ?? null;
		if ( $block_type ) {
			// Strip the 'core/' prefix to get the block's basic type.
			$block_type = str_replace( 'core/', '', $block_type );
		}
		$details['block_type'] = $block_type;

		// Get the block name from the metadate of the first block, if set.
		$details['block_name'] = $blocks[0]['attrs']['metadata']['name'] ?? null;

		return $details;
	}
}
