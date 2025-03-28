<?php
/**
 * Contains the Post_Tracker class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

use WP_Post;

/**
 * Class Logify_WP\Post_Tracker
 *
 * Provides tracking of events related to posts.
 */
class Post_Tracker
{

	/**
	 * Event for creating or updating a post object.
	 *
	 * @var ?Event
	 */
	private static ?Event $update_post_event = null;

	/**
	 * Whether the event being developed is for creating a media object, or updating it.
	 *
	 * @var bool
	 */
	private static bool $creating = false;

	/**
	 * Keep track of terms added to a post in a single request.
	 *
	 * @var array
	 */
	private static array $terms = array();

	/**
	 * Set up hooks for the events we want to log.
	 */
	public static function init()
	{
		// Track post creation and update.
		add_action('save_post', [__NAMESPACE__ . '\Async_Tracker', 'async_save_post'], 10, 3);
		add_action('middle_save_post', array(__CLASS__, 'on_save_post'), 10, 4);
		// add_action('save_post', array(__CLASS__, 'on_save_post'), 10, 3);

		add_action('pre_post_update', [__NAMESPACE__ . '\Async_Tracker', 'async_pre_post_update'], 10, 2);
		add_action('middle_pre_post_update', array(__CLASS__, 'on_pre_post_update'), 10, 3);

		add_action('post_updated', [__NAMESPACE__ . '\Async_Tracker', 'async_post_updated'], 10, 3);
		add_action('middle_post_updated', array(__CLASS__, 'on_post_updated'), 10, 4);

		add_action('update_post_meta', [__NAMESPACE__ . '\Async_Tracker', 'async_update_post_meta'], 10, 4);
		add_action('middle_update_post_meta', array(__CLASS__, 'on_update_post_meta'), 10, 5);

		// Post status change.
		add_action('transition_post_status', [__NAMESPACE__ . '\Async_Tracker', 'async_transition_post_status'], 10, 3);
		add_action('middle_transition_post_status', array(__CLASS__, 'on_transition_post_status'), 10, 4);

		// Track post deletion.
		add_action('before_delete_post', [__NAMESPACE__ . '\Async_Tracker', 'async_before_delete_post'], 10, 2);
		add_action('middle_before_delete_post', array(__CLASS__, 'on_before_delete_post'), 10, 3);

		add_action('delete_post', [__NAMESPACE__ . '\Async_Tracker', 'async_delete_post'], 10, 2);
		add_action('middledelete_post', array(__CLASS__, 'on_delete_post'), 10, 2);

		// Track linking and unlinking of terms and posts.
		add_action('added_term_relationship', [__NAMESPACE__ . '\Async_Tracker', 'async_added_term_relationship'], 10, 3);
		add_action('middle_added_term_relationship', array(__CLASS__, 'on_added_term_relationship'), 10, 3);

		add_action('wp_after_insert_post', [__NAMESPACE__ . '\Async_Tracker', 'async_wp_after_insert_post'], 10, 4);
		add_action('middle_wp_after_insert_post', array(__CLASS__, 'on_wp_after_insert_post'), 10, 5);

		add_action('deleted_term_relationships', [__NAMESPACE__ . '\Async_Tracker', 'async_deleted_term_relationships'], 10, 3);
		add_action('middle_deleted_term_relationships', array(__CLASS__, 'on_deleted_term_relationships'), 10, 3);

		// Shutdown.
		add_action('shutdown', [__NAMESPACE__ . '\Async_Tracker', 'async_shutdown_post'], 10, 0);
		add_action('middle_shutdown_post', array(__CLASS__, 'on_shutdown'), 10, 0);
	}

	// =============================================================================================
	// Post creation and update

