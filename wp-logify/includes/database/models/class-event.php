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
	 * @var DateTime
	 */
	public DateTime $when_happened;

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
	 * @var null|int|string
	 */
	public null|int|string $object_id;

	/**
	 * The object reference.
	 *
	 * @var ?Object_Reference
	 */
	private ?Object_Reference $object_ref;

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

	/**
	 * Get the Object_Reference for the object associated with the event.
	 *
	 * @return ?Object_Reference The Object_Reference for the object, or null if there is no object.
	 */
	public function get_object_reference(): ?Object_Reference {
		// Check if it's already been set.
		if ( isset( $this->object_ref ) ) {
			return $this->object_ref;
		}

		// Handle the null case.
		if ( $this->object_type === null || $this->object_id === null ) {
			return null;
		}

		// Construct the object reference.
		$this->object_ref = new Object_Reference( $this->object_type, $this->object_id, $this->object_name );

		return $this->object_ref;
	}

	/**
	 * Get the HTML for an object tag.
	 *
	 * @return string The object tag.
	 */
	public function get_object_tag(): string {
		// Make sure the object reference has been created.
		$object_ref = $this->get_object_reference();

		// Return the tag, or empty string if there is no object reference.
		return $object_ref === null ? '' : $object_ref->get_tag();
	}
}
