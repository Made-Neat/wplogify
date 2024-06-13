<?php
/**
 * Class WP_Logify_DateTime
 *
 * Contains useful datetime functions.
 *
 * @package WP_Logify
 * @since 1.0.0
 * @category Class
 * @author Shaun Moss <shaun@astromultimedia.com>
 */
class WP_Logify_DateTime {

	/**
	 * The MySQL datetime format.
	 *
	 * @var string
	 */
	public const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Constructs a DateTime object from a given datetime string.
	 * This can throw a DateMalformedStringException if the string is not a valid datetime.
	 *
	 * @param string $datetime_string The datetime string to create a DateTime object from.
	 * @param string $tz_string The timezone string to use. Defaults to 'site'.
	 * @return DateTime The created DateTime object.
	 */
	public static function create_datetime( string $datetime_string, string $tz_string = 'site' ): \DateTime {
		$tz = $tz_string === 'site' ? wp_timezone() : new DateTimeZone( $tz_string );
		return new DateTime( $datetime_string, $tz );
	}

	/**
	 * Returns the current datetime as a DateTime object.
	 *
	 * @param string $tz_string The timezone string to use. Defaults to 'site'.
	 * @return DateTime The created DateTime object.
	 */
	public static function current_datetime( string $tz_string = 'site' ): DateTime {
		return self::create_datetime( 'now', $tz_string );
	}

	/**
	 * Formats a given DateTime using the date and time format from the site settings.
	 *
	 * @param DateTime|string $datetime The DateTime object to format or the datetime as a string (presumably in some other format).
	 * @param bool            $include_seconds Whether to include seconds in the time format if they aren't already.
	 * @return string The formatted datetime string.
	 */
	public static function format_datetime_site( DateTime|string $datetime, bool $include_seconds = false ): string {
		// Convert string to DateTime if necessary.
		if ( is_string( $datetime ) ) {
			$datetime = self::create_datetime( $datetime );
		}

		// Get the date and time formats from the site settings.
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		// Include seconds in the time format if requested, and not already present.
		if ( $include_seconds && strpos( $time_format, ':i:s' ) === false && strpos( $time_format, ':i' ) !== false ) {
			$time_format = str_replace( ':i', ':i:s', $time_format );
		}

		return $datetime->format( $time_format ) . ', ' . $datetime->format( $date_format );
	}

	/**
	 * Formats a given DateTime using the MySQL datetime format.
	 *
	 * @param DateTime|string $datetime The DateTime object to format or the datetime as a string (presumably in some other format).
	 * @return string The formatted datetime string.
	 */
	public static function format_datetime_mysql( DateTime|string $datetime ): string {
		// Convert string to DateTime if necessary.
		if ( is_string( $datetime ) ) {
			$datetime = self::create_datetime( $datetime );
		}

		return $datetime->format( self::MYSQL_DATETIME_FORMAT );
	}

	/**
	 * Subtracts a given number of hours from a DateTime.
	 *
	 * @param DateTime $datetime The DateTime to subtract hours from.
	 * @param int      $hours The number of hours to subtract.
	 * @return DateTime A new DateTime object equal to the original DateTime minus the specified number of hours.
	 */
	public static function subtract_hours( DateTime $datetime, int $hours ): DateTime {
		$datetime2 = clone $datetime;
		return $datetime2->sub( new DateInterval( 'PT' . $hours . 'H' ) );
	}
}