	/**
	 * Log the creation and update of a post.
	 *
	 * This event is the only one that occurs when creating a post.
	 *
	 * However, it occurs 3rd in a series of 2-3 when updating a post.
	 * 1. pre_post_update (maybe)
	 * 2. post_updated
	 * 3. save_post
	 *
	 * @param int     $post_id The ID of the post being saved.
	 * @param WP_Post $post    The post object.
	 * @param bool    $update  Whether this is an update or a new post.
	 */
	public static function on_save_post(int $post_id, $serialize_post, bool $update, int $acting_user_id)
	{

		// Unserialize Post object
		$post = unserialize($serialize_post);
		// Ignore updates. We track post updates by tracking the creation of revisions, which
		// enables us to link to the compare revisions page.

		if ($update) {
			return;
		}

		// Ignore navigation menu items.
		if ($post->post_type === 'nav_menu_item') {
			return;
		}

		Debug::info('on_save_post');

		// Check if we're creating or updating. If this is a revision, we're updating the post.
		// Otherwise, we're creating a new one.
		self::$creating = wp_is_post_revision($post_id) === false;

		// If we're updating, the current $post variable refers to the new revision rather than the
		// parent post. Update $post so we get the right details.
		if (!self::$creating) {
			// Record the ID and title of the new revision; we'll use this info in the link, below.
			$revision_id = $post_id;
			$revision_title = $post->post_title;

			// Load the parent object.
			$post_id = $post->post_parent;
			$post = Post_Utility::load($post_id);
		}

		// Get the event type verb.
		$event_type_verb = self::$creating || $post->post_status === 'auto-draft' ? 'Created' : 'Updated';

		// Get the event.
		$event = self::get_update_post_event($event_type_verb, $post, $acting_user_id);
		if (!$event) {
			return;
		}

		// If updating, and the content has changed, replace the changed content so we aren't
		// storing huge blocks of text in the properties table.
		if (!self::$creating) {
			$prop = $event->get_prop('post_content');
			if ($prop) {
				// Show a snippet of the old version.
				$prop->val = Strings::get_snippet($prop->val);
				// Link to the revision.
				$prop->new_val = new Object_Reference('post', $revision_id, $revision_title);
			}
		}
	}

	/**
	 * Record the last modified datetime before the post is updated.
	 *
	 * If this event occurs, it will be before on_post_updated(), which will be before on_save_post().
	 *
	 * @param int   $post_id The ID of the post being updated.
	 * @param array $data    The data for the post.
	 */
	public static function on_pre_post_update(int $post_id, array $data, $acting_user_id)
	{
		global $wpdb;

		Debug::info('on_pre_post_update');

		// Get the new event.
		$event = self::get_update_post_event('Updated', $post_id, $acting_user_id);
		if (!$event) {
			return;
		}

		// Record the current last modified date.
		$event->set_prop('post_modified', $wpdb->posts, Post_Utility::get_last_modified_datetime($post_id));
	}

	/**
	 * Track post update. This handler allows us to capture changed properties before the
	 * save_post handler is called, as that hook doesn't provide us with the before state.
	 *
	 * If this event occurs it will be before on_save_post().
	 *
	 * @param int     $post_id      Post ID.
	 * @param WP_Post $post_after   Post object following the update.
	 * @param WP_Post $post_before  Post object before the update.
	 */
	public static function on_post_updated(int $post_id, $serialize_post_after, $serialize_post_before, $acting_user_id)
	{

		$post_after = unserialize($serialize_post_after);
		$post_before = unserialize($serialize_post_before);
		Debug::info('on_post_updated');

		// Get changes to the post.
		$props = Post_Utility::get_changes($post_before, $post_after);

		// Remove any changes to post_status, which we log separately.
		unset($props['post_status']);

		// If any changes remain, update the event.
		if (!empty($props)) {
			// Get the new event.
			$event = self::get_update_post_event('Updated', $post_after, $acting_user_id);
			if (!$event) {
				return;
			}

			// Add the changed properties to the event.
			$event->add_props($props);
		}
	}

	/**
	 * Track post meta update.
	 *
	 * @param int    $meta_id    The ID of the meta data.
	 * @param int    $post_id    The ID of the post.
	 * @param string $meta_key   The key of the meta data.
	 * @param mixed  $meta_value The new value of the meta data.
	 */
	public static function on_update_post_meta(int $meta_id, int $post_id, string $meta_key, mixed $meta_value, $acting_user_id)
	{
		global $wpdb;

		// Don't use this method for media attachments.
		if (get_post_type($post_id) === 'attachment') {
			return;
		}

		// Some changes are uninteresting.
		if (in_array($meta_key, array('_edit_lock', '_edit_last'))) {
			return;
		}

		Debug::info('Post_Tracker::on_update_post_meta');

		// Get the current value.
		$current_value = get_post_meta($post_id, $meta_key, true);

		// Process values into correct types.
		$val = Types::process_database_value($meta_key, $current_value);
		$new_val = Types::process_database_value($meta_key, $meta_value);

		if (!Types::are_equal($val, $new_val)) {
			// Get the new event.
			$event = self::get_update_post_event('Updated', $post_id, $acting_user_id);
			if (!$event) {
				return;
			}

			// Update the post meta.
			$event->set_prop($meta_key, $wpdb->postmeta, $val, $new_val);
		}
	}

