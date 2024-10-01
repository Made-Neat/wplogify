<?php
/**
 * Contains the Set class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Set class. Emulates sets, i.e. unordered collections with no duplicates.
 */
class Set implements Countable, IteratorAggregate {

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
	 * Arrays will be flattened.
	 */
	public function __construct( ...$items ) {
		$this->items = array();
		$this->addItems( $items );
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
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Check if the set contains a given item.
	 *
	 * @param mixed $item The item to check.
	 * @return bool True if the set contains the item, false otherwise.
	 */
	public function contains( $item ): bool {
		return in_array( $item, $this->items, true );
	}

	/**
	 * Add an item to the set.
	 *
	 * @param mixed $item The item to add.
	 * @return self The set with the item added.
	 */
	public function add( $item ): self {
		if ( ! $this->contains( $item ) ) {
			$this->items[] = $item;
		}
		return $this;
	}

	/**
	 * Add multiple items to the set.
	 *
	 * Note, this method will recursively flatten nested arrays.
	 * No arrays will be added to the set.
	 *
	 * @param array $items The items to add.
	 * @return self The set with the items added.
	 */
	public function addItems( array $items ): self {
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$this->addItems( $item );
			} else {
				$this->add( $item );
			}
		}
		return $this;
	}

	/**
	 * Remove an item from the set.
	 *
	 * @param mixed $item2 The item to remove.
	 * @return self The set with the item removed.
	 */
	public function remove( $item ): self {
		$new_items = array();
		foreach ( $this->items as $item2 ) {
			if ( $item !== $item2 ) {
				$new_items[] = $item2;
			}
		}
		$this->items = $new_items;
		return $this;
	}

	/**
	 * Checks if a set is empty.
	 *
	 * @return bool True if the set is empty, false otherwise.
	 */
	public function isEmpty(): bool {
		return empty( $this->items );
	}

	/**
	 * Clears all items from the set.
	 *
	 * @return self The cleared set.
	 */
	public function clear(): self {
		$this->items = array();
		return $this;
	}

	// =============================================================================================
	// Standard set operations.

	/**
	 * Union of two sets.
	 *
	 * @param self $set2 The second set.
	 * @return self The union of the two sets.
	 */
	public function union( self $set2 ): self {
		// The arrays will be flatted and duplicates will be ignored.
		return new self( $this->items, $set2->items );
	}

	/**
	 * Difference between two sets.
	 *
	 * @param self $set2 The second set.
	 * @return self The difference between the two sets.
	 */
	public function diff( self $set2 ): self {
		// Construct new set containing items from the first set that are not in the second set.
		// We use this instead of array_diff() because that function does not use strict comparison.
		$result = new self();
		foreach ( $this->items as $item ) {
			if ( ! $set2->contains( $item ) ) {
				$result->add( $item );
			}
		}
		return $result;
	}

	/**
	 * Intersection between two sets.
	 *
	 * @param self $set2 The second set.
	 * @return self The intersection between the two sets.
	 */
	public function intersect( self $set2 ): self {
		// Construct new set containing items from the first set that are also in the second set.
		// We use this instead of array_intersect() because that function does not use strict comparison.
		$result = new self();
		foreach ( $this->items as $item ) {
			if ( $set2->contains( $item ) ) {
				$result->add( $item );
			}
		}
		return $result;
	}

	// =============================================================================================
	// Comparison methods.

	/**
	 * Checks if two sets are equal.
	 *
	 * @param self $set2 The second set.
	 * @return bool True if the sets are equal, false otherwise.
	 */
	public function equals( self $set2 ): bool {
		return $this->count() === $set2->count() && $this->isSubset( $set2 );
	}

	/**
	 * Checks if a set is a subset of another set.
	 *
	 * @param self $set2 The second set.
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
	 * @param self $set2 The second set.
	 * @return bool True if the set is a proper subset of the other set, false otherwise.
	 */
	public function isProperSubset( self $set2 ): bool {
		return $this->count() < $set2->count() && $this->isSubset( $set2 );
	}

	/**
	 * Checks if a set is a isSuperset of another set.
	 *
	 * @param self $set2 The second set.
	 * @return bool True if the set is a superset of the other set, false otherwise.
	 */
	public function isSuperset( self $set2 ): bool {
		return $set2->isSubset( $this );
	}

	/**
	 * Checks if a set is a proper superset of another set.
	 *
	 * @param self $set2 The second set.
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
	public function toString( $glue = ', ', $left_bracket = '{', $right_bracket = '}' ): string {

		// Convert the set's item to strings.
		$items = array_map(
			function ( $item ) use ( $glue, $left_bracket, $right_bracket ): string {
				return $item instanceof self
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
	public function __toString(): string {
		return $this->toString();
	}

	// =============================================================================================
	// Iterator methods.

	/**
	 * Get an iterator for the set.
	 *
	 * @return Traversable An iterator for the set.
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->items );
	}
}
