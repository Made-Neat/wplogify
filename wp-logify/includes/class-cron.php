<?php
/**
 * Contains the Cron class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

/**
 * Class WP_Logify\Cron
 *
 * Encapsulates all cron-related methods.
 */
class Cron {
	/**
	 * Initializes the class.
	 */
	public static function init() {
		add_action( 'wp_logify_cleanup', array( __CLASS__, 'cleanup_old_records' ) );
		if ( ! wp_next_scheduled( 'wp_logify_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_logify_cleanup' );
		}
	}

	/**
	 * Cleanup old records from the events table.
	 */
	public static function cleanup_old_records() {
		global $wpdb;

		// Check if we need to delete any old records.
		if ( Settings::get_keep_forever() ) {
			return;
		}

		// Calculate the number of days to keep records.
		$quantity = Settings::get_keep_period_quantity();
		$units    = Settings::get_keep_period_units();
		switch ( $units ) {
			case 'day':
				$days = $quantity;
				break;

			case 'week':
				$days = $quantity * 7;
				break;

			case 'month':
				$days = $quantity * 30.436875;
				break;

			case 'year':
				$days = $quantity * 365.2425;
				break;
		}
		$days = absint( ceil( $days ) );

		// Delete old records.
		$table_name = Logger::get_table_name();
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE date_time < NOW() - INTERVAL %d DAY", $days ) );
	}
}
