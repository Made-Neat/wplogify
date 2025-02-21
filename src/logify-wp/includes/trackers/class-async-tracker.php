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

class Async_Tracker {


    
	public static function async_wp_login(string $user_login, WP_User $user){
		
		//async request
		$s_user = serialize($user);
		as_schedule_single_action(time(), 'middle_wp_login', [$user_login, $s_user]);
		
	}public static function async_wp_login_failed(string $username, WP_Error $error){
		
		//async request
		$s_error = serialize($error);

		as_schedule_single_action(time(), 'middle_wp_login_failed', [$username, $s_error]);
		
	}public static function async_wp_logout(int $user_id){
		
		//async request
		as_schedule_single_action(time(), 'middle_wp_logout', [$user_id]);
		
	}
    

    public static function async_wp_loaded(){
		
		//async request
		as_schedule_single_action(time(), 'middle_wp_loaded');
		
	}

    public static function async_user_register(int $user_id, array $userdata){
		
		//async request
		as_schedule_single_action(time(), 'middle_user_register', [$user_id, $userdata]);
		
	}

    public static function async_delete_user(int $user_id, ?int $reassign, WP_User $user){
		
		//async request
		$s_user = serialize($user);
		as_schedule_single_action(time(), 'middle_delete_user', [$user_id, $reassign, $s_user]);
		
	}

    public static function async_profile_update(int $user_id, WP_User $user, array $userdata){
		
		//async request
		$s_user = serialize($user);
		as_schedule_single_action(time(), 'middle_profile_update', [$user_id, $s_user, $userdata]);
		
	}

    public static function async_update_user_meta(int $meta_id, int $user_id, string $meta_key, mixed $meta_value){
		
		//async request
		as_schedule_single_action(time(), 'middle_update_user_meta', [$meta_id, $user_id, $meta_key, $meta_value]);
		
	}

    public static function async_shutdown(){
		
		//async request
		as_schedule_single_action(time(), 'middle_shutdown');
		
	}

    public static function async_updated_option($option, $old_option_value, $new_option_value ){
		
		//async request
		as_schedule_single_action(time(), 'middle_updated_option', [$option, $old_option_value, $new_option_value ]);
		
	}
    
    public static function async_load_themes(){
		
		//async request
		as_schedule_single_action(time(), 'middle_load-themes.php');
		
	}

    public static function async_load_theme_install(){
		
		//async request
		as_schedule_single_action(time(), 'middle_load-theme-install.php');
		
	}

    public static function async_switch_theme(string $new_name, WP_Theme $new_theme, WP_Theme $old_theme){
		
		//async request
		$s_new_theme = serialize($new_theme);
		$s_old_theme = serialize($old_theme);

		as_schedule_single_action(time(), 'middle_switch_theme', [$new_name, $s_new_theme, $s_old_theme]);
    }

