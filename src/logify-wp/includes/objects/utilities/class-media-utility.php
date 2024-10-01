<?php
/**
 * Contains the Media_Utility class.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

/**
 * Class Logify_WP\Media_Utility
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
	 * @return ?string The media type, or null if not an attachment.
	 */
	public static function get_media_type( int $post_id ): ?string {
		// Check this is an attachment.
		if ( get_post_type( $post_id ) !== 'attachment' ) {
			return null;
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
}
