<?php
/**
 * Contains the Theme_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use WP_Theme;

/**
 * Class WP_Logify\Theme_Tracker
 *
 * Provides tracking of events related to themes.
 */
class Theme_Tracker extends Tracker {

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Theme install.
		// Theme activate.
		// Theme deactivate.
		// Theme uninstall/delete.
	}
}
