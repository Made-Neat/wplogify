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
				<td><input type="text" name="wp_logify_api_key" value="<?php echo esc_attr( get_option( 'wp_logify_api_key' ) ); ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">Access control</th>
				<td>
					<label class="wp-logify-settings-radio">
						<input type="radio" name="wp_logify_access_control" value="only_me" <?php checked( get_option( 'wp_logify_access_control', 'only_me' ), 'only_me' ); ?>> Only me
					</label>
					<label class="wp-logify-settings-radio">
						<input type="radio" name="wp_logify_access_control" value="user_roles" <?php checked( get_option( 'wp_logify_access_control', 'only_me' ), 'user_roles' ); ?>> Other user roles
					</label>
				</td>
			</tr>
			<tr valign="top" id="wp_logify_roles_row" style="display: none;">
				<th scope="row">Roles that can view the plugin</th>
				<td>
					<?php
					$roles          = wp_roles()->roles;
					$selected_roles = get_option( 'wp_logify_view_roles', array( 'administrator' ) );
					foreach ( $roles as $role_key => $role ) {
						$checked = in_array( $role_key, $selected_roles, true ) ? 'checked' : '';
						echo '<label><input type="checkbox" name="wp_logify_view_roles[]" value="' . esc_attr( $role_key ) . '" ' . $checked . '> ' . esc_html( $role['name'] ) . '</label><br>';
					}
					?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">How long to keep records</th>
				<td>
					<?php
					$keep_forever = get_option( 'wp_logify_keep_forever', true );
					$quantity     = get_option( 'wp_logify_keep_period_quantity', 1 );
					$units        = get_option( 'wp_logify_keep_period_units', 'year' );
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
					$selected_roles_to_track = get_option( 'wp_logify_roles_to_track', array( 'administrator' ) );
					foreach ( $roles as $role_key => $role ) {
						$checked = in_array( $role_key, $selected_roles_to_track, true ) ? 'checked' : '';
						echo '<label><input type="checkbox" name="wp_logify_roles_to_track[]" value="' . esc_attr( $role_key ) . '" ' . $checked . '> ' . esc_html( $role['name'] ) . '</label><br>';
					}
					?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">WP Cron Tracking</th>
				<td><input type="checkbox" name="wp_logify_wp_cron_tracking" value="1" <?php checked( get_option( 'wp_logify_wp_cron_tracking' ), 1 ); ?> /></td>
			</tr>
			<tr valign="top">
				<th scope="row">Delete all logs when uninstalling</th>
				<td><input type="checkbox" name="wp_logify_delete_on_uninstall" value="1" <?php checked( get_option( 'wp_logify_delete_on_uninstall' ), 1 ); ?> /></td>
			</tr>
			<tr valign="top">
				<th scope="row">Delete all logs now</th>
				<td><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=wp_logify_reset_logs' ) ); ?>" onclick="return confirm('Are you sure you want to delete all the log records? This action cannot be undone.');">Do it</a></td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const accessControlRadios = document.querySelectorAll('input[name="wp_logify_access_control"]');
	const rolesRow = document.getElementById('wp_logify_roles_row');

	function toggleRolesRow() {
		const selectedValue = document.querySelector('input[name="wp_logify_access_control"]:checked').value;
		rolesRow.style.display = selectedValue === 'user_roles' ? 'table-row' : 'none';
	}

	accessControlRadios.forEach(radio => {
		radio.addEventListener('change', toggleRolesRow);
	});

	toggleRolesRow(); // Initial call to set the correct state on page load
});
</script>

<?php
// Add a settings section and field for role-based access
add_action(
	'admin_init',
	function () {
		add_settings_section(
			'wp_logify_settings_section',
			__( 'WP Logify Settings', 'wp-logify' ),
			function () {
				echo "<h2>WP Logify Settings</h2>\n";
			},
			'wp_logify_settings_group'
		);

		add_settings_field(
			'wp_logify_access_control',
			__( 'Access Control', 'wp-logify' ),
			function () {
				$access_control = get_option( 'wp_logify_access_control', 'only_me' );
				?>
				<label><input type="radio" name="wp_logify_access_control" value="only_me" <?php checked( $access_control, 'only_me' ); ?>> Only Me</label><br>
				<label><input type="radio" name="wp_logify_access_control" value="user_roles" <?php checked( $access_control, 'user_roles' ); ?>> Other user roles</label><br>
				<?php
			},
			'wp_logify_settings_group',
			'wp_logify_settings_section'
		);

		add_settings_field(
			'wp_logify_view_roles',
			__( 'Roles that can view the plugin', 'wp-logify' ),
			function () {
				$roles          = get_editable_roles();
				$selected_roles = get_option( 'wp_logify_view_roles', array( 'administrator' ) );
				foreach ( $roles as $role_key => $role ) {
					$checked = in_array( $role_key, $selected_roles, true ) ? 'checked' : '';
					echo '<label><input type="checkbox" name="wp_logify_view_roles[]" value="' . esc_attr( $role_key ) . '" ' . $checked . '> ' . esc_html( $role['name'] ) . '</label><br>';
				}
			},
			'wp_logify_settings_group',
			'wp_logify_settings_section'
		);

		register_setting( 'wp_logify_settings_group', 'wp_logify_access_control' );
		register_setting( 'wp_logify_settings_group', 'wp_logify_view_roles' );
	}
);