    public static function async_delete_theme(string $stylesheet){
		
		//async request
		as_schedule_single_action(time(), 'middle_delete_theme', [$stylesheet]);
		
	}
    public static function async_created_term(int $term_id, int $tt_id, string $taxonomy, array $args){
		
		//async request
		as_schedule_single_action(time(), 'middle_created_term', [$term_id, $tt_id, $taxonomy, $args]);
		
	}
    public static function async_edit_terms(int $term_id, string $taxonomy, array $args){
		
		//async request
		as_schedule_single_action(time(), 'middle_edit_terms', [$term_id, $taxonomy, $args]);
		
	}
    public static function async_pre_delete_term(int $term_id, string $taxonomy ){
		
		//async request
		as_schedule_single_action(time(), 'middle_pre_delete_term', [$term_id, $taxonomy]);
		
	}
    public static function async_save_post(int $post_id, WP_Post $post, bool $update ){
		
		//async request
		$s_post = serialize($post);
		as_schedule_single_action(time(), 'middle_save_post', [$post_id, $s_post, $update]);
		
	}
    public static function async_pre_post_update(int $post_id, array $data ){
		
		//async request
		as_schedule_single_action(time(), 'middle_pre_post_update', [$post_id, $data]);
		
	}
    public static function async_post_updated(int $post_id, WP_Post $post_after, WP_Post $post_before  ){
		
		//async request
		$s_post_after = serialize($post_after);
		$s_post_before = serialize($post_before);
		as_schedule_single_action(time(), 'middle_post_updated', [$post_id, $s_post_after, $s_post_before]);
		
	}
    public static function async_update_post_meta(int $meta_id, int $post_id, string $meta_key, mixed $meta_value  ){
		
		//async request
		as_schedule_single_action(time(), 'middle_update_post_meta', [$meta_id, $post_id, $meta_key, $meta_value]);
		
	}
    public static function async_transition_post_status(string $new_status, string $old_status, WP_Post $post ){
		
		//async request
		$s_post = serialize($post);
		as_schedule_single_action(time(), 'middle_transition_post_status', [$new_status, $old_status, $s_post]);
		
	}
    public static function async_before_delete_post(int $post_id, WP_Post $post ){
		
		//async request
		$s_post = serialize($post);
		as_schedule_single_action(time(), 'middle_before_delete_post', [$post_id, $s_post]);
		
	}
    public static function async_delete_post(int $post_id, WP_Post $post ){
		
		//async request
		$s_post = serialize($post);
		as_schedule_single_action(time(), 'middle_deletd_post', [$post_id, $s_post]);
		
	}
    public static function async_added_term_relationship(int $post_id, int $tt_id, string $taxonomy ){
		
		//async request
		as_schedule_single_action(time(), 'middle_added_term_relationship', [$post_id, $tt_id, $taxonomy]);
		
	}
    public static function async_wp_after_insert_post( int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before){
		
		//async request
		$s_post = serialize($post);
		$s_post_before = serialize($post_before);
		as_schedule_single_action(time(), 'middle_wp_after_insert_post', [$post_id, $s_post, $update, $s_post_before]);
		
	}
    public static function async_deleted_term_relationships(int $post_id, array $tt_ids, string $taxonomy ){
		
		//async request
		as_schedule_single_action(time(), 'middle_deleted_term_relationships', [$post_id, $tt_ids, $taxonomy]);
		
	}
    public static function async_upgrader_process_complete(WP_Upgrader $upgrader, array $hook_extra ){
		
		//async request
		$s_upgrader = serialize($upgrader);
		as_schedule_single_action(time(), 'middle_upgrader_process_complete', [$s_upgrader, $hook_extra]);
		
	}
    public static function async_activate_plugin(WP_Upgrader $plugin_file, array $network_wide ){
		
		//async request
		$s_plugin_file = serialize($plugin_file);
		as_schedule_single_action(time(), 'middle_activate_plugin', [$s_plugin_file, $network_wide]);
		
	}
    public static function async_deactivate_plugin(string $plugin_file, bool $network_deactivating ){
		
		//async request
		as_schedule_single_action(time(), 'middle_deactivate_plugin', [$plugin_file, $network_deactivating]);
		
	}
    public static function async_delete_plugin(string $plugin_file ){
		
		//async request
		as_schedule_single_action(time(), 'middle_delete_plugin', [$plugin_file]);
		
	}
    public static function async_pre_uninstall_plugin(string $plugin_file, array $uninstallable_plugins ){
		
		//async request
		as_schedule_single_action(time(), 'middle_pre_uninstall_plugin', [$plugin_file, $uninstallable_plugins]);
		
	}
    public static function async_update_option(string $option, mixed $old_value, mixed $value ){
		
		//async request
		as_schedule_single_action(time(), 'middle_update_option', [$option, $old_value, $value]);
		
	}
    public static function async_add_attachment(int $post_id ){
		
		//async request
		as_schedule_single_action(time(), 'middle_add_attachment', [$post_id]);
		
	}
    public static function async_add_post_meta(int $post_id, string $meta_key, mixed $meta_value){
		
		//async request
		as_schedule_single_action(time(), 'middle_add_post_meta', [$post_id, $meta_key, $meta_value]);
		
	}
    public static function async_attachment_updated( int $post_id, WP_Post $post_after, WP_Post $post_before){
		
		//async request
		$s_post_after = serialize($post_after);
		$s_post_before = serialize($post_before);
		as_schedule_single_action(time(), 'middle_attachment_updated', [$post_id, $s_post_after, $s_post_before]);
		
	}
    public static function async_delete_attachment(int $post_id, WP_Post $post){
		
		//async request
		$s_post = serialize($post);
		as_schedule_single_action(time(), 'middle_delete_attachment', [$post_id, $s_post]);
		
	}
    public static function async_core_updated_successfully(string $wp_version){
		
		//async request
		as_schedule_single_action(time(), 'middle_core_updated_successfully', [$wp_version]);
		
	}
    public static function async_wp_insert_comment(int $id, WP_Comment $comment ){
		
		//async request
		$s_comment = serialize($comment);
		as_schedule_single_action(time(), 'middle_wp_insert_comment', [$id, $s_comment]);
		
	}
    public static function async_wp_update_comment_data(array|WP_Error $data, array $comment, array $commentarr  ){
		
		//async request
		$s_data = serialize($data);
		as_schedule_single_action(time(), 'middle_wp_update_comment_data', [$s_data, $comment, $commentarr]);
		
	}
    public static function async_edit_comment(int $comment_id, array $data  ){
		
		//async request
        as_schedule_single_action(time(), 'middle_edit_comment', [$comment_id, $data]);
        
    }
    public static function async_delete_comment(string $comment_id, WP_Comment $comment  ){
		
		//async request
		$s_comment = serialize($comment);
        as_schedule_single_action(time(), 'middle_delete_comment', [$comment_id, $s_comment]);
        
    }
    public static function async_transition_comment_status( int|string $new_status, int|string $old_status, WP_Comment $comment  ){
		
		//async request
		$s_comment = serialize($comment);
        as_schedule_single_action(time(), 'middle_transition_comment_status', [$new_status, $old_status, $s_comment]);
        
    }
    public static function async_trashed_post_comments( int $post_id, array $statuses ){
        
        //async request
        as_schedule_single_action(time(), 'middle_trashed_post_comments', [$post_id, $statuses]);
        
    }
    public static function async_untrash_post_comments( int $post_id ){
        
        //async request
        as_schedule_single_action(time(), 'middle_untrash_post_comments', [$post_id]);
        
    }

}
