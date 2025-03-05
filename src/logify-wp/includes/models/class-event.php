<?php
/**
 * Contains the Event class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DateTime;
use InvalidArgumentException;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * This class represents a logged event.
 */
class Event {

	/**
	 * The ID of the event.
	 *
	 * @var ?int
	 */
	public ?int $id = null;

	/**
	 * The date and time of the event in the site time zone, stored as a string.
	 *
	 * @var DateTime
	 */
	public DateTime $when_happened;

	/**
	 * The ID of the user who did the action. Will be 0 for an anonymous user.
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
	 * The role of the user. Will be 'none' for an anonymous user.
	 *
	 * @var string
	 */
	public string $user_role;

	/**
	 * The IP address of the user.
	 *
	 * @var string
	 */
	public ?string $user_ip;

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
	 * The subtype of the object associated with the event, which will be the post type for posts,
	 * and the taxonomy for terms.
	 *
	 * @var ?string
	 */
	public ?string $object_subtype;

	/**
	 * 
	 * The note related to event
	 *
	 * @var ?string
	 */
	public ?object  $eventnotes;

	/**
	 * The unique identifier of the object associated with the event.
	 *
	 * This will be an integer matching a database primary key in the case of a post, user, term, or
	 * comment.
	 * For plugins, it will be the slug.
	 * For themes, it will be the theme directory name a.k.a. stylesheet.
	 * For options, it will be the option name.
	 *
	 * @var null|int|string
	 */
	public null|int|string $object_key = null;

	/**
	 * The name of the object associated with the event.
	 *
	 * For a theme or plugin, this serves as the unique identifier for the object.
	 * For other objects, this is the name of the object at the time of the event and will only be
	 * used if the object has been deleted.
	 *
	 * @var ?string
	 */
	public ?string $object_name = null;

	/**
	 * Properties relating to the event, including current values and changes.
	 *
	 * @var ?Property[]
	 */
	public ?array $properties = null;

	/**
	 * Metadata relating to the event.
	 *
	 * @var ?Eventmeta[]
	 */
	public ?array $eventmetas = null;

	/**
	 * The object.
	 * This is not stored in the database.
	 *
	 * @var ?object
	 */
	private ?object $object = null;

	/**
	 * The object reference.
	 * This is not stored in the database.
	 *
	 * @var ?Object_Reference
	 */
	private ?Object_Reference $object_ref = null;

	// =============================================================================================
	/**
	 * Event Constructor.
	 *
	 * Initializes an empty Event object.
	 */
	public function __construct() {
		// Empty constructor.
	}

