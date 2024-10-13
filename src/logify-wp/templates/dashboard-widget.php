<?php
/**
 * Dashboard widget template.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

global $wpdb;

// Get the logify_wp_events table name.
$table_name = Event_Repository::get_table_name();

// Create a DateTime object from the current local time.
$current_datetime = DateTimes::current_datetime();

// Fetch the total activities for the last hour.
$one_hour_ago         = DateTimes::format_datetime_mysql( DateTimes::subtract_hours( $current_datetime, 1 ) );
$activities_last_hour = (int) $wpdb->get_var(
	$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE when_happened >= %s', $table_name, $one_hour_ago )
);

// Fetch the total activities for the last 24 hours.
$twenty_four_hours_ago    = DateTimes::format_datetime_mysql( DateTimes::subtract_hours( $current_datetime, 24 ) );
$activities_last_24_hours = (int) $wpdb->get_var(
	$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE when_happened >= %s', $table_name, $twenty_four_hours_ago )
);

// Fetch the last 10 activities.
$recordset = $wpdb->get_results(
	$wpdb->prepare( 'SELECT * FROM %i ORDER BY when_happened DESC LIMIT 10', $table_name ),
	ARRAY_A
);
?>

<div class="logify-wp-dashboard-widget">

	<!-- Display the total activities -->
	<div class="logify-wp-stats">
		<div class="logify-wp-stats-box">
			<div class="logify-wp-stats-number"><?php echo esc_html( $activities_last_hour ); ?></div>
			<div class="logify-wp-stats-title">Events in the last hour</div>
		</div>
		<div class="logify-wp-stats-box">
			<div class="logify-wp-stats-number"><?php echo esc_html( $activities_last_24_hours ); ?></div>
			<div class="logify-wp-stats-title">Events in the last 24 hours</div>
		</div>
	</div>

	<!-- Display the last 10 activities in a table -->
	<table id="logify-wp-dashboard-table">
		<thead>
			<tr>
				<th>Date & Time</th>
				<th>User</th>
				<th>Event</th>
				<!-- <th>Object</th> -->
			</tr>
		</thead>
		<tbody>
			<?php
			if ( $recordset ) {
				foreach ( $recordset as $record ) {
					$event = Event_Repository::record_to_object( $record );
					echo '<tr class="logify-wp-event-row" data-event-id="' . esc_attr( $event->id ) . '">' . PHP_EOL;
					echo '<td>' . wp_kses_post( DateTimes::format_datetime_site( $event->when_happened, true, '<br>', true ) ) . '</td>' . PHP_EOL;
					echo '<td>' . wp_kses_post( User_Utility::get_tag( $event->user_id, $event->user_name ) ) . '</td>' . PHP_EOL;
					echo '<td><span class="' . esc_attr( "logify-wp-event-type logify-wp-object-type-$event->object_type" ) . '">' . esc_html( $event->event_type ) . '</span></td>' . PHP_EOL;
					echo '</tr>' . PHP_EOL;
				}
			} else {
				?>
				<tr><td colspan="4">No activities found.</td></tr>
				<?php
			}
			?>
		</tbody>
	</table>

	<!-- Links -->
	<ul class="logify-wp-widget-links">
		<li class="logify-wp-widget-link">
			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=logify-wp' ) ); ?>">View all site activity</a>
		</li>
		<li class="logify-wp-separator">|</li>
		<li class="logify-wp-widget-link">
			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=logify-wp-settings' ) ); ?>">Settings</a>
		</li>
	</ul>

</div>
