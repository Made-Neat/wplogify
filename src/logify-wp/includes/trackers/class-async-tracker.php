<?php
/**
 * Contains the User_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use WP_Error;
use WP_User;
use WP_Upgrader;
use WP_Theme;
use WP_Post;
use WP_Comment;

/**
 * Class Logify_WP\User_Tracker
 *
 * Provides tracking of events related to users.
 */

class Async_Tracker
{

	public static function async_wp_login(string $user_login, WP_User $user)
    {
        as_enqueue_async_action('middle_wp_login', [$user_login, $user->ID]); // Enqueue async action for login.
    }

    /**
     * Handles async login failure tracking.
     *
     * @param string   $username The username attempted.
     * @param WP_Error $error    The login error object.
     */
    public static function async_wp_login_failed(string $username, WP_Error $error)
    {
        $error_s = serialize($error); // Serialize error object.
        
        // Ensure action is not already scheduled.
        if (!as_has_scheduled_action('middle_wp_login_failed', [$username, $error_s])) {
            as_enqueue_async_action('middle_wp_login_failed', [$username, $error_s]); // Enqueue async action.
        }
    }

    /**
     * Handles async logout tracking.
     *
     * @param int $user_id The ID of the user logging out.
     */
    public static function async_wp_logout(int $user_id)
    {
        as_enqueue_async_action('middle_wp_logout', [$user_id]); // Enqueue async action for logout.
    }

    /**
     * Handles async WordPress load tracking.
     */
    public static function async_wp_loaded()
    {
        as_enqueue_async_action('middle_wp_loaded'); // Enqueue async action for WordPress loaded.
    }

    /**
     * Handles async user registration tracking.
     *
     * @param int   $user_id  The ID of the registered user.
     * @param array $userdata The user data array.
     */
    public static function async_user_register(int $user_id, array $userdata)
    {
        as_enqueue_async_action('middle_user_register', [$user_id, $userdata]); // Enqueue async action for user registration.
    }

    /**
     * Handles async user deletion tracking.
     *
     * @param int      $user_id  The ID of the user being deleted.
     * @param int|null $reassign The user ID to reassign posts to, if applicable.
     * @param WP_User  $user     The user object before deletion.
     */
    public static function async_delete_user(int $user_id, ?int $reassign, WP_User $user)
    {
        $serialize_user = serialize($user); // Serialize user object.
        as_enqueue_async_action('middle_delete_user', [$user_id, $reassign, $serialize_user]); // Enqueue async action for user deletion.
    }

    /**
     * Handles async user profile update tracking.
     *
     * @param int     $user_id  The ID of the user being updated.
     * @param WP_User $user     The updated user object.
     * @param array   $userdata The updated user data array.
     */
    public static function async_profile_update(int $user_id, WP_User $user, array $userdata)
    {
        $serialize_user = serialize($user); // Serialize user object.
        as_enqueue_async_action('middle_profile_update', [$user_id, $serialize_user, $userdata]); // Enqueue async action.
    }

    /**
     * Handles async user metadata update tracking.
     *
     * @param int    $meta_id    The ID of the metadata.
     * @param int    $user_id    The ID of the user.
     * @param string $meta_key   The metadata key.
     * @param mixed  $meta_value The metadata value.
     */
    public static function async_update_user_meta(int $meta_id, int $user_id, string $meta_key, mixed $meta_value)
    {
        as_enqueue_async_action('middle_update_user_meta', [$meta_id, $user_id, $meta_key, $meta_value]); // Enqueue async action.
    }
    
    /**
     * Handles async WordPress shutdown event tracking.
     */
    public static function async_shutdown_media()
    {
        as_enqueue_async_action('middle_shutdown_media'); // Enqueue async action for shutdown media.
    }
	/**
     * Tracks WordPress shutdown event for options.
     *
     * No parameters or return value.
     */
    public static function async_shutdown_option(): void
    {
        // Enqueues an async action for tracking option shutdown event
        as_enqueue_async_action('middle_shutdown_option');
    }

    /**
     * Tracks WordPress shutdown event for posts.
     *
     * No parameters or return value.
     */
    public static function async_shutdown_post(): void
    {
        // Enqueues an async action for tracking post shutdown event
        as_enqueue_async_action('middle_shutdown_post');
    }

    /**
     * Tracks WordPress shutdown event for users.
     *
     * No parameters or return value.
     */
    public static function async_shutdown_user(): void
    {
        // Enqueues an async action for tracking user shutdown event
        as_enqueue_async_action('middle_shutdown_user');
    }

    /**
     * Tracks WordPress shutdown event for widgets.
     *
     * No parameters or return value.
     */
    public static function async_shutdown_widget(): void
    {
        // Enqueues an async action for tracking widget shutdown event
        as_enqueue_async_action('middle_shutdown_widget');
    }

    /**
     * Tracks updates to WordPress options.
     *
     * @param string $option           The name of the updated option.
     * @param mixed  $old_option_value The old value of the option.
     * @param mixed  $new_option_value The new value of the option.
     *
     * No return value.
     */
    public static function async_updated_option(string $option, mixed $old_option_value, mixed $new_option_value): void
    {
        // Enqueues an async action for tracking option updates
        // Includes the option name, old value, and new value
        as_enqueue_async_action('middle_updated_option', [$option, $old_option_value, $new_option_value]);
    }