	/**
	 * Creates a new event.
	 *
	 * @param string                   $event_type  The type of event.
	 * @param null|object|array|string $wp_object   The object or object type the event is about.
	 * @param ?array                   $eventmetas  The event metadata.
	 * @param ?array                   $properties  The event properties.
	 * @param null|int|WP_User         $acting_user The user who performed the action. This can be a user ID or WP_User object, or null for the current user.
	 * @param bool                     $all_users   If true, create the event regardless of the acting user's role, or if a user is logged in.
	 * @return ?Event The new event, or null if the event was not created.
	 */
	public static function create(
		string $event_type,
		null|object|array|string $wp_object = null,
		?array $eventmetas = null,
		?array $properties = null,
		null|int|WP_User $acting_user = null,
		bool $all_users = false
	): ?Event {
		// If the event is about an object deletion, this is where we'd store the details of the
		// deleted object in the database. We want to do it before user checking so every object
		// deletion is tracked regardless of who did it. Then we will definitely have the old name.

		// Get the acting user data.
		$user_data = User_Utility::get_user_data( $acting_user );

		// If this is not a login event, and we aren't tracking this user's role, or the user isn't
		// logged in, we don't need to log the event.
		$log_it = $all_users ||
			( $user_data['object'] && Access_Control::user_has_role( $user_data['object'], Plugin_Settings::get_roles_to_track() ) );
		if ( ! $log_it ) {
			Debug::info( "$event_type event not logged. This is not an all-users event, and the acting user ({$user_data['id']}) doesn't have a role that is being tracked." );
			return null;
		}

		// Get the object reference.
		if ( $wp_object === null || $wp_object instanceof Object_Reference ) {
			$object_ref = $wp_object;
		} elseif ( is_string( $wp_object ) ) {
			$object_ref = new Object_Reference( $wp_object, null, null );
		} else {
			$object_ref = Object_Reference::new_from_wp_object( $wp_object );
		}

		// Get the object subtype if applicable.
		$object_type = $object_ref?->type;
		if ( $object_type === 'post' ) {
			$post           = $wp_object instanceof WP_Post ? $wp_object : Post_Utility::load( $object_ref->key );
			$object_subtype = $post->post_type;
		} elseif ( $object_type === 'term' ) {
			$term           = $wp_object instanceof WP_Term ? $wp_object : Term_Utility::load( $object_ref->key );
			$object_subtype = $term->taxonomy;
		} else {
			$object_subtype = null;
		}

		// Construct the new Event object.
		$event                 = new Event();
		$event->when_happened  = DateTimes::current_datetime();
		$event->user_id        = $user_data['id'];
		$event->user_name      = $user_data['name'];
		$event->user_role      = implode( ', ', $user_data['roles'] );
		$event->user_ip        = User_Utility::get_ip();
		$event->user_location  = User_Utility::get_location( $event->user_ip );
		$event->user_agent     = User_Utility::get_user_agent();
		$event->event_type     = $event_type;
		$event->object_type    = $object_type;
		$event->object_subtype = $object_subtype;
		$event->object_key     = $object_ref?->key;
		$event->object_name    = $object_ref?->name;
		$event->eventmetas     = empty( $eventmetas ) ? null : $eventmetas;
		$event->properties     = null;

		// Add the core properties.
		if ( $object_ref instanceof Object_Reference ) {
			$event->add_props( $object_ref->get_core_properties() );
		}

		// Add any other properties we received. This could overwrite some core ones.
		$event->add_props( $properties );

		return $event;
	}

	/**
	 * Save the event to the database.
	 *
	 * @return bool True if the event was saved successfully, false otherwise.
	 */
	public function save(): bool {
		// Save the event to the database.
		$ok = Event_Repository::save( $this );

		// Log the result.
		if ( $ok ) {
			Debug::info( 'EVENT SAVED:', $this->id, $this->event_type );
		} else {
			Debug::error( 'FAILED TO SAVE EVENT:', $this->id, $this->event_type );
		}

		return $ok;
	}

	/**
	 * Check if a new event has been saved yet or not.
	 *
	 * @return bool True if the event has no ID, and thus hasn't yet been saved to the database.
	 */
	public function is_new(): bool {
		return empty( $this->id );
	}

	/**
	 * Delete the event from the database.
	 *
	 * @return bool True if the event was deleted successfully, false otherwise.
	 */
	public function delete(): bool {
		// If the event has no ID, it can't be deleted.
		if ( ! $this->id ) {
			return false;
		}

		// Delete the event from the database.
		$ok = Event_Repository::delete( $this->id );

		// Clear the ID.
		$this->id = null;

		// Log the result.
		if ( $ok ) {
			Debug::info( 'EVENT DELETED:', $this->id, $this->event_type );
		} else {
			Debug::error( 'FAILED TO DELETE EVENT:', $this->id, $this->event_type );
		}

		return $ok;
	}

	// =============================================================================================
	// Methods for getting information about the object.

	/**
	 * Get the object associated with the event.
	 *
	 * @return ?object The object, or null if there is no object.
	 */
	public function get_object(): ?object {
		// Check if it's already been set.
		if ( isset( $this->object ) ) {
			return $this->object;
		}

		// Handle the case where the event has no object.
		if ( $this->object_type === null ) {
			return null;
		}

		// Get the object and remember it.
		$this->object = $this->get_object_ref()->get_object();

		return $this->object;
	}

	/**
	 * Get the Object_Reference for the object associated with the event.
	 *
	 * @return ?Object_Reference The Object_Reference for the object, or null if there is no object.
	 */
	public function get_object_ref(): ?Object_Reference {
		// Check if it's already been set.
		if ( isset( $this->object_ref ) ) {
			return $this->object_ref;
		}

		// Handle the case where the event has no object.
		if ( $this->object_type === null ) {
			return null;
		}

		// Construct the object reference and remember it.
		$this->object_ref = new Object_Reference( $this->object_type, $this->object_key, $this->object_name );

		return $this->object_ref;
	}

