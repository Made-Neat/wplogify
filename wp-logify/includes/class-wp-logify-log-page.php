<?php
/**
 * Formats log entries for display in the admin area.
 */
class WP_Logify_Log_Page {

	/**
	 * Display the log page.
	 *
	 * This function is responsible for displaying the log page in the WordPress admin area.
	 * It retrieves the necessary data from the database and includes the log page template.
	 *
	 * @since 1.0.0
	 */
	public static function display_log_page() {
		global $wpdb;
		$table_name = WP_Logify_Logger::get_table_name();
		$per_page   = (int) get_user_option( 'activities_per_page', get_current_user_id() );
		if ( ! $per_page ) {
			$per_page = 20;
		}
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		$paged       = isset( $_GET['paged'] ) ? max( 0, intval( $_GET['paged'] ) - 1 ) : 0;
		$offset      = $paged * $per_page;

		include plugin_dir_path( __FILE__ ) . '../templates/log-page.php';
	}

	/**
	 * Fetches logs from the database based on the provided search criteria.
	 *
	 * @return void
	 */
	public static function fetch_logs() {
		global $wpdb;

		// Get table names.
		$events_table_name = WP_Logify_Logger::get_table_name();
		$user_table_name   = $wpdb->prefix . 'users';

		// These should match the columns in admin.js.
		$columns = array(
			'id',
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
		$order_col = isset( $columns[ $_POST['order'][0]['column'] ] ) ? $columns[ $_POST['order'][0]['column'] ] : 'date_time';

		// Order by direction.
		if ( isset( $_POST['order'][0]['dir'] ) ) {
			$order_dir = strtoupper( $_POST['order'][0]['dir'] );
		}
		// Default to DESC.
		if ( ! isset( $order_dir ) || ! in_array( $order_dir, array( 'ASC', 'DESC' ), true ) ) {
			$order_dir = 'DESC';
		}

		// Search value.
		$search_value = isset( $_POST['search']['value'] ) ? wp_unslash( $_POST['search']['value'] ) : '';

		// Start constructing the SQL statement to get records from the database.
		$sql  = 'SELECT * FROM %i e LEFT JOIN %i u ON e.user_id = u.ID';
		$args = array( $events_table_name, $user_table_name );

		// Filter by the search value, if provided.
		if ( ! empty( $search_value ) ) {
			$like_value = '%' . $wpdb->esc_like( $search_value ) . '%';
			$sql       .= ' WHERE date_time LIKE %s OR user_role LIKE %s OR user_ip LIKE %s OR event_type LIKE %s OR object_type LIKE %s OR details LIKE %s';
			array_push(
				$args,
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
			case 'id':
				$order_by = "e.ID $order_dir";
				break;

			case 'user':
				$order_by = "u.display_name $order_dir";
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

		// debug_log( $sql );

		// Get the requested records.
		$results = $wpdb->get_results( $sql );

		// Get the number of results.
		$num_filtered_records = count( $results );

		// Get the total number of records in the table.
		$num_total_records = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $events_table_name ) );

		// Construct the data array to return to the client.
		$data = array();
		foreach ( $results as $row ) {
			if ( ! empty( $row->id ) ) {
				// Date and time.
				$date_time          = WP_Logify_DateTime::create_datetime( $row->date_time );
				$formatted_datetime = WP_Logify_DateTime::format_datetime_site( $date_time );
				$time_ago           = human_time_diff( $date_time->getTimestamp() ) . ' ago';
				$row->date_time     = "<div>$formatted_datetime ($time_ago)</div>";

				// User details.
				$user_profile_url = site_url( '/?author=' . $row->user_id );
				$username         = WP_Logify_Users::get_username( $row );
				$user_role        = esc_html( ucwords( $row->user_role ) );
				$row->user        = get_avatar( $row->user_id, 32 )
					. ' <div class="wp-logify-user-info"><a href="' . $user_profile_url . '">'
					. $username . '</a><br><span class="wp-logify-user-role">' . $user_role
					. '</span></div>';

				// Source IP.
				$row->user_ip = '<a href="https://whatismyipaddress.com/ip/'
					. esc_html( $row->user_ip ) . '" target="_blank">'
					. esc_html( $row->user_ip ) . '</a>';

				// Get the object link.
				$row->object = self::get_object_link( $row );

				// Format the data.
				$row->details = self::format_details( $row );

				$data[] = $row;
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

		// Convert JSON string to a small table of key-value pairs.
		$details = json_decode( $row->details );
		$html    = "<table class='wp-logify-event-details-table wp-logify-details-table'>";
		foreach ( $details as $key => $value ) {
			$html .= "<tr><th>$key</th><td>$value</td></tr>";
		}
		$html .= '</table>';
		return $html;
	}

	/**
	 * Formats user details for a log entry.
	 *
	 * @param object $row The data object selected from the database, which includes event details and user details.
	 * @return string The formatted user details as an HTML table.
	 */
	public static function format_user_details( object $row ): string {
		global $wpdb;

		$html = "<table class='wp-logify-user-details-table wp-logify-details-table'>";

		// Core user details.
		$html .= '<tr><th>User</th><td>' . WP_Logify_Users::get_user_profile_link( $row->user_id ) . '</td></tr>';
		$html .= "<tr><th>Email</th><td><a href='mailto:{$row->user_email}'>{$row->user_email}</a></td></tr>";
		$html .= '<tr><th>Role</th><td>' . esc_html( ucwords( $row->user_role ) ) . '</td></tr>';
		$html .= "<tr><th>ID</th><td>$row->user_id</td></tr>";
		$html .= '<tr><th>IP address</th><td>' . ( $row->user_ip ?? 'Unknown' ) . '</td></tr>';

		// Default values.
		$last_login_datetime_string = 'Unknown';
		$user_location              = 'Unknown';
		$user_agent                 = 'Unknown';

		// Look for the last login event.
		$table_name       = WP_Logify_Logger::get_table_name();
		$sql              = "SELECT * FROM %i WHERE user_id = %d AND event_type = 'Login' ORDER BY date_time DESC LIMIT 1";
		$last_login_event = $wpdb->get_row( $wpdb->prepare( $sql, $table_name, $row->user_id ) );
		if ( $last_login_event !== null ) {
			// User's last login datetime.
			if ( ! empty( $last_login_event->date_time ) ) {
				$last_login_datetime_string = WP_Logify_DateTime::format_datetime_site( $last_login_event->date_time );
			}

			// Decode the event details.
			if ( ! empty( $last_login_event->details ) ) {
				$details = json_decode( $last_login_event->details );

				// User location.
				if ( ! $details['Location'] ) {
					$user_location = esc_html( $details['Location'] );
				}

				// User agent.
				if ( ! $details['User agent'] ) {
					$user_agent = esc_html( $details['User agent'] );
				}
			}
		}

		// Additional details.
		$html .= "<tr><th>Last login</th><td>$last_login_datetime_string</td></tr>";
		$html .= "<tr><th>Location</th><td>$user_location</td></tr>";
		$html .= "<tr><th>User agent</th><td>$user_agent</td></tr>";

		$html .= '</table>';
		return $html;
	}

	/**
	 * Formats the details of a log entry.
	 *
	 * @param object $row The data object selected from the database, which includes event details and user details.
	 * @return string The formatted details as HTML.
	 */
	public static function format_details( object $row ) {
		$html = "<div class='wp-logify-details'>";

		// Event details.
		if ( ! empty( $row->details ) ) {
			$html .= "
                <div class='wp-logify-event-details wp-logify-details-section'>
                    <h4>Event Details</h4>
                    " . self::format_event_details( $row ) . '
                </div>';
		}

		// User details.
		if ( ! empty( $row->user_id ) ) {
			$html .= "
                <div class='wp-logify-user-details wp-logify-details-section'>
                    <h4>User Details</h4>
                    " . self::format_user_details( $row ) . '
                </div>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Retrieves the link to an object based on its type and ID.
	 *
	 * NB: The ID will be an integer (as a string) for posts and users, and a string for themes and plugins.
	 *
	 * @param object $event The event object from the database.
	 */
	public static function get_object_link( object $event ) {
		// Handle the null case.
		if ( empty( $event->object_type ) || empty( $event->object_id ) ) {
			return '';
		}

		// Check for valid object type.
		if ( ! in_array( $event->object_type, WP_Logify_Logger::VALID_OBJECT_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid object type: $event->object_type" );
		}

		// Check for valid object ID or name.
		if ( $event->object_id === null ) {
			throw new InvalidArgumentException( 'Object ID or name cannot be null.' );
		}

		// Generate the link based on the object type.
		switch ( $event->object_type ) {
			case 'post':
				$post = get_post( $event->object_id );

				// Get the URL based on the post status.
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
				return WP_Logify_Users::get_user_profile_link( $event->object_id );

			case 'theme':
				$theme = wp_get_theme( $event->object_id );
				return "<a href='/wp-admin/theme-editor.php?theme={$theme->stylesheet}'>{$theme->name}</a>";

			case 'plugin':
				$plugin = get_plugin_data( $event->object_id );
				return $plugin['Name'];
		}
	}
}
