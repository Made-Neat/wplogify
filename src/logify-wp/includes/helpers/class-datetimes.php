<?php
/**
 * Contains the DateTimes class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DateInterval;
use DateTime;
use DateTimeZone;
use Throwable;

/**
 * Class Logify_WP\DateTimes
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
	 * The MySQL datetime format for a 'zero' date.
	 *
	 * @var string
	 */
	public const MYSQL_DATETIME_ZERO = '0000-00-00 00:00:00';

	/**
	 * The number of days in a week.
	 *
	 * @var int
	 */
	public const DAYS_PER_WEEK = 7;

	/**
	 * The average number of days in a calendar month.
	 *
	 * @var float
	 */
	public const DAYS_PER_MONTH = 30.436875;

	/**
	 * The average number of days in a calendar year.
	 *
	 * @var float
	 */
	public const DAYS_PER_YEAR = 365.2425;

	/**
	 * Constructs a DateTime object from a given datetime string.
	 *
	 * Returns null if the datetime string is null, zero, or otherwise invalid.
	 *
	 * @param string $datetime_string The datetime string to create a DateTime object from.
	 * @param string $tz_string The timezone string to use. Defaults to the site time zone.
	 * @return ?DateTime The created DateTime object or null if the string is invalid.
	 */
	public static function create_datetime( ?string $datetime_string, string $tz_string = 'site' ): ?DateTime {
		// Handle the null and zero cases.
		if ( $datetime_string === null || $datetime_string === self::MYSQL_DATETIME_ZERO ) {
			return null;
		}

		// Convert the provided time zone argument to a DateTimeZone object.
		$tz = $tz_string === 'site' ? wp_timezone() : new DateTimeZone( $tz_string );

		// Try to create the DateTime object.
		try {
			// This will throw an exception if the string is not a valid datetime.
			return new DateTime( $datetime_string, $tz );
		} catch ( Throwable ) {
			return null;
		}
	}

	/**
	 * Returns the current datetime as a DateTime object.
	 *
	 * @param string $tz_string The timezone string to use. Defaults to the site time zone.
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
	 * @param string          $separator The separator to use between the time and date parts.
	 * @param bool            $non_breaking_spaces Whether to use non-breaking spaces in the formatted date and time (not the separator, which is used as provided).
	 * @return string The formatted datetime string.
	 */
	public static function format_datetime_site(
		DateTime|string $datetime,
		bool $include_seconds = true,
		string $separator = ', ',
		bool $non_breaking_spaces = false
	): string {
		// Convert string to DateTime if necessary.
		if ( is_string( $datetime ) ) {
			$datetime = self::create_datetime( $datetime );
		}

		// Set the time zone to the site's time zone.
		self::set_timezone( $datetime, 'site' );

		// Get the date and time formats from the site settings.
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		// Include seconds in the time format if requested, and not already present.
		if ( $include_seconds && strpos( $time_format, ':i:s' ) === false && strpos( $time_format, ':i' ) !== false ) {
			$time_format = str_replace( ':i', ':i:s', $time_format );
		}

		// Format the date and time.
		$formatted_date = $datetime->format( $date_format );
		$formatted_time = $datetime->format( $time_format );

		// Replace spaces with non-breaking spaces if requested.
		if ( $non_breaking_spaces ) {
			$formatted_date = str_replace( ' ', '&nbsp;', $formatted_date );
			$formatted_time = str_replace( ' ', '&nbsp;', $formatted_time );
		}

		// Assemble the result string.
		$datetime_string = $formatted_time . $separator . $formatted_date;

		return $datetime_string;
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
	 * Adds a given number of days to a DateTime.
	 *
	 * @param DateTime $datetime The DateTime to add days to.
	 * @param int      $days The number of days to add.
	 * @return DateTime A new DateTime object equal to the original DateTime plus the specified number of days.
	 */
	public static function add_days( DateTime $datetime, int $days ): DateTime {
		$datetime2 = clone $datetime;
		return $datetime2->add( new DateInterval( 'P' . $days . 'D' ) );
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
	 * Return a string showing how long ago the given DateTime was.
	 *
	 * @param DateTime $datetime The DateTime to compare with now.
	 * @return string The string showing how long ago the DateTime was.
	 */
	public static function get_ago_string( DateTime $datetime ): string {
		return human_time_diff( $datetime->getTimestamp() ) . ' ago';
	}

	/**
	 * Set the time zone of a DateTime object.
	 *
	 * @param DateTime $datetime The DateTime object to set the time zone of.
	 * @param string   $tz_string The time zone string to set the DateTime to.
	 */
	public static function set_timezone( DateTime $datetime, string $tz_string ) {
		$tz = $tz_string === 'site' ? wp_timezone() : new DateTimeZone( $tz_string );
		$datetime->setTimezone( $tz );
	}

	/**
	 * Convert a PHP date format to jQuery datepicker format.
	 *
	 * @param string $php_format The PHP date format.
	 * @return string The corresponding jQuery datepicker format.
	 */
	public static function convert_php_date_format_to_js( string $php_format ): string {

		// Mapping from PHP date format to jQuery datepicker format.
		$replacements = array(
			// Day.
			'd' => 'dd',   // Day of the month, 2 digits with leading zeros (01-31)
			'j' => 'd',    // Day of the month without leading zeros (1-31)
			'D' => 'D',    // Day of the week, short name (Mon-Sun)
			'l' => 'DD',   // Day of the week, full name (Monday-Sunday)

			// Month.
			'm' => 'mm',   // Month, 2 digits with leading zeros (01-12)
			'n' => 'm',    // Month without leading zeros (1-12)
			'M' => 'M',    // Short month name (Jan-Dec)
			'F' => 'MM',   // Full month name (January-December)

			// Year.
			'Y' => 'yy',   // Year, 4 digits (e.g., 2024)
			'y' => 'y',    // Year, 2 digits (e.g., 24)
		);

		// Perform the replacement.
		return strtr( $php_format, $replacements );
	}
}
