<?php
global $wpdb;
$table_name = $wpdb->prefix . 'wp_logify_activities';

// Fetch the total activities for the last hour and last 24 hours
$current_time = current_time('mysql');
$one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour', strtotime($current_time)));
$twenty_four_hours_ago = date('Y-m-d H:i:s', strtotime('-24 hours', strtotime($current_time)));

$activities_last_hour = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE date_time > %s", $one_hour_ago));
$activities_last_24_hours = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE date_time > %s", $twenty_four_hours_ago));

// Fetch the last 10 activities
$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wp_logify_activities ORDER BY date_time DESC LIMIT 10");

echo '<div class="wp-logify-dashboard-widget">';

// Display the total activities
echo '<div class="wp-logify-stats">';
echo '<div class="wp-logify-stats-box">';
echo '<div class="wp-logify-stats-header">Last Hour</div>';
echo '<div class="wp-logify-stats-number">' . esc_html($activities_last_hour) . '</div>';
echo '</div>';
echo '<div class="wp-logify-stats-box">';
echo '<div class="wp-logify-stats-header">Last 24 Hours</div>';
echo '<div class="wp-logify-stats-number">' . esc_html($activities_last_24_hours) . '</div>';
echo '</div>';
echo '</div>';

// Add settings link
echo '<div class="wp-logify-settings-link">';
echo '<a href="' . esc_url(admin_url('admin.php?page=wp-logify-settings')) . '"><span class="dashicons dashicons-admin-generic"></span></a>';
echo '</div>';

// Display the last 10 activities in a table
echo '<table class="wp-logify-activity-table">';
echo '<thead>';
echo '<tr>';
echo '<th>Date & Time</th>';
echo '<th>User</th>';
echo '<th>Event</th>';
echo '<th>Object</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';
if ($results) {
    foreach ($results as $activity) {
        $user_profile_url = admin_url('user-edit.php?user_id=' . $activity->user_id);
        $username = esc_html(WP_Logify_Admin::get_username($activity->user_id));
        $user_info = get_avatar($activity->user_id, 32) . ' <div class="wp-logify-user-info"><a href="' . esc_url($user_profile_url) . '">' . $username . '</a><br><span class="wp-logify-user-role">' . esc_html(ucwords($activity->user_role)) . '</span></div>';

        echo '<tr>';
        echo '<td>' . esc_html(WP_Logify_Admin::format_datetime($activity->date_time)) . '</td>';
        echo '<td>' . $user_info . '</td>';
        echo '<td>' . esc_html($activity->event) . '</td>';
        echo '<td>' . esc_html($activity->object) . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="4">No activities found.</td></tr>';
}
echo '</tbody>';
echo '</table>';

// Add the "View all Site Activities" link
echo '<a href="' . admin_url('admin.php?page=wp-logify') . '">View all Site Activities</a>';
echo '</div>';
?>

<?php
function wp_logify_dashboard_widget() {
    $access_roles = get_option('wp_logify_view_roles', ['administrator']);
    if (!current_user_has_access($access_roles)) {
        return;
    }
    
    wp_add_dashboard_widget('wp_logify_dashboard_widget', 'WP Logify - Recent Site Activities', 'wp_logify_display_dashboard_widget');
}

function wp_logify_display_dashboard_widget() {
    include plugin_dir_path(__FILE__) . '../templates/dashboard-widget.php';
}

function current_user_has_access($roles) {
    $user = wp_get_current_user();
    foreach ($roles as $role) {
        if (in_array($role, $user->roles)) {
            return true;
        }
    }
    return false;
}

add_action('wp_dashboard_setup', 'wp_logify_dashboard_widget');
?>
