<?php
/**
 * Cut from log-page.php template.
 */
function display_log_details( $log_id ) {
		global $wpdb;
		$log_id     = intval( $log_id );
		$table_name = Logger::get_table_name();
		$log        = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE ID = %d', $table_name, $log_id ) );

	if ( $log ) {
		$details = maybe_unserialize( $log->details );
		?>
			<div class="log-details">
				<h3>Log Details</h3>
				<p><strong>User:</strong> <?php echo esc_html( get_userdata( $log->user_id )->user_login ); ?></p>
				<p><strong>Action:</strong> <?php echo esc_html( $log->action ); ?></p>
				<p><strong>Date and Time:</strong> <?php echo esc_html( $log->created_at ); ?></p>
			<?php if ( isset( $details['post_id'] ) ) : ?>
					<p><strong>Post Title:</strong> <?php echo esc_html( $details['post_title'] ); ?></p>
					<p><strong>Post Type:</strong> <?php echo esc_html( $details['post_type'] ); ?></p>
				<?php elseif ( isset( $details['term_id'] ) ) : ?>
					<p><strong>Term Name:</strong> <?php echo esc_html( $details['term_name'] ); ?></p>
					<p><strong>Taxonomy:</strong> <?php echo esc_html( $details['taxonomy'] ); ?></p>
				<?php elseif ( isset( $details['option_name'] ) ) : ?>
					<p><strong>Option Name:</strong> <?php echo esc_html( $details['option_name'] ); ?></p>
					<p><strong>Old Value:</strong> <?php echo esc_html( $details['old_value'] ); ?></p>
					<p><strong>New Value:</strong> <?php echo esc_html( $details['new_value'] ); ?></p>
				<?php else : ?>
					<p><strong>Details:</strong> <?php echo esc_html( print_r( $details, true ) ); ?></p>
				<?php endif; ?>
			</div>
			<?php
	}
}

if ( isset( $_GET['log_id'] ) ) {
	display_log_details( intval( $_GET['log_id'] ) );
}