	/**
	 * Get the HTML for an object tag.
	 *
	 * @return string The object tag.
	 */
	public function get_object_tag(): string {
		// Make sure the object reference has been created.
		$object_ref = $this->get_object_ref();

		// Handle menu items differently.
		if ( $this->object_is_menu_item() ) {
			$tag = Menu_Item_Utility::get_tag_from_event( $this );
			if ( $tag ) {
				return $tag;
			}
		}

		// Return the tag, or empty string if there is no object reference.
		return $object_ref === null ? '' : $object_ref->get_tag();
	}

	// =============================================================================================
	// Methods for getting and setting properties.

	/**
	 * Check if the event properties includes a property with the specified key.
	 *
	 * @param string $prop_key The property key.
	 * @return bool True if the property exists, false otherwise.
	 */
	public function has_prop( string $prop_key ): bool {
		return is_array( $this->properties ) && key_exists( $prop_key, $this->properties );
	}

	/**
	 * Get an event property.
	 *
	 * @param string $prop_key The property key.
	 * @return ?Property The property or null if not set.
	 */
	public function get_prop( string $prop_key ): ?Property {
		return $this->properties[ $prop_key ] ?? null;
	}

	/**
	 * Set an event property.
	 *
	 * @param string  $prop_key   The property key.
	 * @param ?string $table_name The table name the property came from.
	 * @param mixed   $val        The old or current value.
	 * @param mixed   $new_val    The new value.
	 */
	public function set_prop( string $prop_key, ?string $table_name, mixed $val, mixed $new_val = null ) {
		// If the properties array hasn't been created yet, do it now.
		if ( ! isset( $this->properties ) ) {
			$this->properties = array();
		}

		// Update the properties array.
		Property::update_array( $this->properties, $prop_key, $table_name, $val, $new_val );
	}

	/**
	 * Add an event property.
	 *
	 * If there is already a property with this key, it will be replaced.
	 *
	 * @param Property The property to add to the event.
	 */
	public function add_prop( Property $prop ) {
		// If the properties array hasn't been created yet, do it now.
		if ( ! isset( $this->properties ) ) {
			$this->properties = array();
		}

		// Update the properties array.
		$this->properties[ $prop->key ] = $prop;
	}

	/**
	 * Add multiple properties to the event.
	 *
	 * @param ?array $props The properties to add.
	 */
	public function add_props( ?array $props ) {
		// If there are no properties, return.
		if ( empty( $props ) ) {
			return;
		}

		// Add each property.
		foreach ( $props as $prop ) {
			$this->add_prop( $prop );
		}
	}

	/**
	 * Remove an event property.
	 *
	 * @param string $prop_key The key of the property to remove.
	 */
	public function remove_prop( string $prop_key ) {
		if ( $this->has_prop( $prop_key ) ) {
			unset( $this->properties[ $prop_key ] );
		}
	}

	/**
	 * Get the current or old value of a property.
	 *
	 * @param string $prop_key The property key.
	 * @return mixed The current or old value.
	 * @throws InvalidArgumentException If the property with the specified key is not found.
	 */
	public function get_prop_val( string $prop_key ): mixed {
		// Try to get the property.
		$prop = $this->get_prop( $prop_key );

		// If the property is not found, throw an exception.
		if ( $prop === null ) {
			throw new InvalidArgumentException( esc_html( "Property with key '$prop_key' not found." ) );
		}

		return $prop->val;
	}

	/**
	 * Set the current or old value of a property.
	 *
	 * @param string $prop_key The property key.
	 * @param mixed  $val      The current or old property value.
	 * @throws InvalidArgumentException If the property with the specified key is not found.
	 */
	public function set_prop_val( string $prop_key, mixed $val ) {
		// Try to get the property.
		$prop = $this->get_prop( $prop_key );

		// If the property is not found, throw an exception.
		if ( $prop === null ) {
			throw new InvalidArgumentException( esc_html( "Property with key '$prop_key' not found." ) );
		}

		// Set the value.
		$prop->val = $val;
	}

