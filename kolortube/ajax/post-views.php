<?php
/** Increment the legacy post-view counter. */
function wpst_set_post_views() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wpst' ) ), 403 );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post.', 'wpst' ) ), 400 );
	}

	$count_key = 'post_views_count';
	$count     = absint( get_post_meta( $post_id, $count_key, true ) );
	if ( 0 === $count && '' === get_post_meta( $post_id, $count_key, true ) ) {
		add_post_meta( $post_id, $count_key, '0', true );
	} else {
		++$count;
		update_post_meta( $post_id, $count_key, $count );
	}

	wp_send_json( array( 'views' => $count ) );
}
add_action( 'wp_ajax_nopriv_post-views', 'wpst_set_post_views' );
add_action( 'wp_ajax_post-views', 'wpst_set_post_views' );
