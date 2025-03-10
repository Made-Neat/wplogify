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

        as_enqueue_async_action('middle_wp_login_failed', [$username, $error_s]); // Enqueue async action.

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
        if (!as_has_scheduled_action('middle_wp_loaded')) {
            //Get Current User
            $acting_user_id = get_current_user_id();
            as_enqueue_async_action('middle_wp_loaded', [$acting_user_id]); // Enqueue async action for WordPress loaded.
        }
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
        if (!as_has_scheduled_action('middle_update_option'))
            // Enqueues an async action for tracking option updates
            as_enqueue_async_action('middle_updated_option', [$option, $old_option_value, $new_option_value]);
    }

    /**
     * Tracks theme load event.
     *
     * No parameters or return value.
     */
    public static function async_load_themes(): void
    {
        if (!as_has_scheduled_action('middle_load_themes'))
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

    /**
     * Handle theme switch asynchronously.
     *
     * @param string   $new_name  The name of the new theme.
     * @param WP_Theme $new_theme The WP_Theme object of the new theme.
     * @param WP_Theme $old_theme The WP_Theme object of the old theme.
     */
    public static function async_switch_theme(string $new_name, WP_Theme $new_theme, WP_Theme $old_theme)
    {
        // Serialize the new theme object for storage or processing.
        $serialize_new_theme = serialize($new_theme);
        $serialize_old_theme = serialize($old_theme);

        // Enqueue an asynchronous action to handle the theme switch.
        as_enqueue_async_action('middle_switch_theme', [$new_name, $serialize_new_theme, $serialize_old_theme]);
    }

    /**
     * Handle theme deletion asynchronously.
     *
     * @param string $stylesheet The stylesheet name of the theme to delete.
     */
    public static function async_delete_theme(string $stylesheet)
    {
        // Enqueue an asynchronous action to handle the theme deletion.
        as_enqueue_async_action('middle_delete_theme', [$stylesheet]);
    }

    /**
     * Handle term creation asynchronously.
     *
     * @param int    $term_id   The term ID.
     * @param int    $tt_id     The term taxonomy ID.
     * @param string $taxonomy  The taxonomy for the term.
     * @param array  $args      Additional arguments for the term.
     */
    public static function async_created_term(int $term_id, int $tt_id, string $taxonomy, array $args)
    {
        // Enqueue an asynchronous action to handle the term creation.
        as_enqueue_async_action('middle_created_term', [$term_id, $tt_id, $taxonomy, $args]);
    }

    /**
     * Handle term editing asynchronously.
     *
     * @param int    $term_id   The term ID.
     * @param string $taxonomy  The taxonomy for the term.
     * @param array  $args      The arguments for the updated term.
     */
    public static function async_edit_terms(int $term_id, string $taxonomy, array $args)
    {
        // Enqueue an asynchronous action to handle the term editing.
        as_enqueue_async_action('middle_edit_terms', [$term_id, $taxonomy, $args]);
    }

    /**
     * Handle pre-term deletion asynchronously.
     *
     * @param int    $term_id   The term ID.
     * @param string $taxonomy  The taxonomy for the term.
     */
    public static function async_pre_delete_term(int $term_id, string $taxonomy)
    {
        // Enqueue an asynchronous action to handle the pre-deletion of the term.
        as_enqueue_async_action('middle_pre_delete_term', [$term_id, $taxonomy]);
    }

    /**
     * Handle post saving asynchronously.
     *
     * @param int    $post_id   The ID of the post being saved.
     * @param WP_Post $post     The WP_Post object of the post being saved.
     * @param bool   $update    Flag indicating if this is an update or new post.
     */
    public static function async_save_post(int $post_id, WP_Post $post, bool $update)
    {
        // Serialize the post object for storage or processing.
        $serialize_post = serialize($post);

        //Get current User ID
        $acting_user_id = get_current_user_id();

        // Enqueue an asynchronous action to handle the post save.
        if (!as_has_scheduled_action('middle_save_post', [$post_id, $serialize_post, $update, $acting_user_id])) {
            as_enqueue_async_action('middle_save_post', [$post_id, $serialize_post, $update, $acting_user_id]);
        }
    }

    /**
     * Handle pre-post update asynchronously.
     *
     * @param int   $post_id The ID of the post being updated.
     * @param array $data    The post data being updated.
     */
    public static function async_pre_post_update(int $post_id, array $data)
    {
        //Get current User ID
        $acting_user_id = get_current_user_id();
        // Enqueue an asynchronous action to handle the pre-update of the post.
        if (!as_has_scheduled_action('middle_pre_post_update', [$post_id, $data, $acting_user_id])) {
            as_enqueue_async_action('middle_pre_post_update', [$post_id, $data, $acting_user_id]);
        }
    }
    /**
     * Handle post update asynchronously.
     *
     * @param int     $post_id        The ID of the updated post.
     * @param WP_Post $post_after     The WP_Post object after the update.
     * @param WP_Post $post_before    The WP_Post object before the update.
     */
    public static function async_post_updated(int $post_id, WP_Post $post_after, WP_Post $post_before)
    {
        // Serialize the post_after and post_before objects for processing.
        $serialize_post_after = serialize($post_after);
        $serialize_post_before = serialize($post_before);

        //Get current User ID
        $acting_user_id = get_current_user_id();

        // Enqueue an asynchronous action to handle the post update.
        if (!as_has_scheduled_action('middle_post_updated', [$post_id, $serialize_post_after, $serialize_post_before, $acting_user_id])) {
            as_enqueue_async_action('middle_post_updated', [$post_id, $serialize_post_after, $serialize_post_before, $acting_user_id]);
        }
    }

    /**
     * Handle post meta update asynchronously.
     *
     * @param int    $meta_id   The ID of the meta.
     * @param int    $post_id   The ID of the post.
     * @param string $meta_key  The meta key.
     * @param mixed  $meta_value The meta value.
     */
    public static function async_update_post_meta(int $meta_id, int $post_id, string $meta_key, mixed $meta_value)
    {
        //Get current User ID
        $acting_user_id = get_current_user_id();
        // Enqueue an asynchronous action to handle the post meta update.
        if (!as_has_scheduled_action('middle_update_post_meta', [$meta_id, $post_id, $meta_key, $meta_value, $acting_user_id]))
            as_enqueue_async_action('middle_update_post_meta', [$meta_id, $post_id, $meta_key, $meta_value, $acting_user_id]);
    }

    /**
     * Handle post status transition asynchronously.
     *
     * @param string  $new_status The new post status.
     * @param string  $old_status The old post status.
     * @param WP_Post $post       The WP_Post object of the post.
     */
    public static function async_transition_post_status(string $new_status, string $old_status, WP_Post $post)
    {
        // Serialize the post object for processing.
        $serialize_post = serialize($post);

        //Get current User ID
        $acting_user_id = get_current_user_id();

        // Enqueue an asynchronous action to handle the post status transition.
        if (!as_has_scheduled_action('middle_transition_post_status', [$new_status, $old_status, $serialize_post, $acting_user_id]))
            as_enqueue_async_action('middle_transition_post_status', [$new_status, $old_status, $serialize_post, $acting_user_id]);
    }

    /**
     * Handle pre-post deletion asynchronously.
     *
     * @param int    $post_id The ID of the post to be deleted.
     * @param WP_Post $post   The WP_Post object of the post to be deleted.
     */
    public static function async_before_delete_post(int $post_id, WP_Post $post)
    {
        // Serialize the post object for processing.
        $serialize_post = serialize($post);
        //Get current User ID
        $acting_user_id = get_current_user_id();

        // Enqueue an asynchronous action to handle the pre-post deletion.
        if (!as_has_scheduled_action('middle_before_delete_post', [$post_id, $serialize_post, $acting_user_id]))
            as_enqueue_async_action('middle_before_delete_post', [$post_id, $serialize_post, $acting_user_id]);
    }

    /**
     * Handle post deletion asynchronously.
     *
     * @param int    $post_id The ID of the deleted post.
     * @param WP_Post $post   The WP_Post object of the deleted post.
     */
    public static function async_delete_post(int $post_id, WP_Post $post)
    {
        // Serialize the post object for processing.
        $serialize_post = serialize($post);

        // Enqueue an asynchronous action to handle the post deletion.
        as_enqueue_async_action('middle_deleted_post', [$post_id, $serialize_post]);
    }

    /**
     * Handle added term relationship asynchronously.
     *
     * @param int    $post_id   The ID of the post.
     * @param int    $tt_id     The term taxonomy ID.
     * @param string $taxonomy  The taxonomy of the term.
     */
    public static function async_added_term_relationship(int $post_id, int $tt_id, string $taxonomy)
    {
        // Enqueue an asynchronous action to handle the added term relationship.
        as_enqueue_async_action('middle_added_term_relationship', [$post_id, $tt_id, $taxonomy]);
    }

    /**
     * Handle post insertion asynchronously after the post is saved.
     *
     * @param int     $post_id       The ID of the inserted post.
     * @param WP_Post $post          The WP_Post object of the inserted post.
     * @param bool    $update        Flag indicating whether this is an update or new post.
     * @param WP_Post|null $post_before The WP_Post object before the post was saved (optional).
     */
    public static function async_wp_after_insert_post(int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before)
    {
        // Serialize the post and post_before objects for processing.
        $serialize_post = serialize($post);
        $serialize_post_before = serialize($post_before);

        //Get current User ID
        $acting_user_id = get_current_user_id();

        // Enqueue an asynchronous action to handle the post insert event.
        as_enqueue_async_action('middle_wp_after_insert_post', [$post_id, $serialize_post, $update, $serialize_post_before, $acting_user_id]);
    }

    /**
     * Handle deleted term relationships asynchronously.
     *
     * @param int   $post_id  The ID of the post.
     * @param array $tt_ids   An array of term taxonomy IDs.
     * @param string $taxonomy The taxonomy of the term.
     */
    public static function async_deleted_term_relationships(int $post_id, array $tt_ids, string $taxonomy)
    {
        // Enqueue an asynchronous action to handle the deleted term relationships.
        as_enqueue_async_action('middle_deleted_term_relationships', [$post_id, $tt_ids, $taxonomy]);
    }

    /**
     * Handle the theme upgrader process completion asynchronously.
     *
     * @param WP_Upgrader $upgrader    The WP_Upgrader instance handling the upgrade.
     * @param array       $hook_extra  Additional hook data for processing.
     */
    public static function async_upgrader_process_complete_theme(WP_Upgrader $upgrader, array $hook_extra)
    {
        // Filter only the necessary fields to avoid transferring large data.
        $upgrader_data = (object) [
            'new_theme_data' => $upgrader->new_theme_data,
            'result' => null,
            'skin' => null,
        ];

        // Check if the upgrader result is available and filter the relevant information.
        if (isset($upgrader->result)) {
            $upgrader_data->result = [
                'clear_destination' => $upgrader->result['clear_destination'],
            ];
        }

        // Create an object for skin data related to the theme upgrade.
        $upgrader_data->skin = (object) [
            'theme_info' => $upgrader->skin->theme_info
        ];

        // Enqueue an asynchronous action to handle the theme upgrade completion.
        as_enqueue_async_action('middle_upgrader_process_complete_theme', [$upgrader_data, $hook_extra]);
    }
    /**
     * Handle the plugin upgrader process completion asynchronously.
     *
     * @param WP_Upgrader $upgrader   The WP_Upgrader instance handling the upgrade.
     * @param array       $hook_extra Extra hook data for processing.
     */
    public static function async_upgrader_process_complete_plugin(WP_Upgrader $upgrader, array $hook_extra)
    {
        // Filter only the necessary fields to avoid transferring large data.
        $upgrader_data = (object) [
            'new_plugin_data' => $upgrader->new_plugin_data,
            'result' => null,
            'skin' => null,
        ];
        // Check if the upgrader result is available and filter the relevant information.
        if (isset($upgrader->result)) {
            $upgrader_data->result = [
                'clear_destination' => $upgrader->result['clear_destination'],
            ];
        }

        // Create an object for skin data related to the plugin upgrade.
        $upgrader_data->skin = (object) [
            'plugin_info' => $upgrader->skin->plugin_info
        ];

        //Get Acting User ID
        $acting_user_id = get_current_user_id();
        // Enqueue an asynchronous action to handle the plugin upgrade completion.
        as_enqueue_async_action('middle_upgrader_process_complete_plugin', [$upgrader_data, $hook_extra, $acting_user_id]);
    }

    /**
     * Handle plugin activation asynchronously.
     *
     * @param string $plugin_file   The plugin file path.
     * @param bool   $network_wide  Flag indicating if the plugin is activated network-wide.
     */
    public static function async_activate_plugin(string $plugin_file, bool $network_wide)
    {
        // Enqueue an asynchronous action to handle the plugin activation.
        as_enqueue_async_action('middle_activate_plugin', [$plugin_file, $network_wide]);
    }

    /**
     * Handle plugin deactivation asynchronously.
     *
     * @param string $plugin_file           The plugin file path.
     * @param bool   $network_deactivating  Flag indicating if the plugin is deactivated network-wide.
     */
    public static function async_deactivate_plugin(string $plugin_file, bool $network_deactivating)
    {
        // Enqueue an asynchronous action to handle the plugin deactivation.
        as_enqueue_async_action('middle_deactivate_plugin', [$plugin_file, $network_deactivating]);
    }

    /**
     * Handle plugin deletion asynchronously.
     *
     * @param string $plugin_file The plugin file path.
     */
    public static function async_delete_plugin(string $plugin_file)
    {
        // Load the plugin by its file path.
        $plugin = Plugin_Utility::load_by_file($plugin_file);

        // Enqueue an asynchronous action to handle the plugin deletion.
        as_enqueue_async_action('middle_delete_plugin', [$plugin]);
    }

    /**
     * Handle pre-uninstallation of a plugin asynchronously.
     *
     * @param string $plugin_file           The plugin file path.
     * @param array  $uninstallable_plugins List of plugins that are uninstallable.
     */
    public static function async_pre_uninstall_plugin(string $plugin_file, array $uninstallable_plugins)
    {
        // Load the plugin by its file path.
        $plugin = Plugin_Utility::load_by_file($plugin_file);

        // Enqueue an asynchronous action to handle the pre-uninstallation of the plugin.
        as_enqueue_async_action('middle_pre_uninstall_plugin', [$plugin, $uninstallable_plugins]);
    }

    /**
     * Handle plugin option update asynchronously.
     *
     * @param string $option   The option name.
     * @param mixed  $old_value The old value of the option.
     * @param mixed  $value    The new value of the option.
     */
    public static function async_update_option_plugin(string $option, mixed $old_value, mixed $value)
    {
        if (!as_has_scheduled_action('middle_update_option_plugin', [$option, $old_value, $value]))
            // Enqueue an asynchronous action to handle the plugin option update.
            as_enqueue_async_action('middle_update_option_plugin', [$option, $old_value, $value]);
    }

    /**
     * Handle general option update asynchronously.
     *
     * @param string $option   The option name.
     * @param mixed  $old_value The old value of the option.
     * @param mixed  $value    The new value of the option.
     */
    public static function async_update_option_option(string $option, mixed $old_value, mixed $value)
    {
        //Get current User ID
        $acting_user_id = get_current_user_id();

        if (!as_has_scheduled_action('middle_update_option_option', [$option, $old_value, $value, $acting_user_id]))
            // Enqueue an asynchronous action to handle the general option update.
            as_enqueue_async_action('middle_update_option_option', [$option, $old_value, $value, $acting_user_id]);
    }

    /**
     * Handle widget option update asynchronously.
     *
     * @param string $option   The option name.
     * @param mixed  $old_value The old value of the option.
     * @param mixed  $value    The new value of the option.
     */
    public static function async_update_option_widget(string $option, mixed $old_value, mixed $value)
    {
        //Get current User ID
        $acting_user_id = get_current_user_id();
        if (!as_has_scheduled_action('middle_update_option_widget', [$option, $old_value, $value, $acting_user_id]))
            // Enqueue an asynchronous action to handle the widget option update.
            as_enqueue_async_action('middle_update_option_widget', [$option, $old_value, $value, $acting_user_id]);
    }

    /**
     * Handle adding an attachment asynchronously.
     *
     * @param int $post_id The ID of the post the attachment is added to.
     */
    public static function async_add_attachment(int $post_id)
    {
        // Enqueue an asynchronous action to handle adding an attachment.
        as_enqueue_async_action('middle_add_attachment', [$post_id]);
    }

    /**
     * Handle adding post meta asynchronously.
     *
     * @param int    $post_id   The ID of the post.
     * @param string $meta_key  The meta key.
     * @param mixed  $meta_value The meta value.
     */
    public static function async_add_post_meta(int $post_id, string $meta_key, mixed $meta_value)
    {
        // Enqueue an asynchronous action to handle adding post meta.
        as_enqueue_async_action('middle_add_post_meta', [$post_id, $meta_key, $meta_value]);
    }
    /**
     * Handle attachment update asynchronously.
     *
     * @param int     $post_id        The ID of the updated post.
     * @param WP_Post $post_after     The WP_Post object after the update.
     * @param WP_Post $post_before    The WP_Post object before the update.
     */
    public static function async_attachment_updated(int $post_id, WP_Post $post_after, WP_Post $post_before)
    {
        // Serialize the post_after and post_before objects for processing.
        $serialize_post_after = serialize($post_after);
        $serialize_post_before = serialize($post_before);

        // Enqueue an asynchronous action to handle the attachment update.
        as_enqueue_async_action('middle_attachment_updated', [$post_id, $serialize_post_after, $serialize_post_before]);
    }

    /**
     * Handle attachment deletion asynchronously.
     *
     * @param int    $post_id The ID of the deleted post.
     * @param WP_Post $post   The WP_Post object of the deleted post.
     */
    public static function async_delete_attachment(int $post_id, WP_Post $post)
    {
        // Serialize the post object for processing.
        $serialize_post = serialize($post);

        // Enqueue an asynchronous action to handle the attachment deletion.
        as_enqueue_async_action('middle_delete_attachment', [$post_id, $serialize_post]);
    }

    /**
     * Handle core WordPress update completion asynchronously.
     *
     * @param string $wp_version The new WordPress version.
     */
    public static function async_core_updated_successfully(string $wp_version)
    {
        // Enqueue an asynchronous action to handle the successful core update.
        as_enqueue_async_action('middle_core_updated_successfully', [$wp_version]);
    }

    /**
     * Handle new comment insertion asynchronously.
     *
     * @param int       $id      The comment ID.
     * @param WP_Comment $comment The WP_Comment object of the inserted comment.
     */
    public static function async_wp_insert_comment(int $id, WP_Comment $comment)
    {
        if (!Plugin_Settings::get_comment_tracking_state()) {
            return;
        }
        // Serialize the comment object for processing.
        $serialize_comment = serialize($comment);

        // Enqueue an asynchronous action to handle the comment insertion.
        as_enqueue_async_action('middle_wp_insert_comment', [$id, $serialize_comment]);
    }

    /**
     * Handle comment data update asynchronously.
     *
     * @param array|WP_Error $data       The comment data or error.
     * @param array          $comment    The comment array.
     * @param array          $commentarr The array of comment data.
     */
    public static function async_wp_update_comment_data(array|WP_Error $data, array $comment, array $commentarr)
    {
        if (!Plugin_Settings::get_comment_tracking_state()) {
            return;
        }
        // Enqueue an asynchronous action to handle the comment data update.
        as_enqueue_async_action('middle_wp_update_comment_data', [$data, $comment, $commentarr]);
    }

    /**
     * Handle comment edit asynchronously.
     *
     * @param int   $comment_id The ID of the edited comment.
     * @param array $data       The edited comment data.
     */
    public static function async_edit_comment(int $comment_id, array $data)
    {
        if (!Plugin_Settings::get_comment_tracking_state()) {
            return;
        }
        // Enqueue an asynchronous action to handle the comment editing.
        as_enqueue_async_action('middle_edit_comment', [$comment_id, $data]);
    }

    /**
     * Handle comment deletion asynchronously.
     *
     * @param string   $comment_id The ID of the deleted comment.
     * @param WP_Comment $comment   The WP_Comment object of the deleted comment.
     */
    public static function async_delete_comment(string $comment_id, WP_Comment $comment)
    {
        if (!Plugin_Settings::get_comment_tracking_state()) {
            return;
        }
        // Serialize the comment object for processing.
        $serialize_comment = serialize($comment);

        // Enqueue an asynchronous action to handle the comment deletion.
        as_enqueue_async_action('middle_delete_comment', [$comment_id, $serialize_comment]);
    }

    /**
     * Handle comment status transition asynchronously.
     *
     * @param int|string $new_status The new status of the comment.
     * @param int|string $old_status The old status of the comment.
     * @param WP_Comment $comment    The WP_Comment object of the comment.
     */
    public static function async_transition_comment_status(int|string $new_status, int|string $old_status, WP_Comment $comment)
    {
        if (!Plugin_Settings::get_comment_tracking_state()) {
            return;
        }
        // Serialize the comment object for processing.
        $serialize_comment = serialize($comment);

        // Enqueue an asynchronous action to handle the comment status transition.
        as_enqueue_async_action('middle_transition_comment_status', [$new_status, $old_status, $serialize_comment]);
    }

    /**
     * Handle trashed post comments asynchronously.
     *
     * @param int   $post_id The ID of the post.
     * @param array $statuses The statuses of the trashed comments.
     */
    public static function async_trashed_post_comments(int $post_id, array $statuses)
    {
        if (!Plugin_Settings::get_comment_tracking_state()) {
            return;
        }
        // Enqueue an asynchronous action to handle trashed post comments.
        as_enqueue_async_action('middle_trashed_post_comments', [$post_id, $statuses]);
    }

    /**
     * Handle untrashing post comments asynchronously.
     *
     * @param int $post_id The ID of the post.
     */
    public static function async_untrash_post_comments(int $post_id)
    {
        if (!Plugin_Settings::get_comment_tracking_state()) {
            return;
        }
        // Enqueue an asynchronous action to handle untrashing post comments.
        as_enqueue_async_action('middle_untrash_post_comments', [$post_id]);
    }
}