    /**
     * Tracks theme load event.
     *
     * No parameters or return value.
     */
    public static function async_load_themes(): void
    {
        // Enqueues an async action for tracking theme load event
        as_enqueue_async_action('middle_load_themes');
    }

    /**
     * Tracks theme installation event.
     *
     * No parameters or return value.
     */
    public static function async_load_theme_install(): void
    {
        // Enqueues an async action for tracking theme installation event
        as_enqueue_async_action('middle_load_theme_install');
    }

	public static function async_switch_theme(string $new_name, WP_Theme $new_theme, WP_Theme $old_theme)
	{
		//serialize theme object
		$serialize_new_theme = serialize($new_theme);
		$serialize_old_theme = serialize($old_theme);
		//async request
		as_enqueue_async_action('middle_switch_theme', [$new_name, $serialize_new_theme, $serialize_old_theme]);
	}

	public static function async_delete_theme(string $stylesheet)
	{

		//async request
		as_enqueue_async_action('middle_delete_theme', [$stylesheet]);

	}
	public static function async_created_term(int $term_id, int $tt_id, string $taxonomy, array $args)
	{

		//async request
		as_enqueue_async_action('middle_created_term', [$term_id, $tt_id, $taxonomy, $args]);

	}
	public static function async_edit_terms(int $term_id, string $taxonomy, array $args)
	{

		//async request
		as_enqueue_async_action('middle_edit_terms', [$term_id, $taxonomy, $args]);

	}
	public static function async_pre_delete_term(int $term_id, string $taxonomy)
	{

		//async request
		as_enqueue_async_action('middle_pre_delete_term', [$term_id, $taxonomy]);

	}
	public static function async_save_post(int $post_id, WP_Post $post, bool $update)
	{
		//serialize post object
		$serialize_post = serialize($post);

		//async request
		as_enqueue_async_action('middle_save_post', [$post_id, $serialize_post, $update]);

	}
	public static function async_pre_post_update(int $post_id, array $data)
	{

		//async request
		as_enqueue_async_action('middle_pre_post_update', [$post_id, $data]);

	}
	public static function async_post_updated(int $post_id, WP_Post $post_after, WP_Post $post_before)
	{

		//serialize post_after, post_before object
		$serialize_post_after = serialize($post_after);
		$serialize_post_before = serialize($post_before);

		//async request
		as_enqueue_async_action('middle_post_updated', [$post_id, $serialize_post_after, $serialize_post_before]);

	}
	public static function async_update_post_meta(int $meta_id, int $post_id, string $meta_key, mixed $meta_value)
	{

		//async request
		as_enqueue_async_action('middle_update_post_meta', [$meta_id, $post_id, $meta_key, $meta_value]);

	}
	public static function async_transition_post_status(string $new_status, string $old_status, WP_Post $post)
	{
		//serialize post object
		$serialize_post = serialize($post);
		//async request
		as_enqueue_async_action('middle_transition_post_status', [$new_status, $old_status, $serialize_post]);

	}
	public static function async_before_delete_post(int $post_id, WP_Post $post)
	{
		//serialize post object
		$serialize_post = serialize($post);
		//async request
		as_enqueue_async_action('middle_before_delete_post', [$post_id, $serialize_post]);

	}
	public static function async_delete_post(int $post_id, WP_Post $post)
	{
		//serialize post object
		$serialize_post = serialize($post);
		//async request
		as_enqueue_async_action('middle_deletd_post', [$post_id, $serialize_post]);

	}
	public static function async_added_term_relationship(int $post_id, int $tt_id, string $taxonomy)
	{

		//async request
		as_enqueue_async_action('middle_added_term_relationship', [$post_id, $tt_id, $taxonomy]);

	}
	public static function async_wp_after_insert_post(int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before)
	{

		//serialize post object
		$serialize_post = serialize($post);
		$serialize_post_before = serialize($post_before);
		//async request
		as_enqueue_async_action('middle_wp_after_insert_post', [$post_id, $serialize_post, $update, $serialize_post_before]);

	}
	public static function async_deleted_term_relationships(int $post_id, array $tt_ids, string $taxonomy)
	{

		//async request
		as_enqueue_async_action('middle_deleted_term_relationships', [$post_id, $tt_ids, $taxonomy]);

	}
	public static function async_upgrader_process_complete_theme(WP_Upgrader $upgrader, array $hook_extra)
	{

		// filter only needed field because it has too long to transfer with parameter
		$upgrader_data = (object) [
			'new_theme_data' => $upgrader->new_theme_data,
			'result' => NULL,
			'skin' => NULL,
		];
		if (isset($upgrader->result)) {
			$upgrader_data->result = [
				'clear_destination' => $upgrader->result['clear_destination'],
			];
		}
		$upgrader_data->skin = (object) [
			'theme_info' => $upgrader->skin->theme_info
		];
		//async request
		as_enqueue_async_action('middle_upgrader_process_complete_theme', [$upgrader_data, $hook_extra]);

	}