	// =============================================================================================
	// Post status change.

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * We log status changes separately from other changes to the post so we can more easily see
	 * when posts were published or submitted for review.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_transition_post_status(string $new_status, string $old_status, $serialize_post, $acting_user_id)
	{
		global $wpdb;
		//unserialize post object
		$post = unserialize($serialize_post);
		error_log("POSTTYPE:");
		// Ignore navigation menu items.
		if ($post->post_type === 'nav_menu_item') {
			return;
		}

		// Ensure this is not a revision.
		if (wp_is_post_revision($post)) {
			return;
		}

		// This event is triggered even when the status hasn't change, but we only need to log an
		// event if the status has changed.
		if ($new_status === $old_status) {
			return;
		}

		// Some status changes we don't care about.
		if (in_array($new_status, array('auto-draft', 'inherit'), true)) {
			return;
		}

		Debug::info('on_transition_post_status', $post->ID, $old_status, $new_status);

		// Get the post type.
		$post_type = Post_Utility::get_post_type_singular_name($post->post_type);

		// Get the event type.
		$event_type_verb = Post_Utility::get_status_transition_verb($old_status, $new_status);

		// Get the event type.
		$event_type = "$post_type $event_type_verb";

		// Create the event.
		$event = Event::create($event_type, $post, null, null, $acting_user_id);

		// If the event could not be created, we aren't tracking this user.
		if (!$event) {
			return;
		}

		// Update the properties to correctly show the status change.
		$event->set_prop('post_status', $wpdb->posts, $old_status, $new_status);

		// If the post is scheduled for the future, let's include this information.
		if ($new_status === 'future') {
			$scheduled_publish_datetime = DateTimes::create_datetime($post->post_date);
			$event->set_meta('when_to_publish', $scheduled_publish_datetime);
		}

		// Log the event.
		$event->save();
	}

	// =============================================================================================
	// Post deletion

	/**
	 * Fires before a post is deleted, at the start of wp_delete_post().
	 *
	 * We use this method to create the delete event (without saving it), and record details that
	 * won't be available in on_delete_post().
	 *
	 * NOTE: This method doesn't get called for media.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function on_before_delete_post(int $post_id, $serialize_post, $acting_user_id)
	{

		//unserialize post object
		$post = unserialize($serialize_post);
		// Ignore revisions.
		if (wp_is_post_revision($post)) {
			return;
		}

		Debug::info('on_before_delete_post', $post_id);

		// Get the event type.
		$event_type = self::get_delete_event_type($post->post_type);

		// Create the event.
		$event = Event::create($event_type, $post, null, null, $acting_user_id);

		// If the event could not be created, we aren't tracking this user.
		if (!$event) {
			return;
		}

		// Get the attached terms.
		$attached_terms = Post_Utility::get_attached_terms($post_id);

		// Add them to the eventmetas. One for each taxonomy.
		foreach ($attached_terms as $taxonomy => $term_refs) {
			// Handle navigation menu items differently.
			if ($post->post_type === 'nav_menu_item' && $taxonomy === 'nav_menu') {
				$event->set_meta('navigation_menu', $term_refs[0]);
			} else {
				$event->set_meta(get_taxonomy($taxonomy)->labels->name, $term_refs);
			}
		}

		// For navigation menu items, get the menu item details from the post meta data.
		// We have to do it here, because in on_delete_post() the metadata has already been deleted.
		if ($post->post_type === 'nav_menu_item') {

			// Get the linked object.
			$linked_object = Menu_Item_Utility::get_linked_object($post_id);

			// Make sure we have an object name.
			// This is tricky when the linked object has been deleted already.
			$object_name = $event->object_name;

			if (!$object_name) {
				// if the event doesn't have a name, the post title should be null or an empty string.
				$object_name = $post->post_title;

				// If we don't have a post title, try to get the name from the linked object.
				if (!$object_name && $linked_object instanceof Object_Reference) {
					$object_name = $linked_object->get_name();
				}

				// If we couldn't get a name from the linked object then a post, page, or category
				// was deleted while still linked to a menu. We can look for a Post, Page, or
				// Category Deleted event occuring simultaneously (in the same HTTP request).
				if (!$object_name) {
					// Get the menu item details.
					$menu_item_details = Menu_Item_Utility::get_menu_item_details($post_id);

					if ($menu_item_details) {
						// Set a default event type for the simultaneous event.
						$event_type2 = null;

						// Get the event type for the delete event.
						// This is a bit of a kludge as it depends on the event type matching
						// exactly, but it will do for now.
						if ($menu_item_details['_menu_item_type'] === 'post_type') {
							$post_type = $menu_item_details['_menu_item_object'];
							$event_type2 = Post_Utility::get_post_type_singular_name($post_type) . ' Deleted';
						} elseif ($menu_item_details['_menu_item_type'] === 'taxonomy') {
							$taxonomy = $menu_item_details['_menu_item_object'];
							$event_type2 = Taxonomy_Utility::get_singular_name($taxonomy) . ' Deleted';
						}

						// Look for the simulaneous event.
						if ($event_type2) {
							$event2 = Logger::get_current_event_by_event_type($event_type2);
							if ($event2) {
								// Copy the name.
								$object_name = $event2->object_name;
							}
						}
					}
				}

				// If we found a name, copy it to the new event.
				if ($object_name) {
					$event->object_name = $object_name;
				}
			}
		}

		// Remember the new event.
		Logger::$current_events[] = $event;
	}

	/**
	 * Log the deletion of a post.
	 *
	 * This method is called immediately before the post is deleted from the database.
	 * It relies on on_before_delete_post() being called first, to record attached terms.
	 *
	 * @param int     $post_id The ID of the post that was deleted.
	 * @param WP_Post $post    The post object that was deleted.
	 */
	public static function on_delete_post(int $post_id, $serialize_post )
	{
		//unserialize post object
		$post = unserialize($serialize_post);
		// Ensure this is not a revision.
		if (wp_is_post_revision($post)) {
			return;
		}

		// This method is not for media.
		if ($post->post_type === 'attachment') {
			return;
		}

		Debug::info('on_delete_post', $post_id);

		// Get the event type.
		$event_type = self::get_delete_event_type($post->post_type);

		// Get the event.
		$event = Logger::get_current_event_by_event_type($event_type);

		// Check if we have the event. Should be ok.
		if (!$event) {
			return;
		}

		// For normal post types (not menu items), add all the object's properties (including
		// metadata), in case we want to restore it later.
		if ($post->post_type !== 'nav_menu_item') {
			$event->add_props(Post_Utility::get_properties($post));
		}

		// Save the event to the log.
		$event->save();
	}

