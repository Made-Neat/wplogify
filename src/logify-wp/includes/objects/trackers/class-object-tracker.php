<?php
/**
 * Contains the Object_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class Logify_WP\Object_Tracker
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
