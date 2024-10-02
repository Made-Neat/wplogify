<?php
/**
 * Settings page template.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

?>

<div class="wrap">
	<h1>Logify WP Settings</h1>
	<form method="post" action="options.php">
		<?php settings_fields( 'logify_wp_settings_group' ); ?>
		<?php do_settings_sections( 'logify_wp_settings_group' ); ?>

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
							echo "<label><input type='checkbox' name='logify_wp_roles_with_access[]' value='" . esc_attr( $role_key ) . "' $checked $disabled> " . esc_html( $role['name'] ) . '</label><br>';
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
								$checked         = Access_Control::user_has_individual_access( $user ) ? 'checked' : '';
								$role_access_msg = Access_Control::user_has_access_via_role( $user ) ? ' <span class="logify-wp-role-access-msg">(has access via role)</span>' : '';
								echo "<label><input type='checkbox' name='logify_wp_users_with_access[]' value='{$user->ID}' $checked> " . esc_html( User_Utility::get_name( $user->ID ) ) . "$role_access_msg</label><br>";
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
								echo '<option value="' . $i . '" ' . selected( $quantity, $i ) . '>' . $i . '</option>';
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
					<th scope="row">Delete all data when uninstalling</th>
					<td><input type="checkbox" name="logify_wp_delete_on_uninstall" value="1" <?php checked( Plugin_Settings::get_delete_on_uninstall(), 1 ); ?> /></td>
				</tr>
				<tr valign="top">
					<th>
						<a id="logify-wp-delete-logs-button" class="button button-secondary logify-wp-settings-button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=logify_wp_reset_logs' ) ); ?>" onclick="return confirm('Are you sure you want to delete all the log records? This action cannot be undone.');">Delete all log records now</a>
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
							echo '<label><input type="checkbox" name="logify_wp_roles_to_track[]" value="' . esc_attr( $role_key ) . '" ' . $checked . '> ' . esc_html( $role['name'] ) . '</label><br>';
						}
						?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Show submenu in admin bar</th>
					<td><input type="checkbox" name="logify_wp_show_in_admin_bar" value="1" <?php checked( Plugin_Settings::get_show_in_admin_bar(), 1 ); ?> /></td>
				</tr>
			</table>
		</fieldset>

		<?php submit_button( name: 'logify-wp-submit-button' ); ?>
	</form>
</div>
