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
			'object_type',
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
		$order_by_columns = array( 'when_happened' );
		if ( isset( $_POST['order'][0]['column'] ) ) {
			$column_number = (int) $_POST['order'][0]['column'];
			if ( array_key_exists( $column_number, $columns ) ) {
				$order_by_columns = array( $columns[ $column_number ] );
			}
		}
		$order_by_columns[] = 'event_id';

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
		$num_total_records = (int) $wpdb->get_var( $total_sql );

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
		$num_filtered_records = (int) $wpdb->get_var( $filtered_sql );

		// -----------------------------------------------------------------------------------------
		// Get the requested records.

		// Select clause.
		$select = 'SELECT event_id FROM %i e LEFT JOIN %i u ON e.user_id = u.ID';

		// Order-by clause.
		$order_by_columns = array_map( fn( $order_by_col ) => "$order_by_col $order_by_direction", $order_by_columns );
		$order_by         = 'ORDER BY ' . implode( ', ', $order_by_columns );

		// Limit clause.
		$limit      = 'LIMIT %d OFFSET %d';
		$limit_args = array( $page_length, $start );

		// Construct and run the SQL statement.
		$args        = array_merge( $select_args, $where_args, $limit_args );
		$results_sql = $wpdb->prepare( "$select $where $order_by $limit", $args );
		$recordset   = $wpdb->get_results( $results_sql, ARRAY_A );

		// -----------------------------------------------------------------------------------------
		// Construct the data array to return to the client.
		$data = array();
		foreach ( $recordset as $record ) {
			// Construct the Event object.
			$event = Event_Repository::load( $record['event_id'] );

			// Create a new data item.
			$item             = array();
			$item['event_id'] = $event->id;

			// Date and time of the event.
			$formatted_datetime    = DateTimes::format_datetime_site( $event->when_happened );
			$time_ago              = DateTimes::get_ago_string( $event->when_happened );
			$item['when_happened'] = "<div>$formatted_datetime ($time_ago)</div>";

			// User details.
			$user_tag             = User_Utility::get_tag( $event->user_id, $event->user_name );
			$user_role            = esc_html( ucwords( $event->user_role ) );
			$item['display_name'] = get_avatar( $event->user_id, 32 ) . " <div class='wp-logify-user-info'>$user_tag<br><span class='wp-logify-user-role'>$user_role</span></div>";

			// Source IP.
			$item['user_ip'] = '<a href="https://whatismyipaddress.com/ip/' . esc_html( $event->user_ip ) . '" target="_blank">' . esc_html( $event->user_ip ) . '</a>';

			// Event type.
			$item['event_type'] = $event->event_type;

			// Get the HTML for the object name.
			$item['object_name'] = $event->get_object_tag();

			// Include the object type.
			$item['object_type'] = $event->object_type;

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

		// Construct the HTML.
		$html  = "<div class='wp-logify-details-section wp-logify-user-details-section'>\n";
		$html .= "<h4>User Details</h4>\n";
		$html .= "<table class='wp-logify-details-table wp-logify-user-details-table'>\n";

		// User tag.
		$user_tag = User_Utility::get_tag( $event->user_id, $event->user_name );
		$html    .= "<tr><th>User</th><td>$user_tag</td></tr>\n";

		// Role.
		$user_role = esc_html( ucwords( $event->user_role ) );
		$html     .= "<tr><th>Role</th><td>$user_role</td></tr>\n";

		// User ID.
		$html .= "<tr><th>ID</th><td>$event->user_id</td></tr>";

		// IP address.
		$html .= '<tr><th>IP address</th><td>' . ( $event->user_ip ?? 'Unknown' ) . "</td></tr>\n";

		// User location.
		$user_location = empty( $event->user_location ) ? 'Unknown' : esc_html( $event->user_location );
		$html         .= "<tr><th>Location</th><td>$user_location</td></tr>\n";

		// User agent.
		$user_agent = empty( $event->user_agent ) ? 'Unknown' : esc_html( $event->user_agent );
		$html      .= "<tr><th>User agent</th><td>$user_agent</td></tr>\n";

		// If the user has been deleted, we won't have all their details. Although, we could
		// try looking up the log entry for when the user was deleted, and see if we can
		// get their details from there.

		// Get some extra details if the user has not been deleted.
		if ( User_Utility::exists( $event->user_id ) ) {
			// Load the user.
			$user = User_Utility::load( $event->user_id );

			// User email.
			$user_email = empty( $user->user_email ) ? 'Unknown' : esc_html( $user->user_email );
			$html      .= '<tr><th>Email</th><td>' . User_Utility::get_email_link( $user_email ) . "</a></td></tr>\n";

			// Get the last login datetime.
			$last_login_datetime        = User_Utility::get_last_login_datetime( $event->user_id );
			$last_login_datetime_string = $last_login_datetime !== null ? DateTimes::format_datetime_site( $last_login_datetime ) : 'Unknown';
			$html                      .= "<tr><th>Last login</th><td>$last_login_datetime_string</td></tr>\n";

			// Get the last active datetime.
			$last_active_datetime        = User_Utility::get_last_active_datetime( $event->user_id );
			$last_active_datetime_string = $last_active_datetime !== null ? DateTimes::format_datetime_site( $last_active_datetime ) : 'Unknown';
			$html                       .= "<tr><th>Last active</th><td>$last_active_datetime_string</td></tr>\n";
		}

		$html .= "</table>\n";
		$html .= "</div>\n";

		return $html;
	}

	/**
	 * Formats the eventmetas of a log entry.
	 *
	 * @param Event $event The event.
	 * @return string The formatted eventmetas as an HTML table.
	 */
	public static function format_eventmetas( Event $event ): string {
		// Handle the null case.
		if ( empty( $event->eventmetas ) ) {
			return '';
		}

		// Convert eventmetas to a table of key-value pairs.
		$html  = "<div class='wp-logify-details-section wp-logify-eventmeta-section'>\n";
		$html .= "<h4>Event Details</h4>\n";
		$html .= "<table class='wp-logify-details-table wp-logify-eventmeta-table'>\n";
		foreach ( $event->eventmetas as $eventmeta ) {
			$readable_key = Types::make_key_readable( $eventmeta->meta_key );
			$html        .= "<tr><th>$readable_key</th><td>" . Types::value_to_html( $eventmeta->meta_key, $eventmeta->meta_value ) . '</td></tr>';
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
	public static function format_properties( Event $event ): string {
		// Handle the null case.
		if ( empty( $event->properties ) ) {
			return '';
		}

		// Check which columns to show.
		$show_new_vals = false;
		foreach ( $event->properties as $prop ) {
			// Check if we want to show the 'After' column.
			if ( $prop->new_val !== null ) {
				$show_new_vals = true;
				break;
			}
		}

		// Convert JSON string to a table showing the changes.
		$html = "<div class='wp-logify-details-section wp-logify-properties-section'>\n";

		// Get the title given the object type.
		switch ( $event->object_type ) {
			case 'user':
				$object_type_title = 'Account';
				break;

			case 'post':
				// Get the post type. It might be in the properties.
				$post_type = $event->properties['post_type']->val ?? null;
				// If not, we can get it from the post.
				if ( ! $post_type ) {
					$post_type = $event->get_object()->post_type;
				}
				$object_type_title = Post_Utility::get_post_type_singular_name( $post_type );
				break;

			case 'term':
				// Get the taxonomy. It might be in the properties.
				$taxonomy = $event->properties['taxonomy']->val ?? null;
				// If not, we can get it from the term.
				if ( ! $taxonomy ) {
					$taxonomy = $event->get_object()->taxonomy;
				}
				$object_type_title = Term_Utility::get_taxonomy_singular_name( $taxonomy );
				break;

			default:
				// Default is upper-case-first the object-type (e.g. 'Plugin').
				$object_type_title = Types::make_key_readable( $event->object_type, true );
		}
		$html .= "<h4>$object_type_title Details</h4>\n";

		// Start table.
		$class = 'wp-logify-properties-table-' . ( $show_new_vals ? 3 : 2 ) . '-column';
		$html .= "<table class='wp-logify-details-table wp-logify-properties-table $class'>\n";

		// Header row is only needed if both old and new value columns are shown.
		if ( $show_new_vals ) {
			$html .= "<tr><th></th><th>Before</th><th>After</th></tr>\n";
		}

		// Property rows.
		foreach ( $event->properties as $prop ) {
			// Some info we don't care about. Maybe we shouldn't even store these.
			if ( $prop->key === 'session_tokens' || $prop->key === 'wp_user_level' ) {
				continue;
			}

			// Start row.
			$html .= '<tr>';

			// Property key.
			if ( $prop->key === 'wp_capabilities' ) {
				$key = 'Roles';

				// Get the current or old roles.
				$roles = array();
				if ( ! empty( $prop->val ) ) {
					foreach ( $prop->val as $role => $enabled ) {
						if ( $enabled ) {
							$roles[] = ucfirst( $role );
						}
					}
				}

				// Get the new roles.
				$new_roles = array();
				if ( ! empty( $prop->new_val ) ) {
					foreach ( $prop->new_val as $role => $enabled ) {
						if ( $enabled ) {
							$new_roles[] = ucfirst( $role );
						}
					}
				}
			} else {
				$key = Types::make_key_readable( $prop->key );
			}
			$html .= "<th>$key</th>";

			// Current or old value.
			if ( $prop->key === 'wp_capabilities' ) {
				// Show the role(s) without the booleans.
				$val = Types::value_to_html( $key, $roles );
			} else {
				// Default.
				$val = Types::value_to_html( $prop->key, $prop->val );
			}
			$html .= "<td>$val</td>";

			// New value.
			if ( $show_new_vals ) {
				if ( $prop->key === 'wp_capabilities' ) {
					$new_val = Types::value_to_html( $key, $new_roles );
				} else {
					$new_val = Types::value_to_html( $prop->key, $prop->new_val );
				}

				// Get the CSS class.
				$new_value_exists = $prop->new_val != null;
				$class            = $new_value_exists ? 'wp-logify-value-changed' : 'wp-logify-value-unchanged';
				$html            .= "<td class='$class'>$new_val</td>";
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
	 * Formats the details of a log entry.
	 *
	 * @param Event $event The event.
	 * @return string The formatted details as HTML.
	 */
	public static function format_details( Event $event ): string {
		$html  = "<div class='wp-logify-details'>\n";
		$html .= self::format_user_details( $event );
		$html .= self::format_properties( $event );
		$html .= self::format_eventmetas( $event );
		$html .= "</div>\n";
		return $html;
	}
}
