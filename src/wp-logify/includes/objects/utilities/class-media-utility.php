<?php
/**
 * Contains the Media_Utility class.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

use Exception;

/**
 * Class WP_Logify\Media_Utility
 *
 * Provides methods for working with attachments.
 *
 * Note, this class does not extend Object_Utility. It is effectively an adjunct to Post_Utility,
 * with methods specifically pertaining to media (attachment-type posts).
 */
class Media_Utility {

	/**
	 * Get the media type of an attachment post.
	 *
	 * Possible results:
	 * - image
	 * - audio
	 * - video
	 * - file
	 *
	 * @param int $post_id The ID of the post.
	 * @return string The media type.
	 * @throws Exception If the post ID doesn't refer to an attachment.
	 */
	public static function get_media_type( int $post_id ): ?string {
		// Check this is an attachment.
		if ( get_post_type( $post_id ) !== 'attachment' ) {
			throw new Exception( "Post $post_id is not an attachment." );
		}

		// Get the MIME type of the post.
		$mime_type = get_post_mime_type( $post_id );

		if ( $mime_type ) {
			// Check the base media type (the part before the slash in the MIME type).
			$mime_parts = explode( '/', $mime_type );

			// Check if the MIME type is an image, audio, or video.
			if ( in_array( $mime_parts[0], array( 'image', 'audio', 'video' ), true ) ) {
				return $mime_parts[0];
			}
		}

		// Default to 'file'.
		return 'file';
	}

	/**
	 * Set properties pertaining to images.
	 *
	 * @param Event $event The event to update.
	 */
	/*
	public static function update_image_properties( Event $event ) {
		// Check we have the right object type and media type.
		$is_image = $event->object_type === 'post' && $event->object_subtype === 'attachment'
			&& self::get_media_type( (int) $event->object_key ) === 'image';
		if ( ! $is_image ) {
			throw new Exception( 'This method requires that the event object is an image.' );
		}

		// Get the post ID and object.
		$post_id = (int) $event->object_key;
		$post    = Post_Utility::load( $post_id );

		global $wpdb;

		// Alternative text.
		$alt_text_prop = $event->remove_prop( '_wp_attachment_image_alt' );
		if ( $alt_text_prop ) {
			// Update the property key and re-add the property to the event.
			$alt_text_prop->key = 'Alternative text';
			$event->add_prop( $alt_text_prop );
		} else {
			$alt_text = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
			$event->set_prop( 'Alternative text', $wpdb->postmeta, $alt_text );
		}

		// Title.
		$title_prop = $event->remove_prop( 'post_title' );
		if ( $title_prop ) {
			// Update the property key and re-add the property to the event.
			$title_prop->key = 'Title';
			$event->add_prop( $title_prop );
		} else {
			$event->set_prop( 'Title', $wpdb->posts, $post->post_title );
		}

		// Caption.
		$caption_prop = $event->remove_prop( 'post_excerpt' );
		if ( $caption_prop ) {
			// Update the property key and re-add the property to the event.
			$caption_prop->key = 'Caption';
			$event->add_prop( $caption_prop );
		} else {
			$event->set_prop( 'Caption', $wpdb->posts, $post->post_excerpt );
		}

		// Description.
		$desc_prop = $event->remove_prop( 'post_content' );
		if ( $desc_prop ) {
			// Update the property key and re-add the property to the event.
			$desc_prop->key = 'Description';
			$event->add_prop( $desc_prop );
		} else {
			$event->set_prop( 'Description', $wpdb->posts, $post->post_content );
		}
	}*/
}
