<?php
/**
 * Contains the Property class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;

/**
 * Represents the type and the old and new values of an object property.
 */
class Property {

	/**
	 * The property ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * The event ID.
	 *
	 * @var int
	 */
	public int $event_id;

	/**
	 * The property name or key.
	 *
	 * @var string
	 */
	public string $key;

	/**
	 * The name of the database table the property comes from, e.g. 'wp_posts'.
	 * This can be null if the property is not related to a database table.
	 *
	 * @var ?string
	 */
	public ?string $table_name;

	/**
	 * The old or current value of the property. This should not be null.
	 *
	 * @var mixed
	 */
	public mixed $val = null;

	/**
	 * The new value of the property, if changed. This will be null if the value wasn't changed.
	 *
	 * @var mixed
	 */
	public mixed $new_val = null;

	/**
	 * Property constructor.
	 *
	 * @param string $key The property name.
	 * @param string $table_name The name of the database table the property belongs to (e.g.
	 *                           'posts'), which could be null if the property is unrelated to a
	 *                           database table.
	 * @param mixed  $val The old or current value of the property.
	 * @param mixed  $new_val Optional. The new value of the property, if changed.
	 */
	public function __construct( string $key, ?string $table_name, mixed $val = null, mixed $new_val = null ) {
		$this->key        = $key;
		$this->table_name = $table_name;
		$this->val        = $val;
		$this->new_val    = $new_val;
	}
}
