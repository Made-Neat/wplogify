<?php
/**
 * Contains the Notes_Page class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use DateTime;
use DateTimeZone;

/**
 * Class Logify_WP\Notes_Page
 *
 * Contains methods for formatting notes entries for display in the admin area.
 */
class Notes_Page {

	/**
	 * Initialize the log page.
	 */
	public static function init() {
		add_action( 'wp_ajax_logify_wp_fetch_notes', array( __CLASS__, 'fetch_notes' ) );
		add_action('wp_ajax_logify_update_notes', array( __CLASS__, 'logify_update_notes' ) );
		add_action('wp_ajax_logify_add_notes', array( __CLASS__, 'logify_add_notes' ) );
	}

	public static function logify_update_notes() {
		self::ensure_feature_enabled();
		
		check_ajax_referer('logify-wp-notes-page', 'security');
		$note_id = intval($_POST['note_id']);
		$event_id = intval($_POST['event_id']);

		if ( !$event_id && !$note_id ) {
			wp_send_json_error(['message' => 'Invalid note data.']);
		}
		if(empty($_POST['note_content'])){
			wp_send_json_error(['message' => 'Note can\'t be empty.']);
		}
	
		// Fetch the note to validate ownership
		$note_repo = new \Logify_WP\Note_Repository();	

		if ( $event_id ){
			$existing_note = $note_repo->load_by_event_id($event_id);
		}else{
			$existing_note = $note_repo->load($note_id);
		}

		if (!$existing_note) {
			wp_send_json_error(['message' => 'Note not found.']);
		}
	
		// Check if the current user is the owner of the note
		if ($existing_note->user_id !== get_current_user_id()) {
			wp_send_json_error(['message' => 'You are not authorized to edit this note.']);
		}

		$success = $note_repo->save((object) [
			'id'   => $note_id,
			'note' => $_POST['note_content'],
		]);
	
		if ($success) {
			wp_send_json_success(['message' => 'Note updated successfully.']);
		} else {
			wp_send_json_error(['message' => 'Failed to update note.']);
		}
	}

	public static function logify_add_notes() {
		self::ensure_feature_enabled();
		
		check_ajax_referer('logify-wp-notes-page', 'security');
	
		// Validate input
		if (empty($_POST['note_content'])) {
			wp_send_json_error(['message' => 'Note content cannot be empty.']);
		}

		$event_id = intval($_POST['event_id']);
		
		// Create note repository instance
		$note_repo = new \Logify_WP\Note_Repository();
		$user = wp_get_current_user();
		// Save new note
		$success = $note_repo->create( [
			'note'        => $_POST['note_content'],
			'user_id'     => get_current_user_id(), // Assuming user ID is required
			'user_role' => !empty($user->roles) ? implode(', ', $user->roles) : 'No role assigned', // Get user roles
    		'user_name'  => $user->user_login, // Get username
			'activity_id' => ($event_id) ? $event_id : 0,
			'created_at'  => current_time('mysql'), // Assuming created_at is required
			'ip_address'  => sanitize_text_field($_SERVER['REMOTE_ADDR']), // Optional: IP address
		]);
	
		if ($success) {
			wp_send_json_success(['message' => 'Note added successfully.']);
		} else {
			wp_send_json_error(['message' => 'Failed to add note.']);
		}
	}

	/**
	 * Check if the notes feature is enabled
	 */
	private static function ensure_feature_enabled() {
		if (!get_option('logify_wp_enable_notes', false)) {
			wp_send_json_error(['message' => 'Notes feature is not enabled in settings.']);
		}
	}	

