<?php
/**
 * Contains the Event_Meta class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Represents metadata associated with an event.
 */
class Event_Meta {

	/**
	 * The ID of the event_meta record.
	 *
	 * @var int
	 */
	public int $event_meta_id;

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
	 * Event_Meta constructor.
	 *
	 * @param ?int   $event_id The event ID if known.
	 * @param string $meta_key The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function __construct( ?int $event_id, string $meta_key, mixed $meta_value ) {
		$this->event_id   = $event_id;
		$this->meta_key   = $meta_key;
		$this->meta_value = $meta_value;
	}
}
