<?php
function wpst_post_like() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ajax-nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}

	$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$post_like = isset( $_POST['post_like'] ) ? sanitize_key( wp_unslash( $_POST['post_like'] ) ) : '';

	if ( ! $post_id || ! in_array( $post_like, array( 'like', 'dislike' ), true ) || 'post' !== get_post_type( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid request.' ), 400 );
	}

	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( '' === $ip ) {
		wp_send_json_error( array( 'message' => 'Unable to identify visitor.' ), 400 );
	}

	$likes    = (int) get_post_meta( $post_id, 'likes_count', true );
	$dislikes = (int) get_post_meta( $post_id, 'dislikes_count', true );

	if ( wpst_hasAlreadyVoted( $post_id ) ) {
		$total      = $likes + $dislikes;
		$percentage = $total > 0 ? (int) ceil( ( $likes / $total ) * 100 ) : 0;
		wp_send_json( array(
			'alreadyrate' => true,
			'percentage'  => $percentage,
			'nbrates'     => $total,
			'likes'       => $likes,
			'dislikes'    => $dislikes,
			'progressbar' => $percentage,
		) );
	}

	$voted_ips = get_post_meta( $post_id, 'voted_IP', true );
	if ( ! is_array( $voted_ips ) ) {
		$voted_ips = array();
	}
	$voted_ips[ $ip ] = time();
	update_post_meta( $post_id, 'voted_IP', $voted_ips );

	if ( 'like' === $post_like ) {
		++$likes;
	} else {
		++$dislikes;
	}
	update_post_meta( $post_id, 'likes_count', $likes );
	update_post_meta( $post_id, 'dislikes_count', $dislikes );

	$total      = $likes + $dislikes;
	$percentage = $total > 0 ? (int) ceil( ( $likes / $total ) * 100 ) : 0;
	update_post_meta( $post_id, 'rate', $percentage );

	wp_send_json( array(
		'alreadyrate' => false,
		'percentage'  => $percentage,
		'nbrates'     => $total,
		'likes'       => $likes,
		'dislikes'    => $dislikes,
		'progressbar' => $percentage,
	) );
}

add_action( 'wp_ajax_nopriv_post-like', 'wpst_post_like' );
add_action( 'wp_ajax_post-like', 'wpst_post_like' );
