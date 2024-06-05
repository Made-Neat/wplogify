<?php
class WP_Logify_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('set-screen-option', [__CLASS__, 'set_screen_option'], 10, 3);
        add_action('wp_ajax_wp_logify_fetch_logs', [__CLASS__, 'fetch_logs']);
        add_action('admin_init', [__CLASS__, 'restrict_access']);
        add_action('admin_post_wp_logify_reset_logs', [__CLASS__, 'reset_logs']);
    }

    public static function fetch_logs() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp_logify_activities';
        $columns = [
            'id',
            'date_time',
            'user_id',
            'user_role',
            'source_ip',
            'event',
            'object',
            'editor'
        ];

        $limit = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $offset = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $order_by = isset($columns[$_POST['order'][0]['column']]) ? $columns[$_POST['order'][0]['column']] : 'date_time';
        $order = isset($_POST['order'][0]['dir']) && in_array($_POST['order'][0]['dir'], ['asc', 'desc']) ? $_POST['order'][0]['dir'] : 'desc';
        $search_value = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

        $sql = "SELECT * FROM $table_name";

        if (!empty($search_value)) {
            $sql .= $wpdb->prepare(
                " WHERE id LIKE %s OR date_time LIKE %s OR user_id LIKE %s OR user_role LIKE %s OR source_ip LIKE %s OR event LIKE %s OR object LIKE %s OR editor LIKE %s",
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%'
            );
        }

        $sql .= " ORDER BY $order_by $order LIMIT %d OFFSET %d";
        $sql = $wpdb->prepare($sql, $limit, $offset);
        $results = $wpdb->get_results($sql, ARRAY_A);

        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $filtered_records = $total_records;
        if (!empty($search_value)) {
            $filtered_sql = "SELECT COUNT(*) FROM $table_name WHERE id LIKE %s OR date_time LIKE %s OR user_id LIKE %s OR user_role LIKE %s OR source_ip LIKE %s OR event LIKE %s OR object LIKE %s OR editor LIKE %s";
            $filtered_sql = $wpdb->prepare(
                $filtered_sql,
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%',
                '%' . $wpdb->esc_like($search_value) . '%'
            );
            $filtered_records = $wpdb->get_var($filtered_sql);
        }

        $data = [];
        foreach ($results as $row) {
            if (!empty($row['id'])) {
                $date_time = new DateTime($row['date_time'], new DateTimeZone(wp_timezone_string()));
                $time_ago = human_time_diff($date_time->getTimestamp(), current_time('timestamp')) . ' ago';
                $time_format = $date_time->format(get_option('time_format'));
                $date_format = $date_time->format(get_option('date_format'));
                $row['date_time'] = "<div>$time_ago</div><div>$time_format</div><div>$date_format</div>";

                $user_profile_url = admin_url('user-edit.php?user_id=' . $row['user_id']);
                $username = esc_html(self::get_username($row['user_id']));
                $user_role = esc_html(ucwords($row['user_role']));
                $row['user'] = get_avatar($row['user_id'], 32) . ' <div class="wp-logify-user-info"><a href="' . $user_profile_url . '">' . $username . '</a><br><span class="wp-logify-user-role">' . $user_role . '</span></div>';
                $row['source_ip'] = '<a href="https://whatismyipaddress.com/ip/' . esc_html($row['source_ip']) . '" target="_blank">' . esc_html($row['source_ip']) . '</a>';
                $data[] = $row;
            }
        }

        wp_send_json([
            'draw' => intval($_POST['draw']),
            'recordsTotal' => intval($total_records),
            'recordsFiltered' => intval($filtered_records),
            'data' => $data,
        ]);
    }

    public static function add_admin_menu() {
        $access_roles = get_option('wp_logify_view_roles', ['administrator']);
        if (!self::current_user_has_access($access_roles)) {
            return;
        }

        $hook = add_menu_page('WP Logify', 'WP Logify', 'manage_options', 'wp-logify', [__CLASS__, 'display_log_page'], 'dashicons-list-view');
        add_submenu_page('wp-logify', 'Log', 'Log', 'manage_options', 'wp-logify', [__CLASS__, 'display_log_page']);
        add_submenu_page('wp-logify', 'Settings', 'Settings', 'manage_options', 'wp-logify-settings', [__CLASS__, 'display_settings_page']);
        add_action("load-$hook", [__CLASS__, 'add_screen_options']);
    }

    public static function register_settings() {
        register_setting('wp_logify_settings_group', 'wp_logify_api_key');
        register_setting('wp_logify_settings_group', 'wp_logify_delete_on_uninstall');
        register_setting('wp_logify_settings_group', 'wp_logify_roles_to_track', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'wp_logify_sanitize_roles'],
            'default' => ['administrator'],
        ]);
        register_setting('wp_logify_settings_group', 'wp_logify_view_roles', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'wp_logify_sanitize_roles'],
            'default' => ['administrator'],
        ]);
        register_setting('wp_logify_settings_group', 'wp_logify_keep_days', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 30,
        ]);
        register_setting('wp_logify_settings_group', 'wp_logify_wp_cron_tracking', [
            'type' => 'boolean',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);
    }

    public static function wp_logify_sanitize_roles($roles) {
        $valid_roles = array_keys(wp_roles()->roles);
        return array_filter($roles, function($role) use ($valid_roles) {
            return in_array($role, $valid_roles);
        });
    }

    public static function add_screen_options() {
        $option = 'per_page';
        $args = [
            'label' => __('Activities per page', 'wp-logify'),
            'default' => 20,
            'option' => 'activities_per_page'
        ];
        add_screen_option($option, $args);
    }

    public static function set_screen_option($status, $option, $value) {
        if ('activities_per_page' == $option) return $value;
        return $status;
    }

    public static function display_log_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_logify_activities';
        $per_page = (int) get_user_option('activities_per_page', get_current_user_id(), 'edit_wp-logify');
        if (!$per_page) $per_page = 20;
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $paged = isset($_GET['paged']) ? max(0, intval($_GET['paged']) - 1) : 0;
        $offset = $paged * $per_page;

        include plugin_dir_path(__FILE__) . '../templates/log-page.php';
    }

    public static function display_settings_page() {
        include plugin_dir_path(__FILE__) . '../templates/settings-page.php';
    }

    public static function add_dashboard_widget() {
        $access_roles = get_option('wp_logify_view_roles', ['administrator']);
        if (!self::current_user_has_access($access_roles)) {
            return;
        }

        wp_add_dashboard_widget('wp_logify_dashboard_widget', 'WP Logify - Recent Site Activities', [__CLASS__, 'display_dashboard_widget']);
    }

    public static function display_dashboard_widget() {
        include plugin_dir_path(__FILE__) . '../templates/dashboard-widget.php';
    }

    public static function enqueue_assets($hook) {
        if ($hook != 'toplevel_page_wp-logify' && $hook != 'wp-logify_page_wp-logify-settings') {
            return;
        }
        wp_enqueue_style('wp-logify-admin-css', plugin_dir_url(__FILE__) . '../assets/css/admin.css');
        wp_enqueue_script('wp-logify-admin-js', plugin_dir_url(__FILE__) . '../assets/js/admin.js', array('jquery'), null, true);

        // Enqueue DataTables CSS and JS from local files
        wp_enqueue_style('datatables-css', plugin_dir_url(__FILE__) . '../assets/css/jquery.dataTables.min.css');
        wp_enqueue_script('datatables-js', plugin_dir_url(__FILE__) . '../assets/js/jquery.dataTables.min.js', array('jquery'), null, true);

        // Localize script to provide ajaxurl
        wp_localize_script('wp-logify-admin-js', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    private static function format_datetime($datetime) {
        $timestamp = strtotime($datetime);
        $timezone = wp_timezone();
        $wp_datetime = new DateTime("now", $timezone);
        $wp_datetime->setTimestamp($timestamp);
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        return $wp_datetime->format("{$date_format} {$time_format}");
    }

    private static function get_username($user_id) {
        $user = get_userdata($user_id);
        return $user ? $user->display_name : 'Unknown';
    }

    public static function restrict_access() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'wp-logify') !== false) {
            $access_control = get_option('wp_logify_access_control', 'only_me');
            if ($access_control === 'only_me' && !self::is_plugin_installer()) {
                wp_redirect(admin_url());
                exit;
            } elseif ($access_control === 'user_roles' && !self::current_user_has_access(get_option('wp_logify_view_roles', ['administrator']))) {
                wp_redirect(admin_url());
                exit;
            }
        }
    }

    public static function reset_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_logify_activities';
        $wpdb->query("TRUNCATE TABLE $table_name");
        wp_redirect(admin_url('admin.php?page=wp-logify-settings&reset=success'));
        exit;
    }

    public static function hide_plugin_from_list($plugins) {
        $access_control = get_option('wp_logify_access_control', 'only_me');
        if ($access_control === 'only_me' && !self::is_plugin_installer()) {
            unset($plugins[plugin_basename(__FILE__)]);
        } elseif ($access_control === 'user_roles' && !self::current_user_has_access(get_option('wp_logify_view_roles', ['administrator']))) {
            unset($plugins[plugin_basename(__FILE__)]);
        }
        return $plugins;
    }

    private static function current_user_has_access($roles) {
        $user = wp_get_current_user();
        foreach ($roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        return false;
    }

    private static function is_plugin_installer() {
        $plugin_installer = get_option('wp_logify_plugin_installer');
        return $plugin_installer && get_current_user_id() == $plugin_installer;
    }
}

// Initialize the plugin
WP_Logify_Admin::init();
?>