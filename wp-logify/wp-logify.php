<?php
/**
 * Plugin Name: WP Logify
 * Plugin URI: https://wplogify.com
 * Description: WP Logify features advanced tracking to ensure awareness of all changes made to your WordPress website, including who made them and when.
 * Version: 1.15
 * Author: Made Neat
 * Author URI: https://madeneat.com.au
 * License: GPL2
 *
 * WP Logify is a plugin that logs all changes made to your WordPress website, including who made them
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
 * @package WP_Logify
 */

namespace WP_Logify;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Some useful globals.
define( 'WP_LOGIFY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_LOGIFY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Include all include files.
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/database/class-database.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/database/models/class-event.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/database/models/class-eventmeta.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/database/models/class-property.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/database/repositories/class-repository.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/database/repositories/class-event-repository.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/database/repositories/class-eventmeta-repository.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/database/repositories/class-property-repository.php';

require_once WP_LOGIFY_PLUGIN_DIR . 'includes/objects/class-object-reference.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/objects/class-posts.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/objects/class-users.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/objects/class-terms.php';
// require_once WP_LOGIFY_PLUGIN_DIR . 'includes/objects/class-plugins.php';
// require_once WP_LOGIFY_PLUGIN_DIR . 'includes/objects/class-settings.php';

require_once WP_LOGIFY_PLUGIN_DIR . 'includes/ui/class-admin.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/ui/class-log-page.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/ui/class-settings.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/ui/class-widget.php';

require_once WP_LOGIFY_PLUGIN_DIR . 'includes/utility/class-datetimes.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/utility/class-serialization.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/utility/class-types.php';

require_once WP_LOGIFY_PLUGIN_DIR . 'includes/class-cron.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/class-logger.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/class-plugin.php';

require_once WP_LOGIFY_PLUGIN_DIR . 'includes/debug.php';
require_once WP_LOGIFY_PLUGIN_DIR . 'includes/test.php';

// Register plugin hooks.
add_action( 'plugins_loaded', array( 'WP_Logify\Plugin', 'init' ) );
register_activation_hook( __FILE__, array( 'WP_Logify\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_Logify\Plugin', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'WP_Logify\Plugin', 'uninstall' ) );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'WP_Logify\Plugin', 'add_action_links' ) );
