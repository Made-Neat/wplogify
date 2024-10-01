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
$sql_one_hour         = $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE when_happened >= %s', $table_name, $one_hour_ago );
$activities_last_hour = (int) $wpdb->get_var( $sql_one_hour );

// Fetch the total activities for the last 24 hours.
$twenty_four_hours_ago    = DateTimes::format_datetime_mysql( DateTimes::subtract_hours( $current_datetime, 24 ) );
$sql_24_hour              = $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE when_happened >= %s', $table_name, $twenty_four_hours_ago );
$activities_last_24_hours = (int) $wpdb->get_var( $sql_24_hour );

// Fetch the last 10 activities.
$sql_fetch_10 = $wpdb->prepare( 'SELECT * FROM %i ORDER BY when_happened DESC LIMIT 10', $table_name );
$recordset    = $wpdb->get_results( $sql_fetch_10, ARRAY_A );
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
			<?php if ( $recordset ) : ?>
				<?php
				foreach ( $recordset as $record ) :
					$event = Event_Repository::record_to_object( $record );
					?>
					<tr class="logify-wp-event-row logify-wp-object-type-<?php echo $event->object_type; ?>" data-event-id="<?php echo $event->id; ?>">
						<td><?php echo DateTimes::format_datetime_site( $event->when_happened, true, '<br>', true ); ?></td>
						<td><?php echo User_Utility::get_tag( $event->user_id, $event->user_name ); ?></td>
						<td><?php echo esc_html( esc_html( $event->event_type ) ); ?></td>
						<!-- <td><?php // echo $event->get_object_tag(); ?></td> -->
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="4">No activities found.</td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Links -->
	<ul class="logify-wp-widget-links">
		<li class="logify-wp-widget-link">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=logify-wp' ) ); ?>">View all site activity</a>
		</li>
		<li class="logify-wp-separator">|</li>
		<li class="logify-wp-widget-link">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=logify-wp-settings' ) ); ?>">Settings</a>
		</li>
	</ul>

</div>
