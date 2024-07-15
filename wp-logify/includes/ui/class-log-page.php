<?php
/**
 * Contains the Log_Page class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use DateTime;
use Exception;

/**
 * Class WP_Logify\Log_Page
 *
 * Contains methods for formatting event log entries for display in the admin area.
 */
class Log_Page {

	/**
	 * Initialise the log page.
	 */
	public static function init() {
		add_action( 'wp_ajax_wp_logify_fetch_logs', array( __CLASS__, 'fetch_logs' ) );
	}

	/**
	 * Display the log page.
	 */
	public static function display_log_page() {
		include WP_LOGIFY_PLUGIN_DIR . 'templates/log-page.php';
	}

	/**
	 * Get the user's preference for the number of items to show per page.
	 *
	 * @return int The number of items to show per page.
	 */
	public static function get_items_per_page(): int {
		$page_length = (int) get_user_option( 'wp_logify_events_per_page', get_current_user_id() );
		return $page_length ? $page_length : 20;
	}

	/**
	 * Fetches logs from the database based on the provided search criteria.
	 */
	public static function fetch_logs() {
		global $wpdb;

		// Get table names.
		$events_table_name = Event_Repository::get_table_name();
		$user_table_name   = $wpdb->prefix . 'users';

		// These should match the columns in admin.js.
		$columns = array(
			'event_id',
			'when_happened',
			'display_name',
			'user_ip',
			'event_type',
			'object_name',
		);

		// -----------------------------------------------------------------------------------------
		// Extract parameters from the request.

		// Get the number of items per page. We're using the screen options rather than the length
		// argument in the request.
		$page_length = self::get_items_per_page();

		// Get the start item index. Default to 0.
		$start = isset( $_POST['start'] ) ? (int) $_POST['start'] : 0;
		if ( $start < 0 ) {
			$start = 0;
		}

		// Get the order-by column. Default to when_happened.
		$order_by_column = 'when_happened';
		if ( isset( $_POST['order'][0]['column'] ) ) {
			$column_number = (int) $_POST['order'][0]['column'];
			if ( array_key_exists( $column_number, $columns ) ) {
				$order_by_column = $columns[ $column_number ];
			}
		}

		// Get the order-by direction. Check it's valid. Default to DESC.
		$order_by_direction = isset( $_POST['order'][0]['dir'] ) ? strtoupper( $_POST['order'][0]['dir'] ) : 'DESC';
		if ( ! in_array( $order_by_direction, array( 'ASC', 'DESC' ), true ) ) {
			$order_by_direction = 'DESC';
		}

		// Get the search value.
		$search_value = isset( $_POST['search']['value'] ) ? wp_unslash( $_POST['search']['value'] ) : '';

		// -----------------------------------------------------------------------------------------
		// Get the total number of events in the database.
		$total_sql         = $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $events_table_name );
		$num_total_records = $wpdb->get_var( $total_sql );

		// -----------------------------------------------------------------------------------------
		// Get the number of filtered records.

		// Select clause.
		$select_count = 'SELECT COUNT(*) FROM %i e LEFT JOIN %i u ON e.user_id = u.ID';

