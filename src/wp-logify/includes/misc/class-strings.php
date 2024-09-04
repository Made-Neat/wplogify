<?php
/**
 * Contains the Strings class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Miscellaneous static methods for working with strings.
 */
class Strings {

	/**
	 * Check if a string looks like a null.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like a null.
	 */
	public static function looks_like_null( string $value ): bool {
		return $value === 'null';
	}

	/**
	 * Check if a string looks like a boolean.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like a boolean.
	 */
	public static function looks_like_bool( string $value ): bool {
		return $value === 'true' || $value === 'false';
	}

	/**
	 * Check if a string looks like an integer.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like an integer.
	 */
	public static function looks_like_int( string $value ): bool {
		return $value === (string) (int) $value;
	}

	/**
	 * Check if a string looks like a floating point value.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like a floating point value.
	 */
	public static function looks_like_float( string $value ): bool {
		return $value === (string) (float) $value;
	}

	/**
	 * Check if a string looks like a MySQL datetime.
	 *
	 * @param string $value The value to check.
	 * @return bool Whether the value looks like a MySQL datetime.
	 */
	public static function looks_like_datetime( string $value ): bool {
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) === 1;
	}

	/**
	 * Check if a value is null or an empty string.
	 *
	 * @param mixed $value The value to check.
	 * @return bool Whether the value is null or an empty string.
	 */
	public static function is_null_or_empty_string( mixed $value ): bool {
		return $value === null || $value === '';
	}

	/**
	 * Convert a camel-case string into an array of words.
	 *
	 * @param string $str The camel-case string.
	 * @return array The array of words.
	 */
	public static function camel_case_to_words( string $str ): array {
		// Split the camel-case string into words.
		return preg_split( '/(?<!^)(?=[A-Z])/', $str );
	}

	/**
	 * Make a key readable.
	 *
	 * This function takes a key and makes it more readable by converting it to title case and
	 * replacing underscores with spaces.
	 *
	 * @param ?string $key    The key to make readable. Could be null.
	 * @param bool    $ucwords Whether to capitalize the first letter of each word.
	 * @return string The readable key.
	 */
	public static function make_key_readable( ?string $key, bool $ucwords = false ): string {
		// Handle null key.
		if ( $key === null ) {
			return '';
		}

		// Handle some special, known cases.
		switch ( $key ) {
			case 'blogname':
				return 'Blog name';

			case 'blogdescription':
				return 'Blog description';

			case 'user_pass':
				return 'Password';

			case 'user_nicename':
				return 'Nice name';

			case 'show_admin_bar_front':
				return 'Show toolbar';

			case 'user registered':
				return 'Registered (UTC)';

			case 'post_date':
				return 'Created';

			case 'post_date_gmt':
				return 'Created (UTC)';

			case 'post_modified':
				return 'Last modified';

			case 'post_modified_gmt':
				return 'Last modified (UTC)';
		}

		// Convert snake-case or kebab-case keys into words.
		$words = array_filter( preg_split( '/[-_ ]+/', $key ) );

		// Convert camel-case keys into words.
		$words2 = array();
		foreach ( $words as &$word ) {
			// If it's all lowercase or all uppercase, leave unchanged.
			if ( $word === strtolower( $word ) || $word === strtoupper( $word ) ) {
				array_push( $words2, $word );
			} else {
				// If it's mixed-case, assume camel-case and split it.
				$words2 = array_merge( $words2, self::camel_case_to_words( $word ) );
			}
		}
		$words = $words2;

		// Process the words.
		foreach ( $words as $i => $word ) {
			// Process height and width abbreviations.
			if ( $word === 'h' ) {
				$words[ $i ] = 'height';
			} elseif ( $word === 'w' ) {
				$words[ $i ] = 'width';
			}

			// Make acronyms upper-case.
			if ( in_array( $word, self::ACRONYMS, true ) ) {
				$words[ $i ] = strtoupper( $word );
			} elseif ( $ucwords ) {
				// Upper-case the first letter of the word if requested.
				$words[ $i ] = ucfirst( $word );
			}
		}

		// Convert to readable string.
		return ucfirst( implode( ' ', $words ) );
	}

	/**
	 * Check if a string starts with a prefix.
	 *
	 * @param string $str    The string to check.
	 * @param string $prefix The prefix to check for.
	 * @return bool Whether the string starts with the prefix.
	 */
	public static function starts_with( string $str, string $prefix ): bool {
		return substr( $str, 0, strlen( $prefix ) ) === $prefix;
	}

	/**
	 * Check if a string ends with a suffix.
	 *
	 * @param string $str    The string to check.
	 * @param string $suffix The suffix to check for.
	 * @return bool Whether the string ends with the suffix.
	 */
	public static function ends_with( string $str, string $suffix ): bool {
		return substr( $str, -strlen( $suffix ) ) === $suffix;
	}

	/**
	 * Get a snippet from a piece of content.
	 *
	 * @param string $content The content.
	 * @return string The snippet of the content.
	 */
	public static function get_snippet( string $content ): string {
		// Strip all HTML tags and excess whitespace from the content.
		$content = self::strip_tags( $content );

		// If the content is short enough, return it as is.
		if ( strlen( $content ) <= Logger::MAX_OBJECT_NAME_LENGTH ) {
			return $content;
		}

		// Otherwise, truncate it.
		return substr( $content, 0, Logger::MAX_OBJECT_NAME_LENGTH - 3 ) . '...';
	}

	/**
	 * Strip all HTML tags from a string, maintaining sensible whitespace.
	 *
	 * @param ?string $text The text to strip tags from. Can be null.
	 * @return ?string The text with all tags removed, or null, if null was provided.
	 */
	public static function strip_tags( ?string $text ): ?string {
		// Handle a null or empty string.
		if ( self::is_null_or_empty_string( $text ) ) {
			return $text;
		}

		// Strip script and style tags, and anything inside them. Replace each with single space.
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', ' ', $text );

		// Strip other tags, replacing each with a single space.
		$text = preg_replace( '/<[^>]*>/', ' ', $text );

		// Collapse all sequences of whitespace characters into single spaces.
		$text = preg_replace( '/\s+/', ' ', $text );

		// Trim the text.
		return trim( $text );
	}

	/**
	 * Convert a version string (e.g. '1.23.45') to a float.
	 *
	 * @param string $version The version string.
	 * @return float The version as a float.
	 */
	public static function version_to_float( string $version ): float {
		$parts = explode( '.', $version );
		$major = empty( $parts[0] ) ? 0 : (int) $parts[0];
		$minor = empty( $parts[1] ) ? 0 : (int) $parts[1];
		$patch = empty( $parts[2] ) ? 0 : (int) $parts[2];
		return $major + $minor / 100 + $patch / 10000;
	}

	/**
	 *  List of acronyms that should be upper-case.
	 *
	 * @var array
	 */
	private const ACRONYMS = array(
		'bb',
		'css',
		'fl',
		'flac',
		'gmt',
		'guid',
		'id',
		'ip',
		'm4a',
		'mp3',
		'ogg',
		'rss',
		'ssl',
		'ui',
		'uri',
		'url',
		'utc',
		'wav',
		'wp',
	);
}
