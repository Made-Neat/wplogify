<?php
/**
 * Contains the Comment_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Comment;

/**
 * Class WP_Logify\Comment_Tracker
 *
 * Provides tracking of events related to comments.
 */
class Comment_Tracker extends Object_Tracker {

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
	}
}
