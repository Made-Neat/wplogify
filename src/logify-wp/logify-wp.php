<?php
/**
 * Plugin Name: Logify WP
 * Plugin URI: https://logifywp.com
 * Description: Logify WP features advanced tracking to ensure awareness of all changes made to your WordPress website, including who made them and when.
 * Version: 1.48
 * Author: Made Neat
 * Author URI: https://madeneat.com.au
 * License: GPL2
 *
 * Logify WP is a plugin that logs all changes made to your WordPress website, including who made them
 * and when. It features advanced tracking to ensure awareness of all changes made to your WordPress
 * website.
 *
 * This plugin was created by Made Neat, a web development agency based in Australia. We specialize in
 * creating custom WordPress websites and plugins for businesses of all sizes. If you need help with
 * your WordPress website, please get in touch with us at https://madeneat.com.au.
 *
 * This plugin is released under the GPL2 license. You are free to use, modify, and distribute this
 * plugin as you see fit. We hope you find it useful!
 *
 * @package Logify_WP
 */

namespace Logify_WP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Some useful globals.
define( 'LOGIFY_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LOGIFY_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// =================================================================================================
// Include all include files.

// Database classes.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/database/class-database.php';

require_once LOGIFY_WP_PLUGIN_DIR . 'includes/database/models/class-event.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/database/models/class-eventmeta.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/database/models/class-property-array.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/database/models/class-property.php';

require_once LOGIFY_WP_PLUGIN_DIR . 'includes/database/repositories/class-repository.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/database/repositories/class-event-repository.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/database/repositories/class-eventmeta-repository.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/database/repositories/class-property-repository.php';

// -------------------------------------------------------------------------------------------------
// Application object-related classes.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/class-object-reference.php';

// Classes for tracking events.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-comment-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-core-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-media-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-option-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-plugin-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-post-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-taxonomy-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-term-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-theme-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-user-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/trackers/class-widget-tracker.php';

// Classes for working with application objects.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-object-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-comment-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-core-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-media-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-menu-item-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-option-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-plugin-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-post-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-taxonomy-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-term-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-theme-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-user-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/objects/utilities/class-widget-utility.php';

// -------------------------------------------------------------------------------------------------
// Miscellanous other classes.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-access-control.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-admin.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-arrays.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-cron.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-dashboard-widget.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-datetimes.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-log-page.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-logger.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-main.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-plugin-settings.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-serialization.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-set.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-strings.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-types.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/misc/class-urls.php';

// Supporting functions.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/debug.php';
// require_once LOGIFY_WP_PLUGIN_DIR . 'includes/test.php';

// =================================================================================================

// Register plugin hooks.
add_action( 'plugins_loaded', array( 'Logify_WP\Main', 'init' ) );
register_activation_hook( __FILE__, array( 'Logify_WP\Main', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Logify_WP\Main', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Logify_WP\Main', 'uninstall' ) );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'Logify_WP\Main', 'add_action_links' ) );

// Permit fast commenting.
add_filter( 'comment_flood_filter', '__return_false' );
