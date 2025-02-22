<?php
/**
 * Contains the Post_Utility class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DateTime;
use RuntimeException;
use WP_Error;
use WP_Post;

/**
 * Class Logify_WP\Post_Utility
 *
 * Provides methods for working with posts.
 */
class Post_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if a post exists.
	 *
	 * @param int|string $post_id The ID of the post.
	 * @return bool True if the post exists, false otherwise.
	 */
	public static function exists( int|string $post_id ): bool {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(ID) FROM %i WHERE ID = %d', $wpdb->posts, $post_id )
		);
		return $count > 0;
	}

	/**
	 * Get a post by ID.
	 *
	 * @param int|string $post_id The ID of the post.
	 * @return ?WP_Post The post object or null if not found.
	 */
	public static function load( int|string $post_id ): ?WP_Post {
		// Get the post.
		$post = get_post( $post_id );

		// Return the post or null if it doesn't exist.
		return $post ?? null;
	}

	/**
	 * Get the name of a post.
	 *
	 * @param int|string $post_id The ID of the post.
	 * @return ?string The name of the post or null if the post doesn't exist.
	 */
	public static function get_name( int|string $post_id ): ?string {
		// Load the post.
		$post = self::load( $post_id );

		// Handle menu items separately.
		if ( $post && $post->post_type === 'nav_menu_item' ) {
			return Menu_Item_Utility::get_name( $post_id );
		}

		// Return the post title or null if the post doesn't exist.
		return $post->post_title ?? null;
	}

	/**
	 * Get the core properties of a post.
	 *
	 * @param int|string $post_id The ID of the post.
	 * @return ?Property[] The core properties of the post, or null if not found.
	 */
	public static function get_core_properties( int|string $post_id ): ?array {
		global $wpdb;

		// Load the post.
		$post_id = (int) $post_id;
		$post    = self::load( $post_id );

		// Handle the case where the post doesn't exist.
		if ( ! $post ) {
			return null;
		}

		// Build the array of properties.
		$props = array();

		// Link.
		Property::update_array( $props, 'link', null, Object_Reference::new_from_wp_object( $post ) );

		// ID.
		Property::update_array( $props, 'ID', $wpdb->posts, $post_id );

		// Post type.
		Property::update_array( $props, 'post_type', $wpdb->posts, $post->post_type );

		// Post author.
		$author = new Object_Reference( 'user', $post->post_author );
		Property::update_array( $props, 'post_author', $wpdb->posts, $author );

		// Post status.
		Property::update_array( $props, 'post_status', $wpdb->posts, $post->post_status );

		// Post date.
		Property::update_array( $props, 'post_date', $wpdb->posts, self::get_created_datetime( $post ) );

		// Post modified.
		Property::update_array( $props, 'post_modified', $wpdb->posts, self::get_last_modified_datetime( $post ) );

		// Post content, only if not empty.
		if ( $post->post_content ) {
			Property::update_array( $props, 'post_content', $wpdb->posts, Strings::get_snippet( $post->post_content, 100 ) );
		}

		// Post excerpt, only if not empty.
		if ( $post->post_excerpt ) {
			Property::update_array( $props, 'post_excerpt', $wpdb->posts, Strings::get_snippet( $post->post_excerpt, 100 ) );
		}

		// For nav menu items, get the menu item's core properties and merge them into the properties array.
		if ( $post->post_type === 'nav_menu_item' ) {
			$nav_menu_item_props = Menu_Item_Utility::get_core_properties( $post_id );
			if ( $nav_menu_item_props ) {
				$props = array_merge( $props, $nav_menu_item_props );
			}
		}

		// For images, include the alt text.
		if ( $post->post_type === 'attachment' && Media_Utility::get_media_type( $post_id ) === 'image' ) {
			$alt_text = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
			Property::update_array( $props, '_wp_attachment_image_alt', $wpdb->postmeta, $alt_text );
		}

		return $props;
	}

	/**
	 * Get all the post core properties.
	 *
	 * @return array The core properties of a post.
	 */
	public static function core_properties(): array {
		return array(
			'link'                     => 'Link',
			'ID'                       => 'ID',
			'post_type'                => 'Post Type',
			'post_author'              => 'Post Author',
			'post_status'              => 'Post Status',
			'post_date'                => 'Post Date',
			'post_modified'            => 'Post Modified',
			'post_content'             => 'Post Content',
			'post_excerpt'             => 'Post Excerpt',
			'_wp_attachment_image_alt' => 'Alternative Text',
		);
	}

	/**
	 * Check if a property is a core property of posts.
	 *
	 * @param string $prop_key The property key.
	 * @return bool True if the property is a core property, false otherwise.
	 */
	public static function is_core_property( string $prop_key ): bool {
		return key_exists( $prop_key, self::core_properties() );
	}

	/**
	 * If the post hasn't been deleted, get a link to its edit page; otherwise, get a span with
	 * the old title as the link text.
	 *
	 * @param int|string $post_id   The ID of the post.
	 * @param ?string    $old_title The fallback title of the post if it's been deleted.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $post_id, ?string $old_title = null ): string {
		// Load the post.
		$post = self::load( $post_id );

		// If the post exists, get a link.
		if ( $post ) {

			// Handle navigation menu items separately.
			if ( $post->post_type === 'nav_menu_item' ) {
				return Menu_Item_Utility::get_tag( $post_id, $old_title );
			}

			// Check if it's a revision.
			if ( wp_is_post_revision( $post_id ) ) {
				// Get a link to the revision comparison page.
				$url = admin_url( "revision.php?revision={$post_id}" );
				return "<a href='$url' class='logify-wp-object'>Compare revisions</a>";
			}

			// Make a backup title. Some post types don't have titles.
			$title = $post->post_title;
			if ( ! $title ) {
				$post_type = self::get_post_type_singular_name( $post->post_type );
				$title     = "$post_type $post_id";
			}

			// Check if we can provide a link to the post edit page for this post type.
			$show_ui = in_array( $post->post_type, get_post_types( array( 'show_ui' => true ) ), true );

			if ( $show_ui ) {
				// If the post is trashed, we can't reach its edit page, so instead we'll link to the
				// list of trashed posts.
				if ( $post->post_status === 'trash' ) {
					$url = admin_url( 'edit.php?post_status=trash&post_type=post' );
				} else {
					$url = admin_url( "post.php?post=$post_id&action=edit" );
				}

				// Get the link text.
				return "<a href='$url' class='logify-wp-object'>$title</a>";
			} else {
				// If the post type doesn't have a UI, we can't link to the edit page.
				return "<span class='logify-wp-object'>$title</span>";
			}
		}

		// Make a backup title.
		if ( ! $old_title ) {
			$old_title = "Post $post_id";
		}

		// The post no longer exists. Construct the 'deleted' span element.
		return "<span class='logify-wp-deleted-object'>$old_title (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Get the singular name of a custom post type.
	 *
	 * @param string $post_type The post type.
	 * @return string The singular name of the post type, or a reasonable fallback.
	 */
	public static function get_post_type_singular_name( string $post_type ): string {
		// Get the post type object.
		$post_type_object = get_post_type_object( $post_type );

		// Return the singular name, or a reasonable fallback.
		return $post_type_object->labels->singular_name ?? Strings::key_to_label( $post_type, true );
	}

	/**
	 * Get the datetime a post was created.
	 *
	 * This function ignores the post_date and post_date_gmt fields in the parent post record, which
	 * seem to show the last time the post was updated, not the time it was created.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return DateTime The datetime the post was created.
	 */
	public static function get_created_datetime( WP_Post|int $post ): DateTime {
		global $wpdb;

		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = self::load( $post );
		}

		// Get the created datetime.
		$created_datetime = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(post_date) FROM %i WHERE (ID = %d OR post_parent = %d) AND post_date != '0000-00-00 00:00:00'",
				$wpdb->posts,
				$post->ID,
				$post->ID
			)
		);
		return DateTimes::create_datetime( $created_datetime );
	}

	/**
	 * Get the datetime a post was last modified.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return DateTime The datetime the post was last modified.
	 */
	public static function get_last_modified_datetime( WP_Post|int $post ): DateTime {
		global $wpdb;

		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = self::load( $post );
		}

		// Get the last modified datetime.
		$last_modified_datetime = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_modified) FROM %i WHERE (ID = %d OR post_parent = %d) AND post_modified != '0000-00-00 00:00:00'",
				$wpdb->posts,
				$post->ID,
				$post->ID
			)
		);
		return DateTimes::create_datetime( $last_modified_datetime );
	}

	/**
	 * Get the properties of a post.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return ?Property[] The properties of the post.
	 */
	public static function get_properties( WP_Post|int $post ): ?array {
		global $wpdb;

		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = self::load( $post );

			// Handle post not found.
			if ( ! $post ) {
				return null;
			}
		}

		$props = array();

		// Add the base properties.
		foreach ( $post as $key => $value ) {
			// Skip the dates in the posts table, they're incorrect.
			if ( in_array( $key, array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ), true ) ) {
				continue;
			}

			// Process database values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			Property::update_array( $props, $key, $wpdb->posts, $value );
		}

		// Add the meta properties.
		$postmeta = get_post_meta( $post->ID );
		foreach ( $postmeta as $key => $value ) {
			// Check for single.
			self::reduce_to_single( $value );

			// Process database values into correct types.
			$value = Types::process_database_value( $key, $value );

			// Construct the new Property object and add it to the properties array.
			Property::update_array( $props, $key, $wpdb->postmeta, $value );
		}

		return $props;
	}

	/**
	 * Get all terms attached to the specified post as object references.
	 *
	 * @param WP_Post|int $post The post object or ID.
	 * @return array The attached terms as an array of arrays of object references.
	 * @throws RuntimeException If an error occurs getting the terms.
	 */
	public static function get_attached_terms( WP_Post|int $post ): array {
		// Load the post if necessary.
		if ( is_int( $post ) ) {
			$post = self::load( $post );
		}

		// Initialize the result.
		$term_refs = array();

		// Get all the relevant taxonomy names.
		$taxonomies = get_object_taxonomies( $post->post_type );

		foreach ( $taxonomies as $taxonomy ) {
			// Get the terms in this taxonomy that are attached to the post.
			$terms = get_the_terms( $post, $taxonomy );

			// Check for error.
			if ( $terms instanceof WP_Error ) {
				throw new RuntimeException( esc_html( "Error getting terms attached to post $post->ID." ) );
			}

			// If we got some terms, convert them to object references.
			if ( $terms ) {
				$term_refs[ $taxonomy ] = array();
				foreach ( $terms as $term ) {
					$term_refs[ $taxonomy ][] = new Object_Reference( 'term', $term->term_id, $term->name );
				}
			}
		}

		return $term_refs;
	}

	/**
	 * Get the verb for a post status transition.
	 *
	 * @param string $old_status The old post status.
	 * @param string $new_status The new post status.
	 * @return string The verb for the status transition.
	 */
	public static function get_status_transition_verb( string $old_status, string $new_status ) {
		// If transitioning out of trash, use a special verb.
		if ( $old_status === 'trash' ) {
			return 'Restored';
		}

		// Generate the event type verb from the new status.
		return match ( $new_status ) {
			'publish'           => 'Published',
			'draft'             => 'Drafted',
			'pending'           => 'Pending',
			'private'           => 'Privatized',
			'trash'             => 'Trashed',
			'auto-draft'        => 'Auto-drafted',
			'inherit'           => 'Inherited',
			'future'            => 'Scheduled',
			'request-pending'   => 'Request Pending',
			'request-confirmed' => 'Request Confirmed',
			'request-failed'    => 'Request Failed',
			'request-completed' => 'Request Completed',
			default             => 'Status Changed',
		};
	}

	/**
	 * Get the changes in a post by comparing the before and after versions.
	 *
	 * @param WP_Post $post_before The post before the update.
	 * @param WP_Post $post_after  The post after the update.
	 * @return Property[] The changes in the post.
	 */
	public static function get_changes( WP_Post $post_before, WP_Post $post_after ): array {
		global $wpdb;

		$props = array();

		// Add the base properties.
		foreach ( $post_before as $key => $value ) {
			// Skip the dates in the posts table, they're incorrect.
			if ( in_array( $key, array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ), true ) ) {
				continue;
			}

			// Get the before and after values.
			$val     = Types::process_database_value( $key, $value );
			$new_val = Types::process_database_value( $key, $post_after->$key );

			// If the value was changed, add the property.
			if ( ! Types::are_equal( $val, $new_val ) ) {
				Property::update_array( $props, $key, $wpdb->posts, $val, $new_val );
			}
		}

		// Add the meta properties.
		$postmeta_before = get_post_meta( $post_before->ID );
		$postmeta_after  = get_post_meta( $post_after->ID );

		// Collect all the keys.
		$keys = array_unique( array_merge( array_keys( $postmeta_before ), array_keys( $postmeta_after ) ) );

		// Go through the meta keys looking for changes.
		foreach ( $keys as $key ) {
			// Get the before and after values.
			$val     = self::extract_meta( $postmeta_before, $key );
			$new_val = self::extract_meta( $postmeta_after, $key );

			// If the value was changed, add the property.
			if ( ! Types::are_equal( $val, $new_val ) ) {
				Property::update_array( $props, $key, $wpdb->postmeta, $val, $new_val );
			}
		}

		return $props;
	}
}