	// =============================================================================================
	// Posts and terms

	/**
	 * Fires immediately after an object-term relationship is added.
	 *
	 * @param int    $post_id  Post ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public static function on_added_term_relationship(int $post_id, int $tt_id, string $taxonomy)
	{
		// Ignore revisions.
		if (wp_is_post_revision($post_id)) {
			return;
		}

		Debug::info('on_added_term_relationship'); // , $post_id, $tt_id, $taxonomy );

		// Remember the newly attached term.
		$term_id = Term_Utility::get_term_id_from_term_taxonomy_id($tt_id);
		self::add_term($taxonomy, 'added', $term_id);
	}

	/**
	 * Fires immediately after an object-term relationship is added.
	 *
	 * @param int    $post_id  The post ID.
	 * @param array  $tt_ids   An array of term taxonomy IDs.
	 * @param string $taxonomy The taxonomy slug.
	 */
	public static function on_deleted_term_relationships(int $post_id, array $tt_ids, string $taxonomy)
	{
		// Ignore revisions.
		if (wp_is_post_revision($post_id)) {
			return;
		}

		Debug::info('on_deleted_term_relationships'); // , $post_id, $tt_ids, $taxonomy );

		// Remember the removed terms.
		foreach ($tt_ids as $tt_id) {
			$term_id = Term_Utility::get_term_id_from_term_taxonomy_id($tt_id);
			self::add_term($taxonomy, 'removed', $term_id);
		}
	}

