<?php
/**
 * Contains the Log_Page class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DateTime;
use DateTimeZone;

/**
 * Class Logify_WP\Log_Page
 *
 * Contains methods for formatting event log entries for display in the admin area.
 */
class Log_Page {

	/**
	 * Initialize the log page.
	 */
	public static function init() {
		add_action( 'wp_ajax_logify_wp_fetch_logs', array( __CLASS__, 'fetch_logs' ) );
	}

	/**
	 * Display the log page.
	 */
	public static function display_log_page() {
		if ( ! Access_Control::can_access_log_page() ) {
			// Disallow access.
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'logify-wp' ), 403 );
		}

		// Get all the data required for the log page.
		$post_types  = Event_Repository::get_post_types();
		$taxonomies  = Event_Repository::get_taxonomies();
		$event_types = Event_Repository::get_event_types();
		$users       = Event_Repository::get_users();
		$roles       = Event_Repository::get_roles();

		// Include the log page template.
		include LOGIFY_WP_PLUGIN_DIR . 'templates/log-page.php';
	}

	/**
	 * Get the user's preference for the number of items to show per page.
	 *
	 * @return int The number of items to show per page.
	 */
	public static function get_items_per_page(): int {
		$page_length = (int) get_user_option( 'logify_wp_events_per_page', get_current_user_id() );
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
			if ( key_exists( $column_number, $columns ) ) {
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

		// Get the object types to show events for.
		$valid_object_types = array_keys( Logger::VALID_OBJECT_TYPES );
		if ( ! empty( $_COOKIE['object_types'] ) ) {
			$selected_object_types = json_decode( stripslashes( $_COOKIE['object_types'] ), true );
			$object_types          = array_keys( array_filter( $selected_object_types ) );
		} else {
			$object_types = $valid_object_types;
		}

		// Get the date range.
		$start_date = isset( $_COOKIE['start_date'] ) ? wp_unslash( $_COOKIE['start_date'] ) : null;
		$end_date   = isset( $_COOKIE['end_date'] ) ? wp_unslash( $_COOKIE['end_date'] ) : null;

		// Get the post type and taxonomy.
		$post_type = isset( $_COOKIE['post_type'] ) ? wp_unslash( $_COOKIE['post_type'] ) : null;
		$taxonomy  = isset( $_COOKIE['taxonomy'] ) ? wp_unslash( $_COOKIE['taxonomy'] ) : null;

		// Get the event type.
		$event_type = isset( $_COOKIE['event_type'] ) ? wp_unslash( $_COOKIE['event_type'] ) : null;

		// Get the user.
		$user_id = isset( $_COOKIE['user_id'] ) ? wp_unslash( $_COOKIE['user_id'] ) : null;

		// Get the role.
		$role = isset( $_COOKIE['role'] ) ? wp_unslash( $_COOKIE['role'] ) : null;

		// -----------------------------------------------------------------------------------------
		// Get the total number of events in the database.
		$total_sql         = $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $events_table_name );
		$num_total_records = (int) $wpdb->get_var( $total_sql );

		// -----------------------------------------------------------------------------------------
		// Get the number of filtered records.

		// Select clause.
		$select_count = 'SELECT COUNT(*) FROM %i e LEFT JOIN %i u ON e.user_id = u.ID';

		// Build the where clause.
		$where_parts = array();
		$where_args  = array();

		// Filter by search string if specified.
		if ( $search_value !== '' ) {
			$where_parts[] =
				'(when_happened LIKE %s
                    OR user_role LIKE %s
                    OR user_ip LIKE %s
                    OR user_location LIKE %s
                    OR user_agent LIKE %s
                    OR event_type LIKE %s
                    OR object_type LIKE %s
                    OR object_name LIKE %s
                    OR user_login LIKE %s
                    OR user_email LIKE %s
                    OR display_name LIKE %s)';
			$like_value    = '%' . $wpdb->esc_like( $search_value ) . '%';
			$where_args    = array_merge(
				$where_args,
				array(
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
				)
			);
		}

		// Filter by object type and subtype if specified.
		$all_object_types_selected = empty( array_diff( $valid_object_types, $object_types ) );
		if ( ! $all_object_types_selected || $post_type || $taxonomy ) {
			// Assemble the OR parts.
			$or_parts = array();

			// Filter by post type if specified.
			if ( in_array( 'post', $object_types ) && $post_type ) {
				// Remove 'post' from object_types.
				$object_types = array_diff( $object_types, array( 'post' ) );
				$or_parts[]   = "(object_type = 'post' AND object_subtype = %s)";
				$where_args[] = $post_type;
			}

			// Filter by taxonomy if specified.
			if ( in_array( 'term', $object_types ) && $taxonomy ) {
				// Remove 'term' from object_types.
				$object_types = array_diff( $object_types, array( 'term' ) );
				$or_parts[]   = "(object_type = 'term' AND object_subtype = %s)";
				$where_args[] = $taxonomy;
			}

			// Filter by the remaining object types.
			if ( $object_types ) {
				$object_type_string = implode( ',', array_map( fn( $object_type ) => "'$object_type'", $object_types ) );
				$or_parts[]         = "object_type IN ($object_type_string)";
			} else {
				$or_parts[] = 'object_type IS NULL';
			}

			// Add the OR parts to the where clause.
			if ( count( $or_parts ) === 1 ) {
				$where_parts[] = $or_parts[0];
			} elseif ( count( $or_parts ) > 1 ) {
				$where_parts[] = '(' . implode( ' OR ', $or_parts ) . ')';
			}
		}

		// Filter by date range if specified.
		if ( $start_date || $end_date ) {
			// Get the date format and time zone from the site settings.
			$date_format = get_option( 'date_format' );
			$time_zone   = new DateTimeZone( get_option( 'timezone_string' ) );

			// Start date.
			if ( $start_date ) {
				// Check the provided string is valid by converting it to a DateTime object.
				$start_date = DateTime::createFromFormat( $date_format, $start_date, $time_zone );
				if ( $start_date ) {
					$where_parts[] = 'when_happened >= %s';
					$where_args[]  = $start_date->format( 'Y-m-d 00:00:00' );
				}
			}

			// End date.
			if ( $end_date ) {
				// Check the provided string is valid by converting it to a DateTime object.
				$end_date = DateTime::createFromFormat( $date_format, $end_date, $time_zone );
				if ( $end_date ) {
					// Add a day, and look for events before this.
					$end_date      = DateTimes::add_days( $end_date, 1 );
					$where_parts[] = 'when_happened < %s';
					$where_args[]  = $end_date->format( 'Y-m-d 00:00:00' );
				}
			}
		}

		// Filter by event type if specified.
		if ( $event_type ) {
			$where_parts[] = 'event_type = %s';
			$where_args[]  = $event_type;
		}

		// Filter by user if specified.
		if ( $user_id !== '' && $user_id !== null ) {
			$where_parts[] = 'user_id = %d';
			$where_args[]  = $user_id;
		}

		// Filter by role if specified.
		if ( $role ) {
			$where_parts[] = 'user_role LIKE %s';
			$where_args[]  = '%' . $wpdb->esc_like( $role ) . '%';
		}

		// Complete building of the where clause.
		$where = $where_parts ? ( 'WHERE ' . implode( ' AND ', $where_parts ) ) : '';

		// Construct and run the SQL statement.
		$select_args  = array( $events_table_name, $user_table_name );
		$args         = array_merge( $select_args, $where_args );
		$filtered_sql = $wpdb->prepare( "$select_count $where", $args );
		// Debug::sql( $filtered_sql );
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
		// Debug::sql( $results_sql );
		$recordset = $wpdb->get_results( $results_sql, ARRAY_A );

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
			$user_role            = esc_html( ucwords( $event->user_role ?? 'none' ) );
			$item['display_name'] = get_avatar( $event->user_id, 32 ) . " <div class='logify-wp-user-info'>$user_tag<br><span class='logify-wp-user-role'>$user_role</span></div>";

			// Source IP.
			$item['user_ip'] = Urls::get_ip_link( $event->user_ip );

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
		// Construct the HTML.
		$html  = "<div class='logify-wp-details-section logify-wp-user-details-section'>\n";
		$html .= "<h4>User Details</h4>\n";
		$html .= "<div class='logify-wp-details-table-wrapper'>\n";
		$html .= "<table class='logify-wp-details-table logify-wp-user-details-table'>\n";

		// User tag.
		$user_tag = User_Utility::get_tag( $event->user_id, $event->user_name );
		$html    .= "<tr><th>User</th><td>$user_tag</td></tr>\n";

		// Role.
		$user_role = esc_html( ucwords( $event->user_role ) );
		$html     .= "<tr><th>Role</th><td>$user_role</td></tr>\n";

		// User ID.
		$html .= "<tr><th>ID</th><td>$event->user_id</td></tr>";

		// IP address.
		$ip_link = $event->user_ip ? Urls::get_ip_link( $event->user_ip ) : 'Unknown';
		$html   .= "<tr><th>IP address</th><td>$ip_link</td></tr>\n";

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

			// Get the registration datetime.
			$registration_datetime        = DateTimes::create_datetime( $user->data->user_registered, 'UTC' );
			$registration_datetime_string = DateTimes::format_datetime_site( $registration_datetime );
			$html                        .= "<tr><th>Registered</th><td>$registration_datetime_string</td></tr>\n";

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
		$html  = "<div class='logify-wp-details-section logify-wp-eventmeta-section'>\n";
		$html .= "<h4>Event Details</h4>\n";
		$html .= "<div class='logify-wp-details-table-wrapper'>\n";
		$html .= "<table class='logify-wp-details-table logify-wp-eventmeta-table'>\n";
		foreach ( $event->eventmetas as $eventmeta ) {
			$label = Strings::key_to_label( $eventmeta->meta_key );
			$value = Types::value_to_html( $eventmeta->meta_key, $eventmeta->meta_value );
			$html .= "<tr><th>$label</th><td>$value</td></tr>";
		}
		$html .= "</table>\n";
		$html .= "</div>\n";
		$html .= "</div>\n";
		return $html;
	}

	/**
	 * Get the title for a post type event.
	 *
	 * @param Event $event The event object.
	 * @return string The post type title.
	 */
	public static function get_post_type_title( $event ) {
		// Get the post type. It might be in the properties.
		$post_type = $event->get_prop( 'post_type' )?->val;

		// If not, we can get it from the post.
		if ( ! $post_type ) {
			$post_type = $event->get_object()?->post_type;
		}

		// If we have a post type, get the singular name.
		if ( $post_type ) {
			return Post_Utility::get_post_type_singular_name( $post_type );
		} else {
			// Otherwise, default to 'Post'.
			return 'Post';
		}
	}

	/**
	 * Get the title for a term type event.
	 *
	 * @param Event $event The event object.
	 * @return string The term type title.
	 */
	public static function get_term_type_title( $event ) {
		// Get the taxonomy. It might be in the properties.
		$taxonomy = $event->get_prop( 'taxonomy' )?->val;

		// If not, we can get it from the term.
		if ( ! $taxonomy ) {
			$taxonomy = $event->get_object()?->taxonomy;
		}

		// If we have a taxonomy, get the singular name.
		if ( $taxonomy ) {
			return Taxonomy_Utility::get_singular_name( $taxonomy );
		} else {
			// Otherwise, default to 'Term'.
			return 'Term';
		}
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
		$html = "<div class='logify-wp-details-section logify-wp-properties-section'>\n";

		// Get the title given the object type.
		$object_type_title = match ( $event->object_type ) {
			'user'   => 'Account',
			'option' => 'Setting',
			'post'   => self::get_post_type_title( $event ),
			'term'   => self::get_term_type_title( $event ),
			default  => Strings::key_to_label( $event->object_type, true ),
		};

		$html .= "<h4>$object_type_title Details</h4>\n";

		// Start scrollable wrapper.
		$html .= "<div class='logify-wp-details-table-wrapper'>\n";

		// Start table.
		$class = 'logify-wp-properties-table-' . ( $show_new_vals ? 3 : 2 ) . '-column';
		$html .= "<table class='logify-wp-details-table logify-wp-properties-table $class'>\n";

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
			$key   = Strings::key_to_label( $prop->key );
			$html .= "<th>$key</th>";

			// Current or old value.
			$val   = Types::value_to_html( $prop->key, $prop->val );
			$html .= "<td>$val</td>";

			// New value.
			if ( $show_new_vals ) {
				$new_val = Types::value_to_html( $prop->key, $prop->new_val );

				// Get the CSS class to show if the value has been changed or not.
				$new_value_exists = $prop->new_val !== null;
				$class            = $new_value_exists ? 'logify-wp-value-changed' : 'logify-wp-value-unchanged';

				$html .= "<td class='$class'>$new_val</td>";
			}

			// End row.
			$html .= "</tr>\n";
		}

		// End table.
		$html .= "</table>\n";

		// End scrollable wrapper.
		$html .= "</div>\n";

		// End section.
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
		$html  = "<div class='logify-wp-details'>\n";
		$html .= self::format_user_details( $event );
		$html .= self::format_properties( $event );
		$html .= self::format_eventmetas( $event );
		$html .= "</div>\n";
		return $html;
	}
}
