<?php
/**
 * Contains the Tracker class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Tracker
 *
 * Base class for object-specific Tracker classes, which provide tracking of events related to objects.
 */
abstract class Tracker {

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
