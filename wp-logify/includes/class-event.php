<?php
/**
 * Contains the Event class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;

/**
 * This class represents a logged event.
 */
class Event {

	/**
	 * The ID of the event.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * The date and time of the event in the site time zone, stored as a string.
	 *
	 * @var string
	 */
	public DateTime $date_time;

	/**
	 * The ID of the user who did the action.
	 *
	 * @var int
	 */
	public int $user_id;

	/**
	 * The role of the user.
	 *
	 * @var string
	 */
	public string $user_role;

	/**
	 * The IP address of the user.
	 *
	 * @var string
	 */
	public string $user_ip;

	/**
	 * The location of the user.
	 *
	 * @var ?string
	 */
	public ?string $user_location;

	/**
	 * The user agent string.
	 *
	 * @var ?string
	 */
	public ?string $user_agent;

	/**
	 * The type of the event, e.g. 'Post Created'.
	 *
	 * @var string
	 */
	public string $event_type;

	/**
	 * The type of object associated with the event, e.g. 'post', 'user', etc.
	 *
	 * @var ?string
	 */
	public ?string $object_type;

	/**
	 * The ID of the object associated with the event. This will be an integer (stored as a string)
	 * in the case of a post, user, etc., but could be a string in the case of a theme or plugin.
	 *
	 * @var ?string
	 */
	public ?string $object_id;

	/**
	 * The name of the object associated with the event.
	 *
	 * @var ?string
	 */
	public ?string $object_name;

	/**
	 * The details of the event. This is an associative array of additional information about the
	 * event, stored in the database as JSON.
	 *
	 * @var ?array
	 */
	public ?array $details;

	/**
	 * The changes associated with the event. This is an associative array with information about
	 * changes to the object, stored in the database as JSON.
	 *
	 * @var ?array
	 */
	public ?array $changes;

	/**
	 * Event constructor.
	 *
	 * @param array $data Optional. An associative array of event data, e.g. from the database.
	 */
	public function __construct( array $data = array() ) {
		if ( ! empty( $data ) ) {
			$this->id            = (int) $data['ID'];
			$this->date_time     = DateTimes::create_datetime( $data['date_time'] );
			$this->user_id       = (int) $data['user_id'];
			$this->user_role     = $data['user_role'];
			$this->user_ip       = $data['user_ip'];
			$this->user_location = $data['user_location'] ?? null;
			$this->user_agent    = $data['user_agent'] ?? null;
			$this->event_type    = $data['event_type'];
			$this->object_type   = $data['object_type'] ?? null;
			$this->object_id     = $data['object_id'] ?? null;
			$this->object_name   = $data['object_name'] ?? null;
			$this->details       = $data['details'] === null ? null : json_decode( $data['details'], true );
			$this->changes       = $data['changes'] === null ? null : json_decode( $data['changes'], true );
		}
	}

	/**
	 * Converts the event object to an associative array, e.g. for saving to the database.
	 *
	 * @return array The event data as an associative array.
	 */
	public function to_array(): array {
		// NB: We don't include the ID property, because the primary use for this function is to
		// insert or update database records.
		return array(
			'date_time'     => DateTimes::format_datetime_mysql( $this->date_time ),
			'user_id'       => $this->user_id,
			'user_role'     => $this->user_role,
			'user_ip'       => $this->user_ip,
			'user_location' => $this->user_location,
			'user_agent'    => $this->user_agent,
			'event_type'    => $this->event_type,
			'object_type'   => $this->object_type,
			'object_id'     => $this->object_id,
			'object_name'   => $this->object_name,
			'details'       => wp_json_encode( $this->details ),
			'changes'       => wp_json_encode( $this->changes ),
		);
	}
}
