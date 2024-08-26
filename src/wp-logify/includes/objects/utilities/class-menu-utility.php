<?php
/**
 * Contains the Menu_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

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
		$props   = array();
		$details = self::get_menu_item_details( $post_id );
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
		// Load the navigation menu item.
		$post = Post_Utility::load( $post_id );

		if ( $post ) {
			// Get the linked object details.
			$info = self::get_menu_item_details( $post_id );

			switch ( $info['_menu_item_type'] ) {
				case 'post_type':
					$post_id    = (int) $info['_menu_item_object_id'];
					$post_title = get_the_title( $post_id );
					return Post_Utility::get_tag( $post_id, $post_title );

				case 'taxonomy':
					$term_id   = (int) $info['_menu_item_object_id'];
					$term_name = get_term( $term_id )->name;
					return Term_Utility::get_tag( $term_id, $term_name );

				case 'custom':
					return self::get_custom_link( $post );
			}
		}

		// Make a backup title.
		if ( ! $old_name ) {
			$old_name = "Navigation Menu Item $post_id";
		}

		return "<span class='wp-logify-deleted-object'>$old_name (deleted)</span>";
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
	 * For custom URL menu items, return an HTML link.
	 *
	 * @param WP_Post $post The navigation menu item object (which is a post).
	 * @return string The link HTML.
	 */
	public static function get_custom_link( WP_Post $post ) {
		$info      = self::get_menu_item_details( $post->ID );
		$url       = $info['_menu_item_url'];
		$link_text = $post->post_title;
		return "<a href='$url' class='wp-logify-object' target='_blank'>$link_text</a>";
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

		// Return the linked object.
		switch ( $info['_menu_item_type'] ) {
			case 'post_type':
				return new Object_Reference( 'post', $info['_menu_item_object_id'] );

			case 'taxonomy':
				return new Object_Reference( 'term', $info['_menu_item_object_id'] );

			case 'custom':
				return self::get_custom_link( $post );
		}

		return null;
	}
}
