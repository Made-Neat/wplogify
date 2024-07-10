<?php


	/**
	 * Check if the object exists.
	 *
	 * @return bool True if the object of the given type and ID exists in the database, false otherwise.
	 * @throws Exception If the object type is unknown.
	 */
public function object_exists(): bool {
	// Check we know which object to load.
	if ( empty( $this->type ) || empty( $this->id ) ) {
		throw new Exception( 'Cannot check for existence of an object without knowing its type and ID.' );
	}

	// Call the appropriate exists method.
	$method = array( self::get_class(), "{$this->type}_exists" );
	return call_user_func( $method, $this->id );
}


	/**
	 * Get the edit URL for the object.
	 *
	 * @return string The URL.
	 */
public function get_edit_url(): string {
	$method = array( self::get_class(), 'get_edit_url' );
	return call_user_func( $method, $this->id );
}

	/**
	 * Get the HTML for the link to the object's edit page.
	 *
	 * @return string The link HTML.
	 */
public function get_edit_link() {
	$method = array( self::get_class(), 'get_edit_link' );
	return call_user_func( $method, $this->id );
}

	/**
	 * If the object hasn't been deleted, get a link to its edit page; otherwise, get a span with
	 * the old name.
	 */
public function get_tag() {
	$method = array( self::get_class(), 'get_tag' );
	return call_user_func( $method, $this->id, $this->name );
}
