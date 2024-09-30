From class-plugin-settings.php

<?php

	/**
	 * The default value for the 'WP-Cron tracking' setting.
	 *
	 * @var bool
	 */
private const DEFAULT_WP_CRON_TRACKING = false;

	register_setting(
		'wp_logify_settings_group',
		'wp_logify_wp_cron_tracking',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => self::DEFAULT_WP_CRON_TRACKING,
		)
	);


	/**
	 * Retrieves the value of the 'WP-Cron tracking' setting.
	 *
	 * @return bool The WP-Cron tracking setting.
	 */
	public static function get_wp_cron_tracking(): bool {
		return get_option( 'wp_logify_wp_cron_tracking', self::DEFAULT_WP_CRON_TRACKING );
	}

	?>


From settings-page.php

<tr valign="top">
	<th scope="row">WP-Cron Tracking</th>
	<td><input type="checkbox" name="wp_logify_wp_cron_tracking" value="1" <?php checked( Plugin_Settings::get_wp_cron_tracking(), 1 ); ?> /></td>
</tr>
