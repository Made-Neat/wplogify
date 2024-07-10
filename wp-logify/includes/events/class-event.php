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
	public int $event_id;

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
	 * The name of the user who did the action.
	 *
	 * @var string
	 */
	public string $user_name;

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
	 * The type of object associated with the event, e.g. 'post', 'user', 'term'.
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
	 * The name of the object associated with the event. This is only used when the object has been
	 * deleted.
	 *
	 * @var ?string
	 */
	public ?string $object_name;

	/**
	 * Metadata relating to the event.
	 *
	 * @var ?array
	 */
	public ?array $event_meta;

	/**
	 * Properties of the relevant object, including old and new values.
	 *
	 * @var ?array
	 */
	public ?array $properties;

	/**
	 * Event Constructor.
	 *
	 * Initializes an empty Event object.
	 */
	public function __construct() {
		// Empty constructor.
	}
}
