<?php
/**
 * Contains the Menu_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Logify\Event;
use Exception;
use WP_Post;

/**
 * Class WP_Logify\Menu_Utility
 *
 * Provides tracking of events related to menus and menu items.
 */
class Menu_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if a nav menu item exists.
	 *
	 * @param int|string $post_id The post ID of the navigation menu item.
	 * @return bool True if the object exists, false otherwise.
	 */
	public static function exists( int|string $post_id ): bool {
		return Post_Utility::exists( $post_id );
	}

	/**
	 * Get a nav menu item by ID.
	 *
	 * If the object isn't found, null will be returned. An exception will not be thrown.
	 *
	 * @param int|string $post_id The post ID of the navigation menu item.
	 * @return mixed The post object or null if not found.
	 */
	public static function load( int|string $post_id ): mixed {
		return Post_Utility::load( $post_id );
	}

	/**
	 * Get the name of a nav menu item. This will be equal to the name of the linked object.
	 *
	 * @param int|string $post_id The post ID of the navigation menu item.
	 * @return string The name, or null if the object could not be found.
	 */
	public static function get_name( int|string $post_id ): ?string {
		return Post_Utility::get_name( $post_id );
	}

	/**
	 * Get the core properties of a nav menu item, for logging.
	 *
	 * @param int|string $post_id The post ID of the navigation menu item.
	 * @return Property[] The core properties of the object.
	 * @throws Exception If the object doesn't exist.
	 */
	public static function get_core_properties( int|string $post_id ): array {
		global $wpdb;

		// Get the menu item details.
		$details = self::get_menu_item_details( $post_id );

		// If we don't have details, return an empty array.
		if ( ! $details ) {
			return array();
		}

		// Add the menu item details to the properties.
		$props = array();
		foreach ( $details as $key => $value ) {
			Property::update_array( $props, $key, $wpdb->postmeta, $value );
		}
		return $props;
	}

	/**
	 * Return HTML referencing a navigation menu item.
	 *
	 * As opposed to other object types, links returned by this method link to the linked object,
	 * which can be a page, post, category, or custom URL.
	 *
	 * @param int|string $post_id  The post ID of the navigation menu item.
	 * @param ?string    $old_name The name of the object at the time of the event.
	 * @return string The link HTML.
	 */
	public static function get_tag( int|string $post_id, ?string $old_name ): string {
		// Get the linked object.
		$linked_object = self::get_linked_object( $post_id );

		// If we have a linked object, return its tag.
		if ( $linked_object ) {
			return Types::value_to_string( $linked_object );
		}

		// Make a backup title.
		if ( ! $old_name ) {
			$old_name = "Navigation Menu Item $post_id";
		}

		// Return a span.
		return Post_Utility::exists( $post_id )
			? "<span class='wp-logify-object'>$old_name</span>"
			: "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Get the menu item details.
	 *
	 * @param int $post_id The post ID of the navigation menu item.
	 * @return array The menu item details.
	 */
	public static function get_menu_item_details( int $post_id ): ?array {
		// Get the meta data for the post.
		$meta = get_post_meta( $post_id );

		// Make sure we have a menu item type.
		if ( ! isset( $meta['_menu_item_type'][0] ) ) {
			return null;
		}

		// Specify the fields we want according to the menu item type.
		$menu_item_type = $meta['_menu_item_type'][0];
		switch ( $menu_item_type ) {
			case 'post_type':
			case 'taxonomy':
				$fields = array(
					'_menu_item_type',
					'_menu_item_object_id',
					'_menu_item_object',
				);
				break;

			case 'custom':
				$fields = array(
					'_menu_item_type',
					'_menu_item_url',
				);
				break;

			default:
				return null;
		}

		// Extract the menu item details.
		$menu_item = array();
		foreach ( $meta as $key => $value ) {
			// Get the properties we want.
			if ( in_array( $key, $fields ) ) {
				// Just take the first value, these are all singular.
				$menu_item[ $key ] = $value[0];
			}
		}

		// Return the menu item details.
		return $menu_item;
	}

	/**
	 * Return a reference to the linked object.
	 *
	 * @param int $post_id The post ID of the navigation menu item.
	 * @return null|string|Object_Reference The link HTML.
	 */
	public static function get_linked_object( int $post_id ): null|string|Object_Reference {
		// Load the navigation menu item.
		$post = Post_Utility::load( $post_id );

		// If the post doesn't exist, return null.
		if ( ! $post ) {
			return null;
		}

		// Get the linked object details.
		$info = self::get_menu_item_details( $post_id );

		// Check we have a menu item type.
		if ( empty( $info['_menu_item_type'] ) ) {
			return null;
		}

		// Return the linked object.
		switch ( $info['_menu_item_type'] ) {
			case 'post_type':
				return new Object_Reference( 'post', $info['_menu_item_object_id'] );

			case 'taxonomy':
				return new Object_Reference( 'term', $info['_menu_item_object_id'] );

			case 'custom':
				$url       = $info['_menu_item_url'];
				$link_text = $post->post_title;
				return "<a href='$url' class='wp-logify-object' target='_blank'>$link_text</a>";
		}

		return null;
	}

	/**
	 * Get the tag for a menu item from an event.
	 *
	 * @param Event $event The event.
	 * @return ?string The tag, or null if not found.
	 */
	public static function get_tag_from_event( Event $event ): ?string {
		// Check we have a post and a post_id.
		if ( $event->object_type !== 'post' || $event->object_key === null ) {
			return null;
		}

		// Try to get the linked object from the post. This will return null if the post has been deleted.
		$menu_item_link = self::get_linked_object( $event->object_key );
		if ( $menu_item_link ) {
			// Convert the linked object to a string.
			return Types::value_to_string( $menu_item_link );
		}

		// Try to get the menu_item_link from the event properties.
		if ( ! empty( $event->eventmetas['menu_item_link']->meta_value ) ) {
			// Convert the linked object to a string.
			return Types::value_to_string( $event->eventmetas['menu_item_link']->meta_value );
		}

		return null;
	}
}
