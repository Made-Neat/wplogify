<?php
/**
 * Contains the Object_Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Object_Tracker
 *
 * Base class for object-specific tracker classes.
 */
abstract class Object_Tracker {

	/**
	 * Array to remember properties between different events.
	 *
	 * @var array
	 */
	protected static $properties = array();

	/**
	 * Array to remember metadata between different events.
	 *
	 * @var array
	 */
	protected static $eventmetas = array();

	/**
	 * Set up hooks for the events we want to log.
	 */
	abstract public static function init();
}
