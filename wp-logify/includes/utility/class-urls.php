<?php

namespace WP_Logify;

class Urls {

	/**
	 * Check if a page exists at the given URL.
	 *
	 * @param mixed $url The URL to check.
	 * @return bool True if the page exists, false otherwise.
	 */
	public static function url_exists( $url ) {
		$response = wp_remote_get( $url );
		return $response['response']['code'] == 200;
	}
}
