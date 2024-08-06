<?php
/**
 * Contains the Comments class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;
use WP_Comment;

/**
 * Class WP_Logify\Comments
 *
 * Provides tracking of events related to comments.
 */
class Comments {

	/**
	 * Array to remember properties between different events.
	 *
	 * @var array
	 */
	private static $properties = array();

	/**
	 * Array to remember metadata between different events.
	 *
	 * @var array
	 */
	private static $eventmetas = array();

	// =============================================================================================
	// Hooks.

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
	}

	/**
	 * Get a comment by ID.
	 *
	 * @param int $comment_id The ID of the comment.
	 * @return WP_Comment The comment object.
	 * @throws Exception If the comment could not be loaded.
	 */
	public static function load( int $comment_id ): WP_Comment {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			throw new Exception( "Comment $comment_id could not be loaded." );
		}

		return $comment;
	}

	/**
	 * Generate a title for a comment.
	 *
	 * @param int|string|WP_Comment $comment The comment or comment ID.
	 * @return string The comment title.
	 */
	public static function get_title( int|string|WP_Comment $comment ): string {
		// If the comment is an ID, load the comment.
		if ( is_int( $comment ) ) {
			$comment_id      = $comment;
			$comment         = self::load( $comment_id );
			$comment_content = $comment->comment_content;
		} elseif ( $comment instanceof WP_Comment ) {
			$comment_id      = $comment->comment_ID;
			$comment_content = $comment->comment_content;
		} else {
			// $comment is a string.
			$comment_content = $comment;
		}

		// Specify a maximum title length.
		$max_title_length = 50;

		// Check the length.
		if ( strlen( $comment_content ) <= 50 ) {
			return $comment_content;
		} else {
			// If the comment is too long, truncate it.
			return substr( $comment_content, 0, $max_title_length - 3 ) . '...';
		}
	}
}