	public static function async_upgrader_process_complete_plugin(WP_Upgrader $upgrader, array $hook_extra)
	{
		// filter only needed field because it has too long to transfer with parameter
		$upgrader_data = (object) [
			'new_plugin_data' => $upgrader->new_plugin_data,
			'result' => NULL,
			'skin' => NULL,
		];
		if (isset($upgrader->result)) {
			$upgrader_data->result = [
				'clear_destination' => $upgrader->result['clear_destination'],
			];
		}
		$upgrader_data->skin = (object) [
			'plugin_info' => $upgrader->skin->plugin_info
		];

		as_enqueue_async_action('middle_upgrader_process_complete_plugin', [$upgrader_data, $hook_extra]);
		
	}
	public static function async_activate_plugin(string $plugin_file, bool $network_wide)
	{

		//async request
		as_enqueue_async_action('middle_activate_plugin', [$plugin_file, $network_wide]);

	}
	public static function async_deactivate_plugin(string $plugin_file, bool $network_deactivating)
	{

		//async request
		as_enqueue_async_action('middle_deactivate_plugin', [$plugin_file, $network_deactivating]);

	}
	public static function async_delete_plugin(string $plugin_file)
	{
		//Load plugin		
		$plugin = Plugin_Utility::load_by_file($plugin_file);
		//async request
		as_enqueue_async_action('middle_delete_plugin', [$plugin]);

	}
	public static function async_pre_uninstall_plugin(string $plugin_file, array $uninstallable_plugins)
	{
		// Load the plugin.
		$plugin = Plugin_Utility::load_by_file($plugin_file);
		//async request
		as_enqueue_async_action('middle_pre_uninstall_plugin', [$plugin, $uninstallable_plugins]);

	}
	public static function async_update_option_plugin(string $option, mixed $old_value, mixed $value)
	{

		//async request
		as_enqueue_async_action('middle_update_option_plugin', [$option, $old_value, $value]);

	}
	public static function async_update_option_option(string $option, mixed $old_value, mixed $value)
	{
		//async request
		as_enqueue_async_action('middle_update_option_option', [$option, $old_value, $value]);

	}
	public static function async_update_option_widget(string $option, mixed $old_value, mixed $value)
	{

		//async request
		as_enqueue_async_action('middle_update_option_widget', [$option, $old_value, $value]);

	}
	public static function async_add_attachment(int $post_id)
	{

		//async request
		as_enqueue_async_action('middle_add_attachment', [$post_id]);

	}
	public static function async_add_post_meta(int $post_id, string $meta_key, mixed $meta_value)
	{

		//async request
		as_enqueue_async_action('middle_add_post_meta', [$post_id, $meta_key, $meta_value]);

	}
	public static function async_attachment_updated(int $post_id, WP_Post $post_after, WP_Post $post_before)
	{

		//serialize post objects
		$serialize_post_after = serialize($post_after);
		$serialize_post_before = serialize($post_before);
		//async request
		as_enqueue_async_action('middle_attachment_updated', [$post_id, $serialize_post_after, $serialize_post_before]);

	}
	public static function async_delete_attachment(int $post_id, WP_Post $post)
	{
		//serialize Post object
		$serialize_post = serialize($post);
		//async request
		as_enqueue_async_action('middle_delete_attachment', [$post_id, $serialize_post]);

	}
	public static function async_core_updated_successfully(string $wp_version)
	{

		//async request
		as_enqueue_async_action('middle_core_updated_successfully', [$wp_version]);

	}
	public static function async_wp_insert_comment(int $id, WP_Comment $comment)
	{

		//serialize comment object
		$serialize_comment = serialize($comment);
		//async request
		as_enqueue_async_action('middle_wp_insert_comment', [$id, $serialize_comment]);

	}
	public static function async_wp_update_comment_data(array|WP_Error $data, array $comment, array $commentarr)
	{
		//async request
		as_enqueue_async_action('middle_wp_update_comment_data', [$data, $comment, $commentarr]);

	}
	public static function async_edit_comment(int $comment_id, array $data)
	{

		//async request
		as_enqueue_async_action('middle_edit_comment', [$comment_id, $data]);

	}
	public static function async_delete_comment(string $comment_id, WP_Comment $comment)
	{

		//serialize comment object
		$serialize_comment = serialize($comment);
		//async request
		as_enqueue_async_action('middle_delete_comment', [$comment_id, $serialize_comment]);

	}
	public static function async_transition_comment_status(int|string $new_status, int|string $old_status, WP_Comment $comment)
	{
		//serialize comment object
		$serialize_comment = serialize($comment);
		//async request
		as_enqueue_async_action('middle_transition_comment_status', [$new_status, $old_status, $serialize_comment]);

	}
	public static function async_trashed_post_comments(int $post_id, array $statuses)
	{

		//async request
		as_enqueue_async_action('middle_trashed_post_comments', [$post_id, $statuses]);

	}
	public static function async_untrash_post_comments(int $post_id)
	{

		//async request
		as_enqueue_async_action('middle_untrash_post_comments', [$post_id]);

	}

}