	/**
	 * Fires immediately after an object-term relationship is added.
	 *
	 * @param int      $post_id     Post ID.
	 * @param WP_Post  $post        Post object.
	 * @param bool     $update      Whether this is an existing post being updated.
	 * @param ?WP_Post $post_before Null for new posts, the WP_Post object prior to the update for updated posts.
	 */
	public static function on_wp_after_insert_post(int $post_id, $serialize_post, bool $update, $serialize_post_before, $acting_user_id)
	{
		//unserialize post object
		$post = unserialize($serialize_post);
		$post_before = unserialize($serialize_post_before);

		//Current User id
		$acting_user = $acting_user_id ?? null;
		
		// Ignore revisions.
		if (wp_is_post_revision($post_id)) {
			return;
		}

		Debug::info('on_wp_after_insert_post');

		// Log the addition or removal of any taxonomy terms.
		if (self::$terms) {
			// Loop through the taxonomies and create a log entry for each.
			foreach (self::$terms as $taxonomy => $term_changes) {

				// Get the taxonomy object and name.
				$taxonomy_obj = get_taxonomy($taxonomy);
				$taxonomy_name = $taxonomy_obj->label;

				// Get the event type.
				if ($post->post_type === 'nav_menu_item') {
					$event_type = 'Navigation Menu Item Added';
				} else {
					$post_type = Post_Utility::get_post_type_singular_name($post->post_type);
					$event_type = "$post_type $taxonomy_name Updated";
				}

				// Create the event.
				$event = Event::create($event_type, $post, null,null, $acting_user);

				// If the event could not be created, we aren't tracking this user.
				if (!$event) {
					return;
				}

				// Show the added terms in the eventmetas.
				if (!$term_changes['added']->isEmpty()) {
					// Convert term IDs to Object_Reference objects.
					$term_refs = self::convert_term_ids_to_references($term_changes['added']);

					// Get the meta key.
					if ($post->post_type === 'nav_menu_item' && $taxonomy === 'nav_menu') {
						// For menu items, just show the menu.
						$event->set_meta('navigation_menu', $term_refs[0]);
					} else {
						$event->set_meta('added_' . $taxonomy_name, $term_refs);
					}
				}

				// Show the removed terms in the eventmetas.
				if (!$term_changes['removed']->isEmpty()) {
					// Convert term IDs to Object_Reference objects.
					$term_refs = self::convert_term_ids_to_references($term_changes['removed']);
					$event->set_meta('removed_' . $taxonomy_name, $term_refs);
				}

				// Log the event.
				$event->save();
			}
		}
	}

	// =============================================================================================
	// Shutdown.

	/**
	 * Fires on shutdown, after PHP execution.
	 */
	public static function on_shutdown()
	{
		// Save the post update/create event, if necessary.
		if (self::$update_post_event && (self::$creating || self::$update_post_event->has_changes())) {
			self::$update_post_event->save();
		}

		Taxonomy_Utility::get_current_taxonomies_core_properties();
	}

	// =============================================================================================
	// Support methods.

	/**
	 * Get the update or create post event. If it hasn't been created yet, do it now.
	 *
	 * @param string      $event_type_verb The event type verb.
	 * @param int|WP_Post $post            The post the event is about.
	 * @return ?Event The event, or null if it couldn't be created.
	 */
	private static function get_update_post_event(string $event_type_verb, int|WP_Post $post, $acting_user_id): ?Event
	{
		// Create the event if not done already.
		if (!self::$update_post_event) {

			// Load the post if necessary.
			if (is_int($post)) {
				$post = Post_Utility::load($post);
			}

			// Get the post type.
			$post_type = Post_Utility::get_post_type_singular_name($post->post_type);

			// Get the event type.
			$event_type = "$post_type $event_type_verb";

			// Create the event.
			self::$update_post_event = Event::create($event_type, $post, null, null, $acting_user_id);
		}

		return self::$update_post_event;
	}

	/**
	 * Get the event type for a post deletion.
	 *
	 * @param string $post_type The post type.
	 * @return string The event type.
	 */
	private static function get_delete_event_type(string $post_type): string
	{
		if ($post_type === 'nav_menu_item') {
			return 'Navigation Menu Item Removed';
		} else {
			return Post_Utility::get_post_type_singular_name($post_type) . ' Deleted';
		}
	}

	/**
	 * Convert a set of term IDs to an array of Object_Reference objects.
	 *
	 * @param Set $term_ids Set of term IDs.
	 * @return array Array of Object_Reference objects.
	 */
	private static function convert_term_ids_to_references(Set $term_ids): array
	{
		return array_map(fn($term_id) => new Object_Reference('term', $term_id), $term_ids->items());
	}

	/**
	 * Add a term to the list of terms to be logged.
	 *
	 * @param string $taxonomy The taxonomy name.
	 * @param string $change   The change type ('added' or 'removed').
	 * @param int    $term_id  The term ID.
	 */
	private static function add_term(string $taxonomy, string $change, int $term_id)
	{
		// Prepare the array if necessary.
		if (!isset(self::$terms[$taxonomy])) {
			self::$terms[$taxonomy] = array(
				'added' => new Set(),
				'removed' => new Set(),
			);
		}

		// Get the opposite change.
		$opposite_change = $change === 'added' ? 'removed' : 'added';

		// If the reverse of the change is already recorded, remove it.
		if (self::$terms[$taxonomy][$opposite_change]->contains($term_id)) {
			self::$terms[$taxonomy][$opposite_change]->remove($term_id);
		} else {
			// Add the term to the 'added' or 'removed' set as required.
			self::$terms[$taxonomy][$change]->add($term_id);
		}
	}
}
