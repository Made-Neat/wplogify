<?php
/**
 * Contains the Eventmeta class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Represents metadata associated with an event.
 */
class Eventmeta {

	/**
	 * The ID of the eventmeta record.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * The ID of the event associated with this metadata.
	 *
	 * @var int
	 */
	public ?int $event_id;

	/**
	 * The meta key.
	 *
	 * @var string
	 */
	public string $meta_key;

	/**
	 * The meta value.
	 *
	 * @var mixed
	 */
	public mixed $meta_value;

	/**
	 * Eventmeta constructor.
	 *
	 * @param ?int   $event_id The ID of the event associated with this metadata.
	 * @param string $meta_key The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function __construct( ?int $event_id, string $meta_key, mixed $meta_value ) {
		$this->event_id   = $event_id;
		$this->meta_key   = $meta_key;
		$this->meta_value = $meta_value;
	}

	/**
	 * Add a new eventmeta to an array of eventmetas.
	 *
	 * @param array  $eventmetas The array of eventmetas to update.
	 * @param string $meta_key The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public static function add_to_array( array &$eventmetas, string $meta_key, mixed $meta_value ) {
		$eventmetas[ $meta_key ] = new Eventmeta( null, $meta_key, $meta_value );
	}
}
