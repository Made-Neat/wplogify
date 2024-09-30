From class-cron.php, cleanup_old_records() method

<?php
// Check if we need to delete any old records.
if ( Plugin_Settings::get_keep_forever() ) {
	return;
}
?>



From class-plugin-settings.php

<?php


	/**
	 * The default value for the 'keep forever' setting.
	 *
	 * @var bool
	 */
private const DEFAULT_KEEP_FOREVER = true;


	register_setting(
		'wp_logify_settings_group',
		'wp_logify_keep_forever',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => self::DEFAULT_KEEP_FOREVER,
		)
	);



	/**
	 * Retrieves the value of the 'keep forever' setting.
	 *
	 * @return bool The keep forever setting.
	 */
	public static function get_keep_forever(): bool {
		return get_option( 'wp_logify_keep_forever', self::DEFAULT_KEEP_FOREVER );
	}
	?>


From settings-page.php

<?php
$keep_forever = Plugin_Settings::get_keep_forever();
?>

<label class="wp-logify-settings-radio">
							<input type="radio" name="wp_logify_keep_forever" value="true" <?php checked( $keep_forever, true ); ?>> Forever
						</label>
						<label class="wp-logify-settings-radio">
							<input type="radio" name="wp_logify_keep_forever" value="false" <?php checked( $keep_forever, false ); ?>>
						</label>