	/**
	 * Display the log page.
	 */
	public static function display_notes_page() {
		self::ensure_feature_enabled();
		
		if ( ! Access_Control::can_access_log_page() ) {
			// Disallow access.
			wp_die( esc_html( 'Sorry, you are not allowed to access this page.' ), 403 );
		}

		// Get all the data required for the log page.
		//$post_types  = Note_Repository::get_post_types();
		//$taxonomies  = Note_Repository::get_taxonomies();
		//$note_types = Note_Repository::get_event_types();
		$users       = Event_Repository::get_users();
		$roles       = Event_Repository::get_roles();

		// Include the log page template.
		include LOGIFY_WP_PLUGIN_DIR . 'templates/notes-page.php';
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
	 * Fetches notes from the database based on the provided search criteria.
	 */
	public static function fetch_notes() {
		self::ensure_feature_enabled();
		
		// Verify the nonce
		check_ajax_referer( 'logify-wp-notes-page', 'security' );

		// Check user capabilities.
		if ( ! Access_Control::can_access_log_page() ) {
			wp_send_json_error( array( 'message' => esc_html( 'You are not allowed to access this data.' ) ), 403 );
		}

		global $wpdb;

		// Get table names.
		$notes_table_name = Note_Repository::get_table_name();
		$users_table_name  = $wpdb->users;

		// These should match the columns.
		$columns = array(
			'note_id',
			'created_at',
			'display_name',
			'note',
			'event_id',
			'edit_link'
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

		// Get the order-by column. Default to created_at.
		$order_by_columns = array( 'created_at' );
		if ( isset( $_POST['order'][0]['column'] ) ) {
			$column_number = (int) $_POST['order'][0]['column'];
			if ( key_exists( $column_number, $columns ) ) {
				$order_by_columns = array( $columns[ $column_number ] );
			}
		}
		// Include ordering by note ID, if not already included.
		if ( ! in_array( 'note_id', $order_by_columns ) ) {
			$order_by_columns[] = 'note_id';
		}

		// Get the order-by direction. Check it's valid. Default to DESC.
		$order_by_direction = isset( $_POST['order'][0]['dir'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_POST['order'][0]['dir'] ) ) )
			: 'DESC';
		if ( ! in_array( $order_by_direction, array( 'ASC', 'DESC' ), true ) ) {
			$order_by_direction = 'DESC';
		}

		// Get the search value.
		$search_value = isset( $_POST['search']['value'] )
			? sanitize_text_field( wp_unslash( $_POST['search']['value'] ) )
			: '';

		// Get the object types to show events for.
		$valid_object_types = array_keys( Logger::VALID_OBJECT_TYPES );
		if ( ! empty( $_COOKIE['object_types'] ) ) {
			$cookie_value          = sanitize_text_field( wp_unslash( $_COOKIE['object_types'] ) );
			$selected_object_types = json_decode( stripslashes( $cookie_value ), true );
			$object_types          = array_keys( array_filter( $selected_object_types ) );
		} else {
			$object_types = $valid_object_types;
		}

		// Get the date range.
		$start_date = isset( $_COOKIE['start_date'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['start_date'] ) )
			: null;
		$end_date   = isset( $_COOKIE['end_date'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['end_date'] ) )
			: null;

		// Get the post type and taxonomy.
		$post_type = isset( $_COOKIE['post_type'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['post_type'] ) )
			: null;
		$taxonomy  = isset( $_COOKIE['taxonomy'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['taxonomy'] ) )
			: null;

		// Get the event type.
		$note_type = isset( $_COOKIE['event_type'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['event_type'] ) )
			: null;

		// Get the user.
		$user_id = isset( $_COOKIE['user_id'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['user_id'] ) )
			: null;

		// Get the role.
		$role = isset( $_COOKIE['role'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['role'] ) )
			: null;

		// -----------------------------------------------------------------------------------------
		// Get the total number of notes in the database.
		$num_total_records = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $notes_table_name )
		);

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
				'(created_at LIKE %s
                	OR note LIKE %s    
					OR user_role LIKE %s
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
				)
			);
		}

		// Filter by date range if specified.
		if ( $start_date || $end_date ) {
			// Get the date format and time zone from the site settings.
			$date_format = get_option( 'date_format' );

			$timezone_string = get_option('timezone_string');
			if (!$timezone_string) {
				// Fallback to gmt_offset if timezone_string is empty
				$gmt_offset = get_option('gmt_offset', 0);
				$timezone_string = timezone_name_from_abbr('', $gmt_offset * 3600, false);
				if (!$timezone_string) {
					$timezone_string = 'UTC'; // Fallback to UTC if both are invalid
				}
			}
			$time_zone = new DateTimeZone($timezone_string);

			// Start date.
			if ( $start_date ) {
				// Check the provided string is valid by converting it to a DateTime object.
				$start_date = DateTime::createFromFormat( $date_format, $start_date, $time_zone );
				if ( $start_date ) {
					$where_parts[] = 'created_at >= %s';
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
					$where_parts[] = 'created_at < %s';
					$where_args[]  = $end_date->format( 'Y-m-d 00:00:00' );
				}
			}
		}

		// Filter by event type if specified.
		if ( $note_type ) {
			$where_parts[] = 'event_type = %s';
			$where_args[]  = $note_type;
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
		$select_args          = array( $notes_table_name, $users_table_name );
		$args                 = array_merge( $select_args, $where_args );
		$num_filtered_records = (int) $wpdb->get_var(
			$wpdb->prepare( "$select_count $where", $args )
		);

		// -----------------------------------------------------------------------------------------
		// Get the requested records.

		// Select clause.
		$select = 'SELECT note_id FROM %i e LEFT JOIN %i u ON e.user_id = u.ID';

		// Order-by clause.
		$order_by      = '';
		$order_by_args = array();
		if ( ! empty( $order_by_columns ) ) {
			$order_by_parts = array();
			foreach ( $order_by_columns as $order_by_col ) {
				$order_by_parts[] = "%i $order_by_direction";
				$order_by_args[]  = $order_by_col;
			}
			$order_by = 'ORDER BY ' . implode( ', ', $order_by_parts );
			// Debug::info( $order_by_columns, $order_by_direction, $order_by_parts, $order_by_args, $order_by );
		}

		// Limit clause.
		$limit      = 'LIMIT %d OFFSET %d';
		$limit_args = array( $page_length, $start );

		// Construct and run the SQL statement.
		$args = array_merge( $select_args, $where_args, $order_by_args, $limit_args );
		$recordset = $wpdb->get_results(
			$wpdb->prepare( "$select $where $order_by $limit", $args ),
			ARRAY_A
		);
		//echo $wpdb->last_query;die; // Displays the query on the page (for debugging purposes only)

		// -----------------------------------------------------------------------------------------
		// Construct the data array to return to the client.
		$data = array();
		foreach ( $recordset as $record ) {
			// Construct the Note object.
			$note = Note_Repository::load( $record['note_id'] );
			// Create a new data item.
			$item             = array();
			$item['note_id'] = $note->id;

			// Date and time of the event.
			try {
				$created_at_datetime = new \DateTime($note->created_at); // Convert to DateTime object.
				$formatted_datetime  = DateTimes::format_datetime_site($created_at_datetime);
				$time_ago            = DateTimes::get_ago_string($created_at_datetime); // Pass DateTime object.
				$item['created_at']  = '<div>' . wp_kses_post($formatted_datetime) . ' (' . esc_html($time_ago) . ')</div>';
			} catch (\Exception $e) {
				// Handle the exception if the date string is invalid.
				error_log('Invalid date format for $note->created_at: ' . $e->getMessage());
				$item['created_at'] = '<div>Invalid date</div>';
			}
			
			// User details.
			$user_tag             = User_Utility::get_tag( $note->user_id, $note->user_name );
			$user_role            = ucwords( $note->user_role ?? 'none' );
			$item['display_name'] = get_avatar( $note->user_id, 32 ) . ' <div class="logify-wp-user-info">' . wp_kses_post( $user_tag ) . '<br><span class="logify-wp-user-role">' . esc_html( $user_role ) . '</span></div>';

			// Note text
			$item['note'] = wp_kses_post( $note->note );
			
			// Strip HTML tags
			$plainText = strip_tags($item['note']);

			// Decode HTML entities like &nbsp; into regular spaces
			$plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5);

			// Replace non-breaking spaces (\u00a0) with regular spaces
			$plainText = str_replace("\u{00a0}", ' ', $plainText);

			// Replace multiple spaces (including non-breaking spaces) with a single space
			$plainText = preg_replace('/\s+/', ' ', $plainText);

			// Trim leading and trailing spaces
			$plainText = trim($plainText);

			// Trim the text to 50 characters and add ellipsis if necessary
			$item['short_note'] = mb_strimwidth($plainText, 0, 50, '...');

			// Note ID.
			$item['event_id'] = $note->event_id;

			$item['edit_link'] = '';

			$item['details'] = self::format_details( $note );
			
			if ($note->user_id == get_current_user_id()) {
				$item['edit_link'] = '<a href="#" class="edit-note-link" data-note-id="' . intval($note->id) . '" data-note-content="' . esc_attr($note->note) . '">Edit</a>';
			}
			
			// Add the item to the data array.
			$data[] = $item;
		}

		$draw = isset( $_POST['draw'] ) ? intval( wp_unslash( $_POST['draw'] ) ) : 0;

		wp_send_json(
			array(
				'draw'            => $draw,
				'recordsTotal'    => $num_total_records,
				'recordsFiltered' => $num_filtered_records,
				'data'            => $data,
			)
		);
	}

	/**
	 * Formats user details for a log entry.
	 *
	 * @param Note $note The event.
	 * @return string The formatted user details as an HTML table.
	 */
	public static function format_user_details( Note $note ): string {
		// Construct the HTML.
		$html  = "<div class='logify-wp-details-section logify-wp-user-details-section'>\n";
		$html .= "<h4>User Details</h4>\n";
		$html .= "<div class='logify-wp-details-table-wrapper'>\n";
		$html .= "<table class='logify-wp-details-table logify-wp-user-details-table'>\n";

		// User tag.
		$user_tag = User_Utility::get_tag( $note->user_id, $note->user_name );
		$html    .= "<tr><th>User</th><td>$user_tag</td></tr>\n";

		// Role.
		$user_role = esc_html( ucwords( $note->user_role ) );
		$html     .= "<tr><th>Role</th><td>$user_role</td></tr>\n";

		// User ID.
		$html .= "<tr><th>ID</th><td>$note->ip_address</td></tr>";

		// IP address.
		$ip_link = $note->ip_address ? Urls::get_ip_link( $note->ip_address ) : 'Unknown';
		$html   .= "<tr><th>IP address</th><td>$ip_link</td></tr>\n";

		// If the user has been deleted, we won't have all their details. Although, we could
		// try looking up the log entry for when the user was deleted, and see if we can
		// get their details from there.

		// Get some extra details if the user has not been deleted.
		if ( User_Utility::exists( $note->user_id ) ) {
			// Load the user.
			$user = User_Utility::load( $note->user_id );

			// User email.
			$user_email = empty( $user->user_email ) ? 'Unknown' : esc_html( $user->user_email );
			$html      .= '<tr><th>Email</th><td>' . User_Utility::get_email_link( $user_email ) . "</a></td></tr>\n";

			// Get the registration datetime.
			$registration_datetime        = DateTimes::create_datetime( $user->data->user_registered, 'UTC' );
			$registration_datetime_string = DateTimes::format_datetime_site( $registration_datetime );
			$html                        .= "<tr><th>Registered</th><td>$registration_datetime_string</td></tr>\n";

			// Get the last login datetime.
			$last_login_datetime        = User_Utility::get_last_login_datetime( $note->user_id );
			$last_login_datetime_string = $last_login_datetime !== null ? DateTimes::format_datetime_site( $last_login_datetime ) : 'Unknown';
			$html                      .= "<tr><th>Last login</th><td>$last_login_datetime_string</td></tr>\n";

			// Get the last active datetime.
			$last_active_datetime        = User_Utility::get_last_active_datetime( $note->user_id );
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
	 * @param Note $note The event.
	 * @return string The formatted eventmetas as an HTML table.
	 */
	public static function format_eventmetas( Note $note ): string {

		// Convert eventmetas to a table of key-value pairs.
		$html  = "<div class='logify-wp-details-section logify-wp-eventmeta-section'>\n";
		$html .= "<h4>Note Details</h4>\n";
		$html .= "<div class='logify-wp-details-table-wrapper'>\n";
		$html .= "<table class='logify-wp-details-table logify-wp-eventmeta-table'>\n";
		$html .= "<tr><th>Note</th><td>$note->note</td></tr>";
				// Handle the null case.
		if ( !empty( $note->eventmetas ) ) {
					
			foreach ( $note->eventmetas as $notemeta ) {
				$label = Strings::key_to_label( $notemeta->meta_key );
				$value = Types::value_to_html( $notemeta->meta_key, $notemeta->meta_value );
				$html .= "<tr><th>$label</th><td>$value</td></tr>";
			}
		}
		$html .= "</table>\n";
		$html .= "</div>\n";
		$html .= "</div>\n";
		return $html;
	}

	/**
	 * Get the title for a post type event.
	 *
	 * @param Note $note The event object.
	 * @return string The post type title.
	 */
	public static function get_post_type_title( $note ) {
		// Get the post type. It might be in the properties.
		$post_type = $note->get_prop( 'post_type' )?->val;

		// If not, we can get it from the post.
		if ( ! $post_type ) {
			$post_type = $note->get_object()?->post_type;
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
	 * @param Note $note The event object.
	 * @return string The term type title.
	 */
	public static function get_term_type_title( $note ) {
		// Get the taxonomy. It might be in the properties.
		$taxonomy = $note->get_prop( 'taxonomy' )?->val;

		// If not, we can get it from the term.
		if ( ! $taxonomy ) {
			$taxonomy = $note->get_object()?->taxonomy;
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
	 * @param Note $note The event.
	 * @return string The object properties formatted as an HTML table.
	 */
	public static function format_properties( Note $note ): string {
		// Handle the null case.
		if ( empty( $note->properties ) ) {
			return '';
		}

		// Check which columns to show.
		$show_new_vals = false;
		foreach ( $note->properties as $prop ) {
			// Check if we want to show the 'After' column.
			if ( $prop->new_val !== null ) {
				$show_new_vals = true;
				break;
			}
		}

		// Convert JSON string to a table showing the changes.
		$html = "<div class='logify-wp-details-section logify-wp-properties-section'>\n";

		// Get the title given the object type.
		$object_type_title = match ( $note->object_type ) {
			'user'   => 'Account',
			'option' => 'Setting',
			'post'   => self::get_post_type_title( $note ),
			'term'   => self::get_term_type_title( $note ),
			default  => Strings::key_to_label( $note->object_type, true ),
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
		foreach ( $note->properties as $prop ) {
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
	 * @param Note $note The event.
	 * @return string The formatted details as HTML.
	 */
	public static function format_details( Note $note ): string {
		$html  = "<div class='logify-wp-details'>\n";
		$html .= self::format_user_details( $note );
		$html .= self::format_properties( $note );
		$html .= self::format_eventmetas( $note );
		$html .= "</div>\n";
		return $html;
	}
}
