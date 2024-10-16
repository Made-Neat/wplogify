<?php
/**
 * Contains the Urls class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

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
		// Request the page.
		$response = wp_remote_get( $url );

		// Check if the response code is 200.
		return wp_remote_retrieve_response_code( $response ) === 200;
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
