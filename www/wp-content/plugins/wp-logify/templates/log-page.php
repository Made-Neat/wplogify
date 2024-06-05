<div class="wrap">
    <h1>WP Logify - Log</h1>

    <h2>Site Activities</h2>

    <!-- Search box placement -->
    <input type="text" id="wp-logify-search-box" placeholder="Search activities..." style="margin-bottom: 1em; width: 100%; padding: 8px; box-sizing: border-box;">

    <table id="wp-logify-activity-log" class="widefat fixed table-wp-logify" cellspacing="0">
        <thead>
            <tr>
                <th class="column-id">ID</th>
                <th>Date</th>
                <th>User</th>
                <th>Role</th>
                <th>Source IP</th>
                <th>Event</th>
                <th>Object</th>
            </tr>
        </thead>
        <tbody>
            <!-- Data will be loaded via AJAX -->
        </tbody>
    </table>

    <?php
    // Pagination logic
    $total_pages = ceil(intval($total_items) / intval($per_page));
    if ($total_pages > 1) {
        $current_page = max(1, intval($paged) + 1);
        $page_links = paginate_links([
            'base' => esc_url(add_query_arg('paged', '%#%')),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page,
        ]);

        echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
    }

    function display_log_details($log_id) {
        global $wpdb;
        $log_id = intval($log_id);
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}logify_logs WHERE id = %d", $log_id));

        if ($log) {
            $details = maybe_unserialize($log->details);
            ?>
            <div class="log-details">
                <h3>Log Details</h3>
                <p><strong>User:</strong> <?php echo esc_html(get_userdata($log->user_id)->user_login); ?></p>
                <p><strong>Action:</strong> <?php echo esc_html($log->action); ?></p>
                <p><strong>Date and Time:</strong> <?php echo esc_html($log->created_at); ?></p>
                <?php if (isset($details['post_id'])): ?>
                    <p><strong>Post Title:</strong> <?php echo esc_html($details['post_title']); ?></p>
                    <p><strong>Post Type:</strong> <?php echo esc_html($details['post_type']); ?></p>
                <?php elseif (isset($details['term_id'])): ?>
                    <p><strong>Term Name:</strong> <?php echo esc_html($details['term_name']); ?></p>
                    <p><strong>Taxonomy:</strong> <?php echo esc_html($details['taxonomy']); ?></p>
                <?php elseif (isset($details['option_name'])): ?>
                    <p><strong>Option Name:</strong> <?php echo esc_html($details['option_name']); ?></p>
                    <p><strong>Old Value:</strong> <?php echo esc_html($details['old_value']); ?></p>
                    <p><strong>New Value:</strong> <?php echo esc_html($details['new_value']); ?></p>
                <?php else: ?>
                    <p><strong>Details:</strong> <?php echo esc_html(print_r($details, true)); ?></p>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    if (isset($_GET['log_id'])) {
        display_log_details(intval($_GET['log_id']));
    }
    ?>
</div>