	/**
	 * Get the new value of a property.
	 *
	 * @param string $prop_key The property key.
	 * @return mixed The new value of the property.
	 * @throws InvalidArgumentException If the property with the specified key is not found.
	 */
	public function get_prop_new_val( string $prop_key ): mixed {
		// Try to get the property.
		$prop = $this->get_prop( $prop_key );

		// If the property is not found, throw an exception.
		if ( $prop === null ) {
			throw new InvalidArgumentException( esc_html( "Property with key '$prop_key' not found." ) );
		}

		return $prop->new_val;
	}

	/**
	 * Set the new value of a property.
	 *
	 * @param string $prop_key The property key.
	 * @param mixed  $new_val  The new value of the property.
	 * @throws InvalidArgumentException If the property with the specified key is not found.
	 */
	public function set_prop_new_val( string $prop_key, mixed $new_val ) {
		// Try to get the property exists.
		$prop = $this->get_prop( $prop_key );

		// If the property is not found, throw an exception.
		if ( $prop === null ) {
			throw new InvalidArgumentException( esc_html( "Property with key '$prop_key' not found." ) );
		}

		// Set the new value.
		$prop->new_val = $new_val;
	}

	/**
	 * See if the event has any property changes.
	 *
	 * TODO: This method assumes that a new_val of null means no change.
	 * TODO: This is not always the case; a property can be changed to null.
	 * TODO: We still need to add the "changed" flag fo the properties table.
	 * TODO: Then we can update this method.
	 *
	 * @return bool True if the event includes property changes, false otherwise.
	 */
	public function has_changes(): int {
		// Check if any property has a new value.
		foreach ( $this->properties as $prop ) {
			if ( $prop->new_val !== null ) {
				return true;
			}
		}

		return false;
	}

	// =============================================================================================
	// Methods for getting and setting eventmetas.

	/**
	 * Check if the eventmetas includes one with the specified key.
	 *
	 * @param string $meta_key The eventmeta key.
	 * @return bool True if the eventmeta exists, false otherwise.
	 */
	public function has_meta( string $meta_key ) {
		return isset( $this->eventmetas ) && key_exists( $meta_key, $this->eventmetas );
	}

	/**
	 * Get an eventmeta.
	 *
	 * @param string $meta_key The eventmeta key.
	 * @return mixed The eventmeta object or null if not set.
	 */
	public function get_meta( string $meta_key ): mixed {
		return empty( $this->eventmetas[ $meta_key ] ) ? null : $this->eventmetas[ $meta_key ];
	}

	/**
	 * Get an eventmeta value.
	 *
	 * @param string $meta_key The eventmeta key.
	 * @return mixed The eventmeta value or null if not set.
	 */
	public function get_meta_val( string $meta_key ): mixed {
		$eventmeta = $this->get_meta( $meta_key );
		return $eventmeta->meta_value ?? null;
	}

	/**
	 * Set an eventmeta data value.
	 *
	 * @param string $meta_key The eventmeta key.
	 * @param mixed  $meta_value The eventmeta value.
	 */
	public function set_meta( string $meta_key, mixed $meta_value ) {
		// Create the eventmeta array if necessary.
		if ( ! isset( $this->eventmetas ) ) {
			$this->eventmetas = array();
		}

		// Create or update the eventmeta.
		if ( $this->has_meta( $meta_key ) ) {
			$this->eventmetas[ $meta_key ]->meta_value = $meta_value;
		} else {
			$this->eventmetas[ $meta_key ] = new Eventmeta( $this->id, $meta_key, $meta_value );
		}
	}

	/**
	 * Check if the object associated with the event is a menu item.
	 *
	 * @return bool True if the object is a menu item, false otherwise.
	 */
	public function object_is_menu_item(): bool {
		// Check it's a post.
		if ( $this->object_type !== 'post' ) {
			return false;
		}

		// Try to load the post.
		$post = $this->object_key ? Post_Utility::load( $this->object_key ) : null;

		// If we could load the post, check the post type.
		if ( $post ) {
			return $post->post_type === 'nav_menu_item';
		}

		// If we couldn't load the post (it may have been deleted), check the object properties.
		$prop = $this->get_prop( 'post_type' );
		if ( $prop ) {
			return $prop->val === 'nav_menu_item';
		}

		// It isn't.
		return false;
	}
}
