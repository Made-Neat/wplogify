<?php
/**
 * Contains the DateTimes class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;
use DateTimeZone;
use DateInterval;

/**
 * Class WP_Logify\DateTimes
 *
 * Contains useful date and time-related functions.
 */
class DateTimes {

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
	public static function create_datetime( string $datetime_string, string $tz_string = 'site' ): DateTime {
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

	/**
	 * Retrieves the duration of a period as a string.
	 * The period is defined by DateTimes indicating the start and end of the period.
	 * The duration is rounded up to the nearest minute. Seconds aren't shown.
	 *
	 * @param DateTime $start The start of the period.
	 * @param DateTime $end The end of the period.
	 * @return string The duration of the period as a string.
	 */
	public static function get_duration_string( DateTime $start, DateTime $end ): string {
		// Compute the duration in seconds.
		$seconds = $end->getTimestamp() - $start->getTimestamp();

		// Special handling for 0 seconds.
		if ( $seconds === 0 ) {
			return '0 minutes';
		}

		// Get the minutes and hours. Round up to the nearest minute.
		$minutes  = (int) ceil( $seconds / 60 );
		$hours    = (int) floor( $minutes / 60 );
		$minutes %= 60;

		// Construct the duration string.
		$duration_string = '';
		if ( $hours > 0 ) {
			$duration_string .= "$hours hour" . ( $hours === 1 ? '' : 's' );
		}
		if ( $minutes > 0 ) {
			if ( $duration_string !== '' ) {
				$duration_string .= ', ';
			}
			$duration_string .= "$minutes minute" . ( $minutes === 1 ? '' : 's' );
		}
		return $duration_string;
	}
}
