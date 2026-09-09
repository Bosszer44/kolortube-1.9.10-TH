<?php
/** Return lightweight video counters for the legacy front-end UI. */
function wpst_get_async_post_data() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wpst' ) ), 403 );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post.', 'wpst' ) ), 400 );
	}

	wp_send_json(
		array(
			'views'    => (int) wpst_getPostViews( $post_id ),
			'likes'    => (int) get_post_meta( $post_id, 'likes_count', true ),
			'dislikes' => (int) get_post_meta( $post_id, 'dislikes_count', true ),
			'rating'   => wpst_getPostLikeRate( $post_id ),
		)
	);
}

add_action( 'wp_ajax_nopriv_get-post-data', 'wpst_get_async_post_data' );
add_action( 'wp_ajax_get-post-data', 'wpst_get_async_post_data' );
