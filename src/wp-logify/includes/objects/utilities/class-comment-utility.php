<?php
/**
 * Contains the Comment_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Comment;

/**
 * Class WP_Logify\Comment_Utility
 *
 * Provides methods for working with comments.
 */
class Comment_Utility extends Object_Utility {

	// =============================================================================================
	// Implementations of base class methods.

	/**
	 * Check if a comment exists.
	 *
	 * @param int|string $comment_id The ID of the comment.
	 * @return bool True if the comment exists, false otherwise.
	 */
	public static function exists( int|string $comment_id ): bool {
		global $wpdb;
		$sql   = $wpdb->prepare( 'SELECT COUNT(comment_ID) FROM %i WHERE comment_ID = %d', $wpdb->comments, $comment_id );
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}

	/**
	 * Get a comment by ID.
	 *
	 * @param int|string $comment_id The ID of the comment.
	 * @return ?WP_Comment The comment object or null if not found.
	 */
	public static function load( int|string $comment_id ): ?WP_Comment {
		// Load the comment.
		$comment = get_comment( $comment_id );

		// Return the comment or null if it doesn't exist.
		return $comment ?? null;
	}

	/**
	 * Generate a title for a comment from the comment's content.
	 *
	 * @param int|string $comment_id The ID of the comment.
	 * @return string The comment title generated from the comment content.
	 */
	public static function get_name( int|string $comment_id ): ?string {
		// Load the comment.
		$comment = self::load( $comment_id );

		// If the comment doesn't exist, return null.
		if ( ! $comment ) {
			return null;
		}

		// Get the snippet from the comment content.
		return Strings::get_snippet( $comment->comment_content );
	}

	/**
	 * Get the core properties of a comment.
	 *
	 * @param int|string $comment_id The ID of the comment.
	 * @return ?Property[] The core properties of the comment, or null if not found.
	 */
	public static function get_core_properties( int|string $comment_id ): ?array {
		global $wpdb;

		// Load the comment.
		$comment = self::load( $comment_id );

		// Handle the case where the comment no longer exists.
		if ( ! $comment ) {
			return null;
		}

		// Build the array of properties.
		$props = array();

		// Link.
		Property_Array::set( $props, 'link', null, Object_Reference::new_from_wp_object( $comment ) );

		// Comment author.
		if ( $comment->user_id && User_Utility::exists( $comment->user_id ) ) {
			$posted_by = new Object_Reference( 'user', $comment->user_id );
			Property_Array::set( $props, 'comment_author', $wpdb->comments, $posted_by );
		} elseif ( $comment->comment_author ) {
			Property_Array::set( $props, 'comment_author', $wpdb->comments, $comment->comment_author );
		}

		// Date.
		$date = Datetimes::create_datetime( $comment->comment_date );
		Property_Array::set( $props, 'comment_date', $wpdb->comments, $date );

		// Status.
		$status = self::approved_to_status( $comment->comment_approved );
		Property_Array::set( $props, 'status', $wpdb->comments, $status );

		// Post.
		if ( $comment->comment_post_ID ) {
			$post = new Object_Reference( 'post', $comment->comment_post_ID );
			Property_Array::set( $props, 'post', $wpdb->comments, $post );
		}

		// Parent.
		if ( $comment->comment_parent ) {
			$parent = new Object_Reference( 'comment', $comment->comment_parent );
			Property_Array::set( $props, 'comment_parent', $wpdb->comments, $parent );
		}

		return $props;
	}

	/**
	 * Get a comment tag.
	 *
	 * @param int|string $comment_id  The ID of the comment.
	 * @param ?string    $old_snippet The comment snippet at the time of the event.
	 * @return string The link or span HTML tag.
	 */
	public static function get_tag( int|string $comment_id, ?string $old_snippet = null ): string {
		// Load the comment.
		$comment = self::load( $comment_id );

		// If the comment exists, get a link if possible.
		if ( $comment ) {
			// Generate the comment snippet.
			$title = self::get_name( $comment_id );

			// Get the comment URL. This will vary by comment status.
			if ( $comment->comment_approved === 'post-trashed' ) {
				// If the comment is attached to a trashed post, there's no way to link to it.
				return "<span class='wp-logify-object'>$title</span>";
			} elseif ( $comment->comment_approved === 'trash' ) {
				// If the comment is trashed, link to the comment in the trash.
				$url = admin_url( "edit-comments.php?comment_status=trash#comment-$comment_id" );
			} else {
				// Link to the comment edit form.
				$url = admin_url( "comment.php?action=editcomment&c=$comment_id" );
			}

			// Return the link.
			return "<a href='$url' class='wp-logify-object'>$title</a>";
		}

		// Make a backup title.
		if ( ! $old_snippet ) {
			$old_snippet = "Comment $comment_id";
		}

		// The comment no longer exists. Construct the 'deleted' span element.
		return "<span class='wp-logify-deleted-object'>$old_snippet (deleted)</span>";
	}

	// =============================================================================================
	// Additional methods.

	/**
	 * Get all the properties of a comment.
	 *
	 * @param int|WP_Comment $comment The ID of the comment or the comment object.
	 * @return ?Property[] The properties of the comment, or null if not found.
	 */
	public static function get_properties( int|WP_Comment $comment ): ?array {
		global $wpdb;

		// Load the comment if necessary.
		if ( is_int( $comment ) ) {
			$comment = self::load( $comment );

			// Handle comment not found.
			if ( ! $comment ) {
				return null;
			}
		}

		// Get the comment data as an array.
		$comment_data = $comment->to_array();

		// Build the array of properties.
		$props = array();
		foreach ( $comment_data as $key => $value ) {
			// Skip a couple we don't need.
			if ( in_array( $key, array( 'populated_children', 'post_fields' ) ) ) {
				continue;
			}

			// Handle children separately.
			if ( $key === 'children' ) {
				// Get the children and add them to the properties.
				$children = self::get_children( $comment->comment_ID );
				Property_Array::set( $props, 'children', $wpdb->comments, $children );
				continue;
			}

			// Add the property.
			Property_Array::set( $props, $key, $wpdb->comments, $value );
		}

		return $props;
	}

	/**
	 * Convert the comment_approved value to a status string.
	 *
	 * @param string $comment_approved The comment_approved value from the database.
	 * @return string The status string.
	 */
	public static function approved_to_status( string $comment_approved ) {
		return match ( $comment_approved ) {
			'0'     => 'unapproved',
			'1'     => 'approved',
			default => $comment_approved
		};
	}

	/**
	 * Get the children comments of a comment, as an array of Object_Reference objects.
	 *
	 * @param int $comment_id The ID of the comment.
	 * @return Object_Reference[] The children comments as Object_Reference objects.
	 */
	public static function get_children( int $comment_id ): array {
		global $wpdb;

		// Get the children comment IDs.
		$sql     = $wpdb->prepare( 'SELECT comment_ID FROM %i WHERE comment_parent = %d', $wpdb->comments, $comment_id );
		$results = $wpdb->get_col( $sql );

		// Convert to object references.
		$results = array_map(
			fn( $comment_id ) => new Object_Reference( 'comment', (int) $comment_id ),
			$results
		);

		return $results;
	}
}
