<?php

/**
 * Class ActionScheduler_wpPostStore_PostTypeRegistrar
 *
 * @codeCoverageIgnore
 */
class ActionScheduler_wpPostStore_PostTypeRegistrar {
	/**
	 * Registrar.
	 */
	public function register() {
		register_post_type( ActionScheduler_wpPostStore::POST_TYPE, $this->post_type_args() );
	}

	/**
	 * Build the args array for the post type definition
	 *
	 * @return array
	 */
	protected function post_type_args() {
		$args = array(
			'label'        => __('Scheduled Actions', 'logify-wp' ),
			'description'  => __('Scheduled actions are hooks triggered on a certain date and time.', 'logify-wp' ),
			'public'       => false,
			'map_meta_cap' => true,
			'hierarchical' => false,
			'supports'     => array( 'title', 'editor', 'comments' ),
			'rewrite'      => false,
			'query_var'    => false,
			'can_export'   => true,
			'ep_mask'      => EP_NONE,
			'labels'       => array(
				'name'               => __('Scheduled Actions', 'logify-wp' ),
				'singular_name'      => __('Scheduled Action', 'logify-wp' ),
				'menu_name'          => _x('Scheduled Actions', 'Admin menu name', 'logify-wp' ),
				'add_new'            => __('Add', 'logify-wp' ),
				'add_new_item'       => __('Add New Scheduled Action', 'logify-wp' ),
				'edit'               => __('Edit', 'logify-wp' ),
				'edit_item'          => __('Edit Scheduled Action', 'logify-wp' ),
				'new_item'           => __('New Scheduled Action', 'logify-wp' ),
				'view'               => __('View Action', 'logify-wp' ),
				'view_item'          => __('View Action', 'logify-wp' ),
				'search_items'       => __('Search Scheduled Actions', 'logify-wp' ),
				'not_found'          => __('No actions found', 'logify-wp' ),
				'not_found_in_trash' => __('No actions found in trash', 'logify-wp' ),
			),
		);

		$args = apply_filters( 'action_scheduler_post_type_args', $args );
		return $args;
	}
}
