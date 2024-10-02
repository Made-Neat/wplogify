<?php
/**
 * Plugin Name: My Single File Plugin
 * Plugin URI:  https://example.com/my-single-file-plugin
 * Description: A simple WordPress plugin that does something cool.
 * Version:     1.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPLv2 or later
 * Text Domain: my-single-file-plugin
 */

// Your plugin code goes here.

function my_single_file_plugin_function() {
	echo 'Hello from my single-file plugin!';
}
add_action( 'wp_footer', 'my_single_file_plugin_function' );
