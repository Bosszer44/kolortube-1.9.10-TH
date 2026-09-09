<?php
/**
 * Ajax function to load the preview of a video based on the video post ID.
 *
 * @package wpst\core\admin\ajax
 */

defined( 'ABSPATH' ) || exit;

/** Load a video preview iframe based on the video post ID. */
function wpst_load_video_preview() {
	check_ajax_referer( 'ajax-nonce', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post.', 'wpst' ) ), 400 );
	}

	try {
		wp_send_json_success( wpst_get_video_preview( $post_id ) );
	} catch ( Throwable $exception ) {
		wp_send_json_error( array( 'message' => __( 'Video preview is unavailable.', 'wpst' ) ), 500 );
	}
}
add_action( 'wp_ajax_wpst_load_video_preview', 'wpst_load_video_preview' );
add_action( 'wp_ajax_nopriv_wpst_load_video_preview', 'wpst_load_video_preview' );
