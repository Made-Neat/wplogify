<?php
/**
 * Contains the DateTimes class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;

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
	 * @param string  $datetime_string The datetime string to create a DateTime object from.
	 * @param ?string $tz_string       The timezone string to use. Defaults to the site time zone.
	 * @return DateTime The created DateTime object.
	 */
	public static function create_datetime( string $datetime_string, ?string $tz_string = null ): DateTime {
		// Convert the provided time zone argument to a suitable DateTimeZone.
		$tz = $tz_string === null ? wp_timezone() : new DateTimeZone( $tz_string );

		return new DateTime( $datetime_string, $tz );
	}

	/**
	 * Returns the current datetime as a DateTime object.
	 *
	 * @param ?string $tz_string The timezone string to use. Defaults to the site time zone.
	 * @return DateTime The created DateTime object.
	 */
	public static function current_datetime( ?string $tz_string = null ): DateTime {
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
	 * Formats a given DateTime using the MySQL datetime format.
	 *
	 * @param DateTime|string $datetime The DateTime object to format or the datetime as a string (presumably in some other format).
	 * @return string The formatted datetime string.
	 */
	public static function format_datetime_database( DateTime|string $datetime ): string {
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

	/**
	 * Convert the datetime to a form suitable for encoding as JSON.
	 *
	 * @param DateTime $datetime The DateTime to convert.
	 * @return array The array representation of the DateTime.
	 */
	public static function encode( DateTime $datetime ): array {
		return array( 'DateTime' => (array) $datetime );
	}

	/**
	 * Check if the provided object is an encoded DateTime.
	 *
	 * @param object    $simple_object The value to check.
	 * @param ?DateTime $datetime The DateTime object to populate if valid.
	 * @return bool    If the JSON contains a valid date-time string.
	 */
	public static function is_encoded_object( object $simple_object, ?DateTime &$datetime ): bool {
		// Convert the object to an array.
		$ary = (array) $simple_object;

		// Check it looks right.
		if ( count( $ary ) !== 1 || empty( $ary['DateTime'] ) || ! is_object( $ary['DateTime'] ) ) {
			return false;
		}

		// Try to convert the inner object to a DateTime.
		try {
			// The DateTime constructor will throw if the string does not represent a valid DateTime.
			$datetime = DateTime::__set_state( (array) $ary['DateTime'] );
			return true;
		} catch ( Exception $ex ) {
			debug( 'Invalid DateTime', $ary['DateTime'], $ex->getMessage() );
		}

		return false;
	}
}
