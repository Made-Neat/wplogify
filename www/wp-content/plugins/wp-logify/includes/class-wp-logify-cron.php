<?php
class WP_Logify_Cron {
	public static function init() {
		add_action( 'wp_logify_cleanup', array( __CLASS__, 'cleanup_old_records' ) );
		if ( ! wp_next_scheduled( 'wp_logify_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_logify_cleanup' );
		}
	}

	public static function cleanup_old_records() {
		global $wpdb;

		// Check if we need to delete any old records.
		$limited = get_option( 'wp_logify_keep_period_limited', false );
		if ( ! $limited ) {
			return;
		}

		// Calculate the number of days to keep records.
		$quantity = get_option( 'wp_logify_keep_period_quantity', 1 );
		$units    = get_option( 'wp_logify_keep_period_units', 'month' );
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
		$table_name = $wpdb->prefix . 'wp_logify_activities';
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE date_time < NOW() - INTERVAL %d DAY", $days ) );
	}
}
