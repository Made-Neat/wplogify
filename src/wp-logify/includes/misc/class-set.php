<?php
/**
 * Contains the Set class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Set class. Emulates sets, i.e. unordered collections with no duplicates.
 */
class Set {

	/**
	 * Items in the set.
	 *
	 * @var array
	 */
	protected $items;

	// =============================================================================================
	// Constructors.

	/**
	 * Constructor.
	 *
	 * Creates a new set containing the values provided.
	 * Duplicate values will be ignored.
	 */
	public function __construct( ...$items ) {
		$this->items = array_unique( array_values( $items ) );
	}

	/**
	 * Create a set from an array.
	 *
	 * @param array $arr The array to create the set from.
	 * @return Set The set created from the array.
	 */
	public static function fromArray( array $arr = array() ): Set {
		return new self( ...$arr );
	}

	// =============================================================================================
	// Basic operations.

	/**
	 * Get the items.
	 *
	 * @return array The items in the set.
	 */
	public function items(): array {
		return $this->items;
	}

	/**
	 * Return the number of items in the set.
	 *
	 * @return int The number of items in the set.
	 */
	public function count() {
		return count( $this->items );
	}

	/**
	 * Check if the set contains a given item.
	 *
	 * @param mixed $item The item to check.
	 * @return bool True if the set contains the item, false otherwise.
	 */
	public function contains( $item ) {
		return in_array( $item, $this->items );
	}

	/**
	 * Add an item to the set.
	 *
	 * @param mixed $item The item to add.
	 * @return Set The set with the item added.
	 */
	public function add( $item ) {
		if ( ! in_array( $item, $this->items, true ) ) {
			$this->items[] = $item;
		}
		return $this;
	}

	/**
	 * Remove an item from the set.
	 *
	 * @param mixed $item The item to remove.
	 * @return Set The set with the item removed.
	 */
	public function remove( $item ) {
		$this->items = array_values( array_diff( $this->items, array( $item ) ) );
		return $this;
	}

	/**
	 * Checks if a set is empty.
	 *
	 * @return bool True if the set is empty, false otherwise.
	 */
	public function isEmpty(): bool {
		return $this->count() === 0;
	}

	// =============================================================================================
	// Standard set operations.

	/**
	 * Union of two sets.
	 *
	 * @param Set $set2 The second set.
	 * @return Set The union of the two sets.
	 */
	public function union( self $set2 ): Set {
		return new self( array_merge( $this->items, $set2->items ) );
	}

	/**
	 * Difference between two sets.
	 *
	 * @param Set $set2 The second set.
	 * @return Set The difference between the two sets.
	 */
	public function diff( self $set2 ): Set {
		return new self( array_diff( $this->items, $set2->items ) );
	}

	/**
	 * Intersection between two sets.
	 *
	 * @param Set $set2 The second set.
	 * @return Set The intersection between the two sets.
	 */
	public function intersect( self $set2 ): Set {
		return new self( array_intersect( $this->items, $set2->items ) );
	}

	// =============================================================================================
	// Comparison methods.

	/**
	 * Checks if two sets are equal.
	 *
	 * @param Set $set2 The second set.
	 * @return bool True if the sets are equal, false otherwise.
	 */
	public function equals( self $set2 ): bool {
		return $this->count() === $set2->count() && $this->isSubset( $set2 );
	}

	/**
	 * Checks if a set is a subset of another set.
	 *
	 * @param Set $set2 The second set.
	 * @return bool True if the set is a subset of the other set, false otherwise.
	 */
	public function isSubset( self $set2 ): bool {
		foreach ( $this->items as $item ) {
			if ( ! $set2->contains( $item ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if a set is a proper subset of another set.
	 *
	 * @param Set $set2 The second set.
	 * @return bool True if the set is a proper subset of the other set, false otherwise.
	 */
	public function isProperSubset( self $set2 ): bool {
		return $this->count() < $set2->count() && $this->isSubset( $set2 );
	}

	/**
	 * Checks if a set is a isSuperset of another set.
	 *
	 * @param Set $set2 The second set.
	 * @return bool True if the set is a superset of the other set, false otherwise.
	 */
	public function isSuperset( self $set2 ): bool {
		return $set2->isSubset( $this );
	}

	/**
	 * Checks if a set is a proper superset of another set.
	 *
	 * @param Set $set2 The second set.
	 * @return bool True if the set is a proper superset of the other set, false otherwise.
	 */
	public function isProperSuperset( self $set2 ): bool {
		return $set2->isProperSubset( $this );
	}

	// =============================================================================================
	// Conversion methods.

	/**
	 * Convert set to a string.
	 *
	 * @return string The set as a string.
	 */
	function toString( $glue = ', ', $left_bracket = '{', $right_bracket = '}' ): string {

		// Convert the set's item to strings.
		$items = array_map(
			function ( $item ) use ( $glue, $left_bracket, $right_bracket ): string {
				return $item instanceof Set
					? $item->toString( $glue, $left_bracket, $right_bracket )
					: wp_json_encode( $item );
			},
			$this->items
		);

		return $left_bracket . implode( $glue, $items ) . $right_bracket;
	}

	/**
	 * Magic method providing default behaviour for converting a set to a string.
	 *
	 * @return string The set as a string.
	 */
	function __toString(): string {
		return $this->toString();
	}
}
