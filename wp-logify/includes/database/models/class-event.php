<?php
/**
 * Contains the Event class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;
use Exception;

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
	 * The name of the object associated with the event. This is only used when the object has been
	 * deleted.
	 *
	 * @var ?string
	 */
	public ?string $object_name;

	/**
	 * Properties relating to the event, including current values ans changes.
	 *
	 * @var ?array
	 */
	public ?array $properties;

	/**
	 * The object reference.
	 *
	 * @var ?Object_Reference
	 */
	private ?Object_Reference $object_ref;

	/**
	 * Event Constructor.
	 *
	 * Initializes an empty Event object.
	 */
	public function __construct() {
		// Empty constructor.
	}

	/**
	 * Create a new Event object from an object.
	 *
	 * @param string  $object_type The type of object, e.g. 'post', 'user', 'term'.
	 * @param int     $object_id The ID of the object.
	 * @param ?string $object_name The name of the object.
	 * @return Event The new Event object.
	 * @throws Exception If the user causing the event could not be identified.
	 */
	public static function create( string $object_type, int $object_id, ?string $object_name = null ): Event {
		// Get the current user.
		$current_user = wp_get_current_user();

		// If the current user could not be loaded, this may be a login or logout event.
		// In such cases, we can get the user from the object information.
		if ( ( empty( $current_user ) || empty( $current_user->ID ) ) && $object_type === 'user' ) {
			$current_user = get_userdata( $object_id );
		}

		// If we still don't have a known user (i.e. it's an anonymous user), we shouldn't be in
		// this method, so let's throw an exception.
		if ( empty( $current_user ) || empty( $current_user->ID ) ) {
			throw new Exception( 'Could not identify the user causing the event.' );
		}

		// Construct the new Event object.
		$event                = new Event();
		$event->when_happened = DateTimes::current_datetime();
		$event->user_id       = $current_user->ID;
		$event->user_name     = Users::get_name( $current_user );
		$event->user_role     = implode( ', ', $current_user->roles );
		$event->user_ip       = Users::get_ip();
		$event->user_location = Users::get_location( $event->user_ip );
		$event->user_agent    = Users::get_user_agent();
		$event->object_type   = $object_type;
		$event->object_id     = $object_id;
		$event->object_name   = $object_name;

		return $event;
	}

	/**
	 * Save the event to the database.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function save() {
		return Event_Repository::save( $this );
	}

	/**
	 * Save the event properties to the database.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function save_properties() {
		return Event_Repository::save_properties( $this );
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

	/**
	 * Check if the event properties includes a property with the specified key.
	 *
	 * @param string $property_key The property key.
	 * @return bool True if the property exists, false otherwise.
	 */
	public function has_property( string $property_key ) {
		return key_exists( $property_key, $this->properties );
	}

	/**
	 * Set an event property.
	 *
	 * @param string  $property_key The property key.
	 * @param ?string $table_name The table name the property came from.
	 * @param mixed   $old_value The old or current value.
	 * @param mixed   $new_value The new value.
	 */
	public function set_property( string $property_key, ?string $table_name, mixed $old_value, mixed $new_value = null ) {
		// If the properties array is not set, create it.
		if ( ! isset( $this->properties ) ) {
			$this->properties = array();
		}

		// If the property with this key already exists, update it.
		if ( self::has_property( $property_key ) ) {
			$property             = $this->properties[ $property_key ];
			$property->table_name = $table_name;
			$property->old_value  = $old_value;
			$property->new_value  = $new_value;
		} else {
			// The property with this key doesn't already exist in the properties array, so create it.
			$this->properties[ $property_key ] = new Property( $property_key, $table_name, $old_value, $new_value );
		}
	}

	/**
	 * Set multiple properties at once.
	 *
	 * @param array $properties The properties to set.
	 *
	 * TODO: Remove if not needed.
	 */
	public function set_properties( array $properties ) {
		foreach ( $properties as $property ) {
			$this->set_property( $property->key, $property->table_name, $property->old_value, $property->new_value );
		}
	}

	/**
	 * Add the object's core properties to the event properties.
	 *
	 * TODO: Remove if not needed.
	 */
	public function set_core_properties() {
		$this->set_properties( $this->get_object_reference()->get_core_properties() );
	}

	/**
	 * Get the current or old value of a property.
	 *
	 * @param string $property_key The property key.
	 * @return mixed The current or old value.
	 */
	public function get_property_value( string $property_key ): mixed {
		return $this->properties[ $property_key ]->old_value;
	}

	/**
	 * Set the current or old value of a property.
	 *
	 * @param string $property_key The property key.
	 * @param mixed  $value The current or old property value.
	 */
	public function set_property_value( string $property_key, mixed $value ) {
		$this->properties[ $property_key ]->old_value = $value;
	}

	/**
	 * Get the new value of a property.
	 *
	 * @param string $property_key The property key.
	 * @return mixed The new value of the property.
	 */
	public function get_property_new_value( string $property_key ): mixed {
		return $this->properties[ $property_key ]->new_value;
	}

	/**
	 * Set the new value of a property.
	 *
	 * @param string $property_key The property key.
	 * @param mixed  $value The new value of the property.
	 */
	public function set_property_new_value( string $property_key, mixed $value ) {
		$this->properties[ $property_key ]->new_value = $value;
	}
}