		// Where clause.
		if ( $search_value === '' ) {
			$where      = '';
			$where_args = array();
		} else {
			$like_value = '%' . $wpdb->esc_like( $search_value ) . '%';
			$where      =
				'WHERE when_happened LIKE %s
                    OR user_role LIKE %s
                    OR user_ip LIKE %s
                    OR user_location LIKE %s
                    OR user_agent LIKE %s
                    OR event_type LIKE %s
                    OR object_type LIKE %s
                    OR object_name LIKE %s
                    OR user_login LIKE %s
                    OR user_email LIKE %s
                    OR display_name LIKE %s';
			$where_args = array(
				$like_value,
				$like_value,
				$like_value,
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

		// Construct and run the SQL statement.
		$select_args          = array( $events_table_name, $user_table_name );
		$args                 = array_merge( $select_args, $where_args );
		$filtered_sql         = $wpdb->prepare( "$select_count $where", $args );
		$num_filtered_records = $wpdb->get_var( $filtered_sql );

		// -----------------------------------------------------------------------------------------
		// Get the requested records.

		// Select clause.
		$select = 'SELECT event_id FROM %i e LEFT JOIN %i u ON e.user_id = u.ID';

		// Order-by clause.
		$order_by      = "ORDER BY %i $order_by_direction";
		$order_by_args = array( $order_by_column );

		// Limit clause.
		$limit      = 'LIMIT %d OFFSET %d';
		$limit_args = array( $page_length, $start );

		// Construct and run the SQL statement.
		$args        = array_merge( $select_args, $where_args, $order_by_args, $limit_args );
		$results_sql = $wpdb->prepare( "$select $where $order_by $limit", $args );
		$rows        = $wpdb->get_results( $results_sql, ARRAY_A );

		// -----------------------------------------------------------------------------------------
		// Construct the data array to return to the client.
		$data = array();
		foreach ( $rows as $row ) {
			// Construct the Event object.
			$event = Event_Repository::load( $row['event_id'] );

			// Create a new data item.
			$item             = array();
			$item['event_id'] = $event->event_id;

			// Date and time of the event.
			$formatted_datetime    = DateTimes::format_datetime_site( $event->when_happened );
			$time_ago              = DateTimes::get_ago_string( $event->when_happened );
			$item['when_happened'] = "<div>$formatted_datetime ($time_ago)</div>";

			// User details.
			$user_tag             = Users::get_tag( $event->user_id, $event->user_name );
			$user_role            = esc_html( ucwords( $event->user_role ) );
			$item['display_name'] = get_avatar( $event->user_id, 32 ) . " <div class='wp-logify-user-info'>$user_tag<br><span class='wp-logify-user-role'>$user_role</span></div>";

			// Source IP.
			$item['user_ip'] = '<a href="https://whatismyipaddress.com/ip/' . esc_html( $event->user_ip ) . '" target="_blank">' . esc_html( $event->user_ip ) . '</a>';

			// Event type.
			$item['event_type'] = $event->event_type;

			// Get the HTML for the object name tag.
			$object_reference    = new Object_Reference( $event->object_type, $event->object_id, $event->object_name );
			$item['object_name'] = $object_reference->get_tag();

			// Format the details.
			$item['details'] = self::format_details( $event );

			// Add the item to the data array.
			$data[] = $item;
		}

		wp_send_json(
			array(
				'draw'            => intval( $_POST['draw'] ),
				'recordsTotal'    => $num_total_records,
				'recordsFiltered' => $num_filtered_records,
				'data'            => $data,
			)
		);
	}

	/**
	 * Formats user details for a log entry.
	 *
	 * @param Event $event The event.
	 * @return string The formatted user details as an HTML table.
	 */
	public static function format_user_details( Event $event ): string {
		// Handle the case where the user ID is empty. Should never happen.
		if ( empty( $event->user_id ) ) {
			return '';
		}

		// User tag.
		$user_tag = Users::get_tag( $event->user_id, $event->user_name );

		// User email.
		$user       = Users::get_user( $event->user_id );
		$user_email = empty( $user->user_email ) ? 'Unknown' : esc_html( $user->user_email );

		// Role.
		$user_role = esc_html( ucwords( $event->user_role ) );

		// Get the last login datetime.
		$last_login_datetime        = Users::get_last_login_datetime( $event->user_id );
		$last_login_datetime_string = $last_login_datetime !== null ? DateTimes::format_datetime_site( $last_login_datetime ) : 'Unknown';

		// Get the last active datetime.
		$last_active_datetime        = Users::get_last_active_datetime( $event->user_id );
		$last_active_datetime_string = $last_active_datetime !== null ? DateTimes::format_datetime_site( $last_active_datetime ) : 'Unknown';

		// User location.
		$user_location = empty( $event->user_location ) ? 'Unknown' : esc_html( $event->user_location );

		// User agent.
		$user_agent = empty( $event->user_agent ) ? 'Unknown' : esc_html( $event->user_agent );

		// Construct the HTML.
		$html  = "<div class='wp-logify-user-details wp-logify-details-section'>\n";
		$html .= "<h4>User Details</h4>\n";
		$html .= "<table class='wp-logify-user-details-table'>\n";
		$html .= "<tr><th>User</th><td>$user_tag</td></tr>\n";
		$html .= "<tr><th>Email</th><td><a href='mailto:$user_email'>$user_email</a></td></tr>\n";
		$html .= "<tr><th>Role</th><td>$user_role</td></tr>\n";
		$html .= "<tr><th>ID</th><td>$event->user_id</td></tr>";
		$html .= '<tr><th>IP address</th><td>' . ( $event->user_ip ?? 'Unknown' ) . "</td></tr>\n";
		$html .= "<tr><th>Last login</th><td>$last_login_datetime_string</td></tr>\n";
		$html .= "<tr><th>Last active</th><td>$last_active_datetime_string</td></tr>\n";
		$html .= "<tr><th>Location</th><td>$user_location</td></tr>\n";
		$html .= "<tr><th>User agent</th><td>$user_agent</td></tr>\n";
		$html .= "</table>\n";
		$html .= "</div>\n";

		return $html;
	}

	/**
	 * Formats the event metadata of a log entry.
	 *
	 * @param Event $event The event.
	 * @return string The formatted event metadata as an HTML table.
	 */
	public static function format_event_metadata( Event $event ): string {
		// Handle the null case.
		if ( empty( $event->event_meta ) ) {
			return '';
		}

		// Convert event metadata to a table of key-value pairs.
		$html  = "<div class='wp-logify-event-meta wp-logify-details-section'>\n";
		$html .= "<h4>Event Details</h4>\n";
		$html .= "<table class='wp-logify-event-meta-table'>\n";
		foreach ( $event->event_meta as $meta_key => $meta_value ) {
			$readable_key = self::make_key_readable( $meta_key );
			$html        .= "<tr><th>$readable_key</th><td>" . self::value_to_html( $meta_key, $meta_value ) . '</td></tr>';
		}
		$html .= "</table>\n";
		$html .= "</div>\n";
		return $html;
	}

	/**
	 * Format object properties.
	 *
	 * @param Event $event The event.
	 * @return string The object properties formatted as an HTML table.
	 */
	public static function format_object_properties( Event $event ): string {
		// Handle the null case.
		if ( empty( $event->properties ) ) {
			return '';
		}

		// Check which columns to show.
		$show_old_values = false;
		$show_new_values = false;
		foreach ( $event->properties as $property ) {
			// Check if we want to show the 'Before' column.
			if ( $property->old_value !== null && $property->old_value !== '' ) {
				$show_old_values = true;
			}

			// Check if we want to show the 'After' column.
			if ( $property->new_value !== null && $property->new_value !== '' ) {
				$show_new_values = true;
			}

			// If we're going to show both, no need to keep checking.
			if ( $show_old_values && $show_new_values ) {
				break;
			}
		}

		// Convert JSON string to a table showing the changes.
		$html              = "<div class='wp-logify-change-details wp-logify-details-section'>\n";
		$object_type_title = $event->object_type === 'user' ? 'Account' : ucfirst( $event->object_type );
		$html             .= "<h4>$object_type_title Details</h4>\n";

		// Start table.
		$html .= "<table class='wp-logify-change-details-table'>\n";

		// Header row.
		$html .= '<tr><th>Property</th>';
		$html .= $show_old_values && $show_new_values ? '<th>Before</th><th>After</th>' : '<th>Value</th>';
		$html .= "</tr>\n";

		// Property rows.
		foreach ( $event->properties as $property ) {
			if ( $property->property_key === 'session_tokens' ) {
				continue;
			}

			// Start row.
			$html .= '<tr>';

			// Property name.
			$html .= '<th>' . self::make_key_readable( $property->property_key ) . '</th>';

			// Old value.
			if ( $show_old_values ) {
				$html .= '<td>' . self::value_to_html( $property->property_key, $property->old_value ) . '</td>';
			}

			// New value.
			if ( $show_new_values ) {
				$html .= '<td>' . self::value_to_html( $property->property_key, $property->new_value ) . '</td>';
			}

			// End row.
			$html .= "</tr>\n";
		}

		// End table.
		$html .= "</table>\n";

		$html .= "</div>\n";

		return $html;
	}

	/**
	 * Convert a value to a string for display.
	 *
	 * @param mixed $value The value to convert.
	 * @return string The value as a string.
	 */
	public static function value_to_string( mixed $value ) {
		if ( $value === null ) {
			return '';
		} elseif ( is_string( $value ) ) {
			return $value;
		} elseif ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		} elseif ( is_scalar( $value ) ) {
			return (string) $value;
		} elseif ( $value instanceof DateTime ) {
			return DateTimes::format_datetime_site( $value );
		} elseif ( $value instanceof Object_Reference ) {
			return $value->get_tag();
		} else {
			// Value is an array or another type of object.
			return wp_json_encode( $value );
		}
	}

