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
	 * Initializes an empty Event_Meta object.
	 */
	public function __construct() {
		// Empty constructor.
	}

	/**
	 * Creates a new Event_Meta object.
	 *
	 * @param ?int   $event_id The event ID if known.
	 * @param string $meta_key The meta key.
	 * @param mixed  $meta_value The meta value.
	 * @return Event_Meta The new object.
	 */
	public static function create( ?int $event_id, string $meta_key, mixed $meta_value ): self {
		$event_meta             = new self();
		$event_meta->event_id   = $event_id;
		$event_meta->meta_key   = $meta_key;
		$event_meta->meta_value = $meta_value;
		return $event_meta;
	}
}
