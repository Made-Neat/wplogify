<?php
/**
 * Contains the Log_Page class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use InvalidArgumentException;

/**
 * Class WP_Logify\Log_Page
 *
 * Contains methods for formatting event log entries for display in the admin area.
 */
class Log_Page {

	/**
	 * Display the log page.
	 *
	 * This function is responsible for displaying the log page in the WordPress admin area.
	 * It retrieves the necessary data from the database and includes the log page template.
	 */
	public static function display_log_page() {
		global $wpdb;
		$table_name = Logger::get_table_name();
		$per_page   = (int) get_user_option( 'activities_per_page', get_current_user_id() );
		if ( ! $per_page ) {
			$per_page = 20;
		}
		$total_items = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
		$paged       = isset( $_GET['paged'] ) ? max( 0, intval( $_GET['paged'] ) - 1 ) : 0;
		$offset      = $paged * $per_page;

		include plugin_dir_path( __FILE__ ) . '../templates/log-page.php';
	}

	/**
	 * Fetches logs from the database based on the provided search criteria.
	 */
	public static function fetch_logs() {
		global $wpdb;

		// Get table names.
		$events_table_name = Logger::get_table_name();
		$user_table_name   = $wpdb->prefix . 'users';

		// These should match the columns in admin.js.
		$columns = array(
			'ID',
			'date_time',
			'user',
			'user_ip',
			'event_type',
			'object',
		);

		// Extract parameters from the request.

		// Number of results per page.
		if ( isset( $_POST['length'] ) ) {
			$limit = intval( $_POST['length'] );
		}
		// Default to 10.
		if ( ! isset( $limit ) || $limit < 1 ) {
			$limit = 10;
		}

		// Offset.
		if ( isset( $_POST['start'] ) ) {
			$offset = intval( $_POST['start'] );
		}
		// Default to 0.
		if ( ! isset( $start ) || $start < 0 ) {
			$offset = 0;
		}

		// Order by column. Default to date_time.
		$order_col = isset( $columns[ $_POST['order'][0]['column'] ] ) ? wp_unslash( $columns[ $_POST['order'][0]['column'] ] ) : 'date_time';

		// Order by direction.
		if ( isset( $_POST['order'][0]['dir'] ) ) {
			$order_dir = strtoupper( wp_unslash( $_POST['order'][0]['dir'] ) );
		}
		// Default to DESC.
		if ( ! isset( $order_dir ) || ! in_array( $order_dir, array( 'ASC', 'DESC' ), true ) ) {
			$order_dir = 'DESC';
		}

		// Search value.
		$search_value = isset( $_POST['search']['value'] ) ? wp_unslash( $_POST['search']['value'] ) : '';

		// Start constructing the SQL statement to get records from the database.
		$sql  = '
            SELECT
                e.ID AS event_id,
                e.date_time,
                e.user_id,
                e.user_role,
                e.user_ip,
                e.user_location,
                e.user_agent,
                e.event_type,
                e.object_type,
                e.object_id,
                e.object_name,
                e.details,
                e.changes,
                u.user_login,
                u.user_nicename,
                u.user_email,
                u.user_status,
                u.display_name
            FROM %i e LEFT JOIN %i u ON e.user_id = u.ID';
		$args = array( $events_table_name, $user_table_name );

		// Filter by the search value, if provided.
		if ( ! empty( $search_value ) ) {
			$like_value = '%' . $wpdb->esc_like( $search_value ) . '%';
			$sql       .= '
                WHERE date_time LIKE %s
                   OR user_role LIKE %s
                   OR user_ip LIKE %s
                   OR event_type LIKE %s
                   OR object_type LIKE %s
                   OR details LIKE %s
                   OR changes LIKE %s
                   OR display_name LIKE %s';
			array_push(
				$args,
				$like_value,
				$like_value,
				$like_value,
				$like_value,
				$like_value,
				$like_value,
				$like_value,
				$like_value,
			);
		}

		// Add the order by parameters.
		switch ( $order_col ) {
			case 'ID':
				$order_by = "event_id $order_dir";
				break;

			case 'user':
				$order_by = "display_name $order_dir";
				break;

			case 'object':
				$order_by = "object_type $order_dir, object_id $order_dir";
				break;

			default:
				$order_by = "$order_col $order_dir";
				break;
		}
		$sql .= " ORDER BY $order_by LIMIT %d OFFSET %d";
		array_push( $args, $limit, $offset );

		// Prepare the statement.
		$sql = $wpdb->prepare( $sql, $args );

		// Get the requested records.
		$results = $wpdb->get_results( $sql );

		// Get the number of results.
		$num_filtered_records = count( $results );

		// Get the total number of records in the table.
		$num_total_records = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $events_table_name ) );

		// Construct the data array to return to the client.
		$data = array();
		foreach ( $results as $row ) {
			if ( ! empty( $row->event_id ) ) {
				// Create a new event.
				$event = array( 'ID' => $row->event_id );

				// Date and time of the event.
				$date_time          = DateTimes::create_datetime( $row->date_time );
				$formatted_datetime = DateTimes::format_datetime_site( $date_time );
				$time_ago           = human_time_diff( $date_time->getTimestamp() ) . ' ago';
				$event['date_time'] = "<div>$formatted_datetime ($time_ago)</div>";

				// User details.
				$user_profile_link = Users::get_user_profile_link( $row->user_id );
				$user_role         = esc_html( ucwords( $row->user_role ) );
				$event['user']     = get_avatar( $row->user_id, 32 ) . " <div class='wp-logify-user-info'>$user_profile_link<br><span class='wp-logify-user-role'>$user_role</span></div>";

				// Source IP.
				$event['user_ip'] = '<a href="https://whatismyipaddress.com/ip/' . esc_html( $row->user_ip ) . '" target="_blank">' . esc_html( $row->user_ip ) . '</a>';

				// Event type.
				$event['event_type'] = $row->event_type;

				// Get the object link.
				$event['object'] = self::get_object_link( $row );

				// Format the details.
				$event['details'] = self::format_details( $row );

				$data[] = $event;
			}
		}

		wp_send_json(
			array(
				'draw'            => intval( $_POST['draw'] ),
				'recordsTotal'    => intval( $num_total_records ),
				'recordsFiltered' => intval( $num_filtered_records ),
				'data'            => $data,
			)
		);
	}

	/**
	 * Formats user details for a log entry.
	 *
	 * @param object $row The data object selected from the database, which includes event details and user details.
	 * @return string The formatted user details as an HTML table.
	 */
	public static function format_user_details( object $row ): string {
		// Handle the case where the user ID is empty. Should never happen.
		if ( empty( $row->user_id ) ) {
			return '';
		}

		// Get the last login datetime.
		$last_login_datetime        = Users::get_last_login_datetime( $row->user_id );
		$last_login_datetime_string = $last_login_datetime !== null ? DateTimes::format_datetime_site( $last_login_datetime, true ) : 'Unknown';

		// Get the last active datetime.
		$last_active_datetime        = Users::get_last_active_datetime( $row->user_id );
		$last_active_datetime_string = $last_active_datetime !== null ? DateTimes::format_datetime_site( $last_active_datetime, true ) : 'Unknown';

		// User location.
		$user_location = empty( $row->user_location ) ? 'Unknown' : esc_html( $row->user_location );

		// User agent.
		$user_agent = empty( $row->user_agent ) ? 'Unknown' : esc_html( $row->user_agent );

		// Construct the HTML.
		$html  = "<div class='wp-logify-user-details wp-logify-details-section'>\n";
		$html .= "<h4>User Details</h4>\n";
		$html .= "<table class='wp-logify-user-details-table'>\n";
		$html .= '<tr><th>User</th><td>' . Users::get_user_profile_link( $row->user_id ) . "</td></tr>\n";
		$html .= "<tr><th>Email</th><td><a href='mailto:{$row->user_email}'>{$row->user_email}</a></td></tr>\n";
		$html .= '<tr><th>Role</th><td>' . esc_html( ucwords( $row->user_role ) ) . "</td></tr>\n";
		$html .= "<tr><th>ID</th><td>$row->user_id</td></tr>";
		$html .= '<tr><th>IP address</th><td>' . ( $row->user_ip ?? 'Unknown' ) . "</td></tr>\n";
		$html .= "<tr><th>Last login</th><td>$last_login_datetime_string</td></tr>\n";
		$html .= "<tr><th>Last active</th><td>$last_active_datetime_string</td></tr>\n";
		$html .= "<tr><th>Location</th><td>$user_location</td></tr>\n";
		$html .= "<tr><th>User agent</th><td>$user_agent</td></tr>\n";
		$html .= "</table>\n";
		$html .= "</div>\n";

		return $html;
	}

	/**
	 * Formats the event details of a log entry.
	 *
	 * @param object $row The data object selected from the database, which includes event details and user details.
	 * @return string The formatted event details as an HTML table.
	 */
	public static function format_event_details( object $row ): string {
		// Handle the null case.
		if ( empty( $row->details ) ) {
			return '';
		}

		// Decode JSON.
		$details = json_decode( $row->details, true );
		if ( empty( $details ) ) {
			return '';
		}

		// Convert JSON string to a small table of key-value pairs.
		$html  = "<div class='wp-logify-event-details wp-logify-details-section'>\n";
		$html .= "<h4>Event Details</h4>\n";
		$html .= "<table class='wp-logify-event-details-table'>\n";
		foreach ( $details as $key => $value ) {
			$html .= "<tr><th>$key</th><td>$value</td></tr>";
		}
		$html .= "</table>\n";
		$html .= "</div>\n";
		return $html;
	}

	/**
	 * Format change details.
	 *
	 * @param object $row The data object selected from the database.
	 * @return string The formatted change details as an HTML table.
	 */
	public static function format_change_details( object $row ): string {
		// Handle the null case.
		if ( empty( $row->changes ) ) {
			return '';
		}

		// Decode JSON.
		$changes = json_decode( $row->changes, true );
		if ( empty( $changes ) ) {
			return '';
		}

		// Convert JSON string to a table showing the changes.
		$html  = "<div class='wp-logify-change-details wp-logify-details-section'>\n";
		$html .= "<h4>Change Details</h4>\n";
		$html .= "<table class='wp-logify-change-details-table'>\n";
		$html .= "<tr><th></th><th>Before</th><th>After</th></tr>\n";
		foreach ( $changes as $key => $value ) {
			$readable_key = self::make_key_readable( $key, array( 'wp', $row->object_type ) );

			$html .= '<tr>';
			$html .= "<th>$readable_key</th>";

			if ( $key === 'user_pass' ) {
				// Special handling for passwords.
				$html .= '<td>(hidden)</td><td>(hidden)</td>';
			} elseif ( is_scalar( $value ) ) {
				$html .= "<td colspan='2'>$value</td>";
			} elseif ( is_array( $value ) && count( $value ) === 2 ) {
				$html .= "<td>{$value[0]}</td><td>{$value[1]}</td>";
			}

			$html .= "</tr>\n";
		}
		$html .= "</table>\n";
		$html .= "</div>\n";

		return $html;
	}

	/**
	 * Make a key readable.
	 *
	 * This function takes a key and makes it more readable by converting it to title case and
	 * replacing underscores with spaces.
	 *
	 * @param string $key The key to make readable.
	 * @param ?array $prefixes_to_ignore An array of prefixes to ignore when making the key readable. Examples: 'wp', 'user', 'post'.
	 * @return string The readable key.
	 */
	public static function make_key_readable( string $key, ?array $prefixes_to_ignore = null ): string {
		// Special cases.
		switch ( $key ) {
			case 'user_pass':
				return 'Password';

			case 'show_admin_bar_front':
				return 'Show toolbar';

			case 'user registered':
				return 'Registered (UTC)';

			case 'post_date':
				return 'Created';

			case 'post_date_gmt':
				return 'Created (UTC)';

			case 'post_modified':
				return 'Last modified';

			case 'post_modified_gmt':
				return 'Last modified (UTC)';
		}

		// Split the key into words.
		$words = explode( '_', $key );

		// Remove any ignored prefix.
		if ( ! empty( $prefixes_to_ignore ) ) {
			while ( true ) {
				if ( count( $words ) > 1 && in_array( $words[0], $prefixes_to_ignore, true ) ) {
					$words = array_slice( $words, 1 );
				} else {
					break;
				}
			}
		}

		return ucfirst( implode( ' ', $words ) );
	}

	/**
	 * Formats the details of a log entry.
	 *
	 * @param object $row The data object selected from the database, which includes event details and user details.
	 * @return string The formatted details as HTML.
	 */
	public static function format_details( object $row ) {
		$html  = "<div class='wp-logify-details'>\n";
		$html .= self::format_user_details( $row );
		$html .= self::format_event_details( $row );
		$html .= self::format_change_details( $row );
		$html .= "</div>\n";
		return $html;
	}

	/**
	 * Retrieves the link to an object based on its type and ID.
	 *
	 * NB: The ID will be an integer (as a string) for posts and users, and a string for themes and plugins.
	 *
	 * @param object $event The event object from the database.
	 * @return string The link to the object.
	 * @throws InvalidArgumentException If the object type is invalid or the object ID is null.
	 */
	public static function get_object_link( object $event ) {
		// Handle the null case.
		if ( empty( $event->object_type ) || empty( $event->object_id ) ) {
			return '';
		}

		// Check for valid object ID.
		if ( $event->object_id === null ) {
			throw new InvalidArgumentException( 'Object ID cannot be null . ' );
		}

		// Construct string to use in place of a link to the object if it's been deleted.
		$deleted_string = empty( $event->object_name )
			? ( ucfirst( $event->object_type ) . ' ' . $event->object_id )
			: $event->object_name;

		// Generate the link based on the object type.
		switch ( $event->object_type ) {
			case 'post':
				// Attempt to load the post.
				$post = get_post( $event->object_id );

				// Check if it was deleted.
				if ( ! $post ) {
					return $deleted_string;
				}

				// The desired URL will vary according to the post status.
				switch ( $post->post_status ) {
					case 'publish':
						// View the post.
						$url = "/?p={$post->ID}";
						break;

					case 'trash':
						// List trashed posts.
						$url = '/wp-admin/edit.php?post_status=trash&post_type=post';
						break;

					default:
						// post_status should be draft or auto-draft. Go to the edit page.
						$url = "/wp-admin/post.php?post={$post->ID}&action=edit";
						break;
				}

				return "<a href='$url'>{$post->post_title}</a>";

			case 'user':
				// Attempt to load the user.
				$user = get_userdata( $event->object_id );

				// Check if the user was deleted.
				if ( ! $user ) {
					return $deleted_string;
				}

				// Return the user profile link.
				return Users::get_user_profile_link( $event->object_id );

			case 'theme':
				// Attempt to load the theme.
				$theme = wp_get_theme( $event->object_id );

				// Check if the theme was deleted.
				if ( ! $theme->exists() ) {
					return $deleted_string;
				}

				// Return a link to the theme.
				return "<a href='/wp-admin/theme-editor.php?theme={$theme->stylesheet}'>{$theme->name}</a>";

			case 'plugin':
				// Attempt to load the plugin.
				$plugins = get_plugins();

				// Check if the plugin was deleted.
				if ( ! array_key_exists( $event->object_id, $plugins ) ) {
					return $deleted_string;
				}

				// Link to the plugins page.
				return "<a href='/wp-admin/plugins.php'>{$plugins[$event->object_id]['Name']}</a>";
		}

		// If the object type is invalid, throw an exception.
		throw new InvalidArgumentException( "Invalid object type: $event->object_type" );
	}
}