	/**
	 * Convert a value to an HTML string for display.
	 *
	 * @param string $key The key of the value.
	 * @param mixed  $value The value to convert.
	 * @return string The value as an HTML string.
	 */
	public static function value_to_html( string $key, mixed $value ): string {
		// Hide passwords.
		if ( $key === 'user_pass' ) {
			return empty( $value ) ? '' : '(hidden)';
		}

		// Expand arrays into mini-tables.
		if ( is_array( $value ) ) {

			// Start the table.
			$html = "<table>\n";

			// Check if the array is a list.
			$is_list = array_is_list( $value );

			foreach ( $value as $key2 => $value2 ) {
				// Start the table row.
				$html .= '<tr>';

				// If it's not a list, show the key.
				if ( ! $is_list ) {
					$html .= '<th>' . self::make_key_readable( $key2 ) . '</th>';
				}

				// Show the value.
				$html .= '<td>' . self::value_to_html( $key2, $value2 ) . '</td>';

				// End the table row.
				$html .= '</tr>';
			}

			// End the table.
			$html .= "</table>\n";

			return $html;
		}

		// Convert to plain text string.
		return self::value_to_string( $value );
	}

	/**
	 * Make a key readable.
	 *
	 * This function takes a key and makes it more readable by converting it to title case and
	 * replacing underscores with spaces.
	 *
	 * @param string $key The key to make readable.
	 * @return string The readable key.
	 */
	public static function make_key_readable( string $key ): string {
		// Handle some special, known cases.
		switch ( $key ) {
			case 'user_pass':
				return 'Password';

			case 'user_nicename':
				return 'Nice name';

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
		$words = array_filter( preg_split( '/[-_]+/', $key ) );

		// Convert acronyms to uppercase.
		foreach ( $words as $i => $word ) {
			if ( in_array( $word, array( 'guid', 'id', 'ip', 'rss', 'ssl', 'url', 'wp' ) ) ) {
				$words[ $i ] = strtoupper( $word );
			}
		}

		// Convert to readable string.
		return ucfirst( implode( ' ', $words ) );
	}

	/**
	 * Formats the details of a log entry.
	 *
	 * @param Event $event The event.
	 * @return string The formatted details as HTML.
	 */
	public static function format_details( Event $event ): string {
		$html  = "<div class='wp-logify-details'>\n";
		$html .= self::format_user_details( $event );
		$html .= self::format_event_metadata( $event );
		$html .= self::format_object_properties( $event );
		$html .= "</div>\n";
		return $html;
	}
}
