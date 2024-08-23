<?php
/**
 * Contains the Nav_Menu_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Nav_Menu_Tracker
 *
 * Provides tracking of events related to navigation menus.
 */
class Nav_Menu_Tracker {

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init() {
		// Create a nav menu.
		add_action( 'wp_create_nav_menu', array( __CLASS__, 'on_wp_create_nav_menu' ), 10, 2 );

		// Update nav menu.
		add_filter( 'wp_update_nav_menu', array( __CLASS__, 'on_wp_update_nav_menu' ), 10, 3 );

		// Delete nav menu.
		add_action( 'wp_delete_nav_menu', array( __CLASS__, 'on_wp_delete_nav_menu' ), 10, 2 );

		// Add nav menu item.
		add_action( 'wp_add_nav_menu_item', array( __CLASS__, 'on_wp_add_nav_menu_item' ), 10, 3 );

		// Add nav menu item.
		add_action( 'wp_update_nav_menu_item', array( __CLASS__, 'on_wp_update_nav_menu_item' ), 10, 3 );
	}

	/**
	 * Fires after a navigation menu is successfully created.
	 *
	 * @param int   $term_id   ID of the new menu.
	 * @param array $menu_data An array of menu data.
	 */
	public static function on_wp_create_nav_menu( int $term_id, array $menu_data ) {
		// Logger::log_event( 'Nav Menu Created', null );
	}
}
