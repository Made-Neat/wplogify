<?php
/**
 * Contains the Urls class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DOMDocument;
use DOMXPath;

/**
 * Class Logify_WP\Urls
 *
 * This is a utility class containing methods relating to URLs.
 */
class Urls {

	/**
	 * Check if a page exists at the given URL.
	 *
	 * @param mixed $url The URL to check.
	 * @return bool True if the page exists, false otherwise.
	 */
	public static function url_exists( $url ): bool {
		$response = wp_remote_get( $url );
		return $response['response']['code'] == 200;
	}

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

		// Load the content of the page.
		$html = file_get_contents( $url );
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

	/**
	 * Generates a link to lookup an IP address.
	 *
	 * @param string $ip_address An IP address.
	 * @return string The HTML link to the IP address lookup page.
	 */
	public static function get_ip_link( string $ip_address ): string {
		$esc_ip = esc_html( $ip_address );
		return "<a href='https://whatismyipaddress.com/ip/$esc_ip' target='_blank'>$esc_ip</a>";
	}

	/**
	 * If a given URL doesn't have a scheme, add one.
	 *
	 * @param string $url The URL to fix.
	 * @return string The fixed URL.
	 */
	public static function fix_scheme( string $url ): string {
		// Check if the URL has a scheme already.
		if ( preg_match( '/^https?:\/\//', $url ) ) {
			return $url;
		}

		// Try https first, as that's preferred.
		$https_url = 'https://' . $url;

		// Check if it exists.
		if ( self::url_exists( $https_url ) ) {
			return $https_url;
		}

		// If https doesn't work, fall back to http.
		return 'http://' . $url;
	}
}
