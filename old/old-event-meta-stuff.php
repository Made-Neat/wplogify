From event repository:


// =============================================================================================
	// Methods for loading and saving metadata.

	/**
	 * Save metadata for an event.
	 *
	 * @param Event $event The event object.
	 * @return bool True on success, false on failure.
	 */
	public static function save_metadata( Event $event ): bool {
		// Delete all existing associated records in the event_meta table.
		$ok = Event_Meta_Repository::delete_by_event_id( $event->id );

		// Return on error.
		if ( ! $ok ) {
			return false;
		}

		// If we have any metadata, insert new records.
		if ( ! empty( $event->event_meta ) ) {
			foreach ( $event->event_meta as $meta_key => $meta_value ) {

				// Construct the new Event_Meta object.
				$event_meta_object = new Event_Meta( $event->id, $meta_key, $meta_value );

				// Save the object.
				$ok = Event_Meta_Repository::save( $event_meta_object );

				// Rollback and return on error.
				if ( ! $ok ) {
					return false;
				}
			}
		}

		return true;
	}
