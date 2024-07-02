<?php
/**
 * Settings page template.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

?>

<div class="wrap">
	<h1>WP Logify Settings</h1>
	<form method="post" action="options.php">
		<?php settings_fields( 'wp_logify_settings_group' ); ?>
		<?php do_settings_sections( 'wp_logify_settings_group' ); ?>
		<table class="form-table wp-logify-settings-table">
			<tr valign="top">
				<th scope="row">API Key</th>
				<td><input type="text" name="wp_logify_api_key" value="<?php echo esc_attr( Settings::get_api_key() ); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">How long to keep records</th>
				<td>
					<?php
					$keep_forever = Settings::get_keep_forever();
					$quantity     = Settings::get_keep_period_quantity();
					$units        = Settings::get_keep_period_units();
					?>
					<label class="wp-logify-settings-radio">
						<input type="radio" name="wp_logify_keep_forever" value="true" <?php checked( $keep_forever, true ); ?>> Forever
					</label>
					<label class="wp-logify-settings-radio">
						<input type="radio" name="wp_logify_keep_forever" value="false" <?php checked( $keep_forever, false ); ?>>
						<select name="wp_logify_keep_period_quantity">
							<?php
							for ( $i = 1; $i <= 10; $i++ ) {
								echo '<option value="' . $i . '" ' . selected( $quantity, $i ) . '>' . $i . '</option>';
							}
							?>
						</select>
						<select name="wp_logify_keep_period_units">
							<option value="day" <?php selected( $units, 'day' ); ?>>days</option>
							<option value="week" <?php selected( $units, 'week' ); ?>>weeks</option>
							<option value="month" <?php selected( $units, 'month' ); ?>>months</option>
							<option value="year" <?php selected( $units, 'year' ); ?>>years</option>
						</select>
					</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Roles to track</th>
				<td>
					<?php
					$roles                   = wp_roles()->roles;
					$selected_roles_to_track = Settings::get_roles_to_track();
					foreach ( $roles as $role_key => $role ) {
						$checked = in_array( $role_key, $selected_roles_to_track, true ) ? 'checked' : '';
						echo '<label><input type="checkbox" name="wp_logify_roles_to_track[]" value="' . esc_attr( $role_key ) . '" ' . $checked . '> ' . esc_html( $role['name'] ) . '</label><br>';
					}
					?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">WP-Cron Tracking</th>
				<td><input type="checkbox" name="wp_logify_wp_cron_tracking" value="1" <?php checked( Settings::get_wp_cron_tracking(), 1 ); ?> /></td>
			</tr>
			<tr valign="top">
				<th scope="row">Delete events log when uninstalling</th>
				<td><input type="checkbox" name="wp_logify_delete_on_uninstall" value="1" <?php checked( Settings::get_delete_on_uninstall(), 1 ); ?> /></td>
			</tr>
			<tr valign="top">
				<th scope="row">Delete all logs now</th>
				<td><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=wp_logify_reset_logs' ) ); ?>" onclick="return confirm('Are you sure you want to delete all the log records? This action cannot be undone.');">Do it</a></td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
