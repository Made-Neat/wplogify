<?php
/**
 * Settings page template.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

// Show a success message if settings were saved.
$settings_updated = isset( $_GET['settings-updated'] ) ? sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) : '';
if ( $settings_updated ) {
	add_settings_error(
		'logify_wp_messages',
		'logify_wp_settings_saved',
		esc_html( 'Settings saved.' ),
		'updated'
	);
}

// Retrieve and sanitize the nonce from $_GET.
$nonce = isset( $_GET['logify_wp_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['logify_wp_nonce'] ) ) : '';
if ( $nonce && wp_verify_nonce( $nonce, 'logify_wp_messages_nonce' ) ) {

	// Show a message if the log records were successfully deleted.
	$logs_reset = isset( $_GET['reset'] ) ? sanitize_text_field( wp_unslash( $_GET['reset'] ) ) : '';
	if ( $logs_reset === 'success' ) {
		add_settings_error(
			'logify_wp_messages',
			'logify_wp_reset_done',
			esc_html( 'Log records have been deleted.' ),
			'updated'
		);
	}

	// Show a message if the data was successfully migrated.
	$data_migrated = isset( $_GET['migrated'] ) ? sanitize_text_field( wp_unslash( $_GET['migrated'] ) ) : '';
	if ( $data_migrated === 'success' ) {
		add_settings_error(
			'logify_wp_messages',
			'logify_wp_migration_done',
			esc_html( 'Data migrated successfully. You should verify the data in the new plugin, then deactivate and delete the old WP Logify plugin.' ),
			'updated'
		);
	}
}

// Display any settings errors or messages.
settings_errors( 'logify_wp_messages' );
?>

<div class="wrap">
	<h1>Logify WP Settings</h1>
	<form method="post" action="options.php">

		<?php settings_fields( 'logify_wp_settings_group' ); ?>

		<fieldset class="logify-wp-settings-group">
			<legend>Access control</legend>
			<table class="form-table logify-wp-settings-table">
				<tr valign="top">
					<th scope="row">Roles with access</th>
					<td>
						<!-- Hidden field to ensure that the administrator role is always selected. -->
						<!-- Because the administrator checkbox is disabled, it doesn't get submitted with the form. -->
						<input type="hidden" name="logify_wp_roles_with_access[]" value="administrator">
						<?php
						$roles                      = wp_roles()->roles;
						$selected_roles_with_access = Plugin_Settings::get_roles_with_access();
						foreach ( $roles as $role_key => $role ) {
							$checked  = in_array( $role_key, $selected_roles_with_access, true ) ? 'checked' : '';
							$disabled = $role_key === 'administrator' ? 'disabled' : '';
							echo '<label>';
							echo '<input type="checkbox" name="logify_wp_roles_with_access[]" value="' . esc_attr( $role_key ) . '" ' . esc_attr( $checked ) . ' ' . esc_attr( $disabled ) . '>';
							echo esc_html( $role['name'] );
							echo '</label><br>';
						}
						?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Additional users with access</th>
					<td>
						<div id="logify-wp-settings-users">
							<?php
							$users = get_users();
							foreach ( $users as $user ) {
								$checked = Access_Control::user_has_individual_access( $user ) ? 'checked' : '';
								echo '<label>';
								echo '<input type="checkbox" name="logify_wp_users_with_access[]" value="' . esc_attr( $user->ID ) . '" ' . esc_attr( $checked ) . '>';
								echo esc_html( User_Utility::get_name( $user->ID ) );
								if ( Access_Control::user_has_access_via_role( $user ) ) {
									echo ' <span class="logify-wp-role-access-msg">' . esc_html( '(has access via role)' ) . '</span>';
								}
								echo '</label><br>';
							}
							?>
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset class="logify-wp-settings-group">
			<legend>Log record retention</legend>
			<table class="form-table logify-wp-settings-table">
				<tr valign="top">
					<th scope="row">How long to keep log records</th>
					<td>
						<?php
						$quantity = Plugin_Settings::get_keep_period_quantity();
						$units    = Plugin_Settings::get_keep_period_units();
						?>
						<select name="logify_wp_keep_period_quantity">
							<?php
							for ( $i = 1; $i <= 12; $i++ ) {
								echo '<option value="' . esc_attr( $i ) . '" ' . selected( $quantity, $i ) . '>' . esc_html( $i ) . '</option>';
							}
							?>
						</select>
						<select name="logify_wp_keep_period_units">
							<option value="day" <?php selected( $units, 'day' ); ?>>days</option>
							<option value="week" <?php selected( $units, 'week' ); ?>>weeks</option>
							<option value="month" <?php selected( $units, 'month' ); ?>>months</option>
							<!-- <option value="year" <?php selected( $units, 'year' ); ?>>years</option> -->
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Delete all data when uninstalling<br>(drop tables)</th>
					<td><input type="checkbox" name="logify_wp_delete_on_uninstall" value="1" <?php echo checked( Plugin_Settings::get_delete_on_uninstall(), 1 ); ?> /></td>
				</tr>
				<tr valign="top">
					<th>
						<?php
						// Get the URL for resetting the logs.
						$reset_logs_url = wp_nonce_url(
							admin_url( 'admin-post.php?action=logify_wp_reset_logs' ),
							'logify_wp_reset_logs_action',
							'logify_wp_nonce'
						);
						?>
						<a id="logify-wp-delete-logs-button" class="button button-secondary logify-wp-settings-button" href="<?php echo esc_url( $reset_logs_url ); ?>" onclick="return confirm('Are you sure you want to delete all the log records? This action cannot be undone.');">Delete all log records now (empty tables)</a>
					</th>
					<td>&nbsp;</td>
				</tr>
			</table>
		</fieldset>

		<fieldset class="logify-wp-settings-group">
			<legend>Additional settings</legend>
			<table class="form-table logify-wp-settings-table">
				<tr valign="top">
					<th scope="row">Roles to track</th>
					<td>
						<?php
						$roles                   = wp_roles()->roles;
						$selected_roles_to_track = Plugin_Settings::get_roles_to_track();
						foreach ( $roles as $role_key => $role ) {
							$checked = in_array( $role_key, $selected_roles_to_track, true ) ? 'checked' : '';
							echo '<label>';
							echo '<input type="checkbox" name="logify_wp_roles_to_track[]" value="' . esc_attr( $role_key ) . '" ' . esc_attr( $checked ) . '> ';
							echo esc_html( $role['name'] );
							echo '</label><br>';
						}
						?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Show submenu in admin bar</th>
					<td><input type="checkbox" name="logify_wp_show_in_admin_bar" value="1" <?php echo checked( Plugin_Settings::get_show_in_admin_bar(), 1 ); ?> /></td>
				</tr>
			</table>
		</fieldset>

		<?php submit_button( name: 'logify-wp-submit-button' ); ?>

		<?php
		// Display the 'Migrate data from WP Logify' button if the old plugin is active.
		if ( is_plugin_active( 'wp-logify/wp-logify.php' ) ) :
			// Get the URL for migrating data from the old WP Logify plugin.
			$migrate_data_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=logify_wp_migrate_data' ),
				'logify_wp_migrate_data_action',
				'logify_wp_nonce'
			);
			?>
			<a id='logify-wp-migrate-data-button' class='button button-action' href='<?php echo esc_url( $migrate_data_url ); ?>' onclick='return confirm("Are you sure you want to migrate the data from the old WP Logify plugin to the new Logify WP plugin? This will remove all log records in the new plugin.");'>Migrate data from WP Logify</a>
		<?php endif; ?>

	</form>
</div>
