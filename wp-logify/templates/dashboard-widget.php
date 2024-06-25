<?php
/**
 * Dashboard widget template.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

global $wpdb;

// Get the wp_logify_events table name.
$table_name = Logger::get_table_name();

// Create a DateTime object from the current local time.
$current_datetime = DateTimes::current_datetime();

// Fetch the total activities for the last hour.
$one_hour_ago         = DateTimes::format_datetime_mysql( DateTimes::subtract_hours( $current_datetime, 1 ) );
$activities_last_hour = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE date_time > %s", $one_hour_ago ) );

// Fetch the total activities for the last 24 hours.
$twenty_four_hours_ago    = DateTimes::format_datetime_mysql( DateTimes::subtract_hours( $current_datetime, 24 ) );
$activities_last_24_hours = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE date_time > %s", $twenty_four_hours_ago ) );

// Fetch the last 10 activities.
$results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY date_time DESC LIMIT 10" );
?>

<div class="wp-logify-dashboard-widget">

	<!-- Display the total activities -->
	<div class="wp-logify-stats">
		<div class="wp-logify-stats-box">
			<div class="wp-logify-stats-number"><?php echo esc_html( $activities_last_hour ); ?></div>
			<div class="wp-logify-stats-title">Events in the last hour</div>
		</div>
		<div class="wp-logify-stats-box">
			<div class="wp-logify-stats-number"><?php echo esc_html( $activities_last_24_hours ); ?></div>
			<div class="wp-logify-stats-title">Events in the last 24 hours</div>
		</div>
	</div>

	<!-- Display the last 10 activities in a table -->
	<table class="wp-logify-activity-table">
		<thead>
			<tr>
				<th>Date & Time</th>
				<th>User</th>
				<th>Event</th>
				<th>Object</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( $results ) : ?>
				<?php foreach ( $results as $event ) : ?>
					<tr>
						<td><?php echo esc_html( DateTimes::format_datetime_site( $event->date_time ) ); ?></td>
						<td><?php echo Users::get_user_profile_link( $event->user_id ); ?></td>
						<td><?php echo esc_html( $event->event_type ); ?></td>
						<td><?php echo Log_Page::get_object_link( $event ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="4">No activities found.</td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Links -->
	<ul class="wp-logify-widget-links">
		<li class="wp-logify-widget-link">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-logify' ) ); ?>">View all site activity</a>
		</li>
		<li class="wp-logify-separator">|</li>
		<li class="wp-logify-widget-link">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-logify-settings' ) ); ?>">Settings</a>
		</li>  	
	</ul>
	
</div>

<?php
function wp_logify_dashboard_widget() {
	// Check current user has access.
	$access_roles = get_option( 'wp_logify_view_roles', array( 'administrator' ) );
	if ( ! current_user_has_access( $access_roles ) ) {
		return;
	}

	wp_add_dashboard_widget( 'wp_logify_dashboard_widget', 'WP Logify - Recent Site Activity', 'wp_logify_display_dashboard_widget' );
}

function wp_logify_display_dashboard_widget() {
	include plugin_dir_path( __FILE__ ) . '../templates/dashboard-widget.php';
}

function current_user_has_access( $roles ) {
	$user = wp_get_current_user();
	foreach ( $roles as $role ) {
		if ( in_array( $role, $user->roles ) ) {
			return true;
		}
	}
	return false;
}

add_action( 'wp_dashboard_setup', 'wp_logify_dashboard_widget' );
