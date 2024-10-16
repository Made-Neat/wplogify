<?php


	/**
	 * Get the URL for a WordPress release page.
	 *
	 * @param string $version The version number of the release.
	 * @return ?string The URL of the release page, or null if not found.
	 */
public static function get_wp_release_url( string $version ): ?string {
	// Look for the transient first.
	$transient_name = 'wp_release_url_' . $version;
	$url            = get_transient( $transient_name );
	if ( $url ) {
		return $url;
	}

	// The base URL for WordPress releases.
	$url = 'https://wordpress.org/news/category/releases/';

	// Request the URL.
	$response = wp_remote_get( $url );

	// Check if the response code is 200.
	if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
		return null;
	}

	// Get the HTML content.
	$html = wp_remote_retrieve_body( $response );
	if ( ! $html ) {
		return null;
	}

	// Parse the HTML.
	$doc = new DOMDocument();
	@$doc->loadHTML( $html );
	$xpath = new DOMXPath( $doc );

	// Find all links on the page.
	$links = $xpath->query( '//a' );

	// Iterate through the links to find the one matching the version.
	foreach ( $links as $link ) {
		$href = $link->getAttribute( 'href' );
		$text = trim( $link->nodeValue );

		// Check if the link text contains the version number.
		if ( strpos( $text, $version ) !== false ) {
			// Save the URL in a transient for up to a year.
			set_transient( $transient_name, $href, 86400 * 366 );

			// Return the full URL for the release page.
			return $href;
		}
	}

	// If no matching link is found.
	return null;
}
