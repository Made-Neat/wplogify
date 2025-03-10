<?php
/**
 * Plugin Name: Logify WP
 * Plugin URI: https://logifywp.com
 * Description: Logify WP features advanced tracking to ensure awareness of all changes made to your WordPress website, including who made them and when.
 * Version: 1.2.3
 * Author: Made Neat
 * Author URI: https://madeneat.com.au
 * Requires at least: 6.2
 * Text Domain: logify-wp
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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

if (!function_exists('get_plugin_data')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// Some useful globals.
define( 'LOGIFY_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LOGIFY_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// =================================================================================================
// Include all classes.

// Helper classes, mostly for working with data and types.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/helpers/class-arrays.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/helpers/class-datetimes.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/helpers/class-object-reference.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/helpers/class-serialization.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/helpers/class-set.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/helpers/class-strings.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/helpers/class-types.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/helpers/class-urls.php';

// Classes that encapsulate the core plugin data types.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/models/class-error.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/models/class-event.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/models/class-eventmeta.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/models/class-property.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/models/class-note.php';

// Classes for interacting with the database.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/repositories/class-repository.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/repositories/class-event-repository.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/repositories/class-eventmeta-repository.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/repositories/class-property-repository.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/repositories/class-note-repository.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/repositories/class-error-repository.php';

// Classes that provide plugin functionality.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-access-control.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-admin.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-cron.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-dashboard-widget.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-data-migration.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-database.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-debug.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-log-page.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-notes-page.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-php-error-log.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-logger.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-main.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/services/class-plugin-settings.php';

// Classes for tracking events.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-async-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-error-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-comment-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-core-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-media-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-option-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-plugin-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-post-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-term-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-theme-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-user-tracker.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/trackers/class-widget-tracker.php';

// Classes for working with application objects.
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-object-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-comment-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-core-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-media-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-menu-item-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-option-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-plugin-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-post-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-taxonomy-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-term-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-theme-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-user-utility.php';
require_once LOGIFY_WP_PLUGIN_DIR . 'includes/utilities/class-widget-utility.php';

//check website already has ActionScheduler library
if(!class_exists('ActionScheduler')){
    // Require action scheduler library
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'woocommerce' . DIRECTORY_SEPARATOR . 'action-scheduler' . DIRECTORY_SEPARATOR . 'action-scheduler.php';
}


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
