From class-plugin-settings.php

<?php

	/**
	 * The default value for the 'API key' setting.
	 *
	 * @var string
	 */
private const DEFAULT_API_KEY = '';

register_setting(
	'wp_logify_settings_group',
	'wp_logify_api_key',
	array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => self::DEFAULT_API_KEY,
	)
);


	/**
	 * Retrieves the value of the 'API key' setting.
	 *
	 * @return string The API key.
	 */
public static function get_api_key(): string {
	return get_option( 'wp_logify_api_key', self::DEFAULT_API_KEY );
}
?>



From settings.scss

#wp-logify-api-key {
	width: 20rem;
}



From settings-page.php

<tr valign="top">
	<th scope="row">API Key</th>
	<td><input type="text" id="wp-logify-api-key" name="wp_logify_api_key" value="<?php echo esc_attr( Plugin_Settings::get_api_key() ); ?>" /></td>
</tr>
