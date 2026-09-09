<?php
add_action( 'xbox_init', 'wpst_video_information_metabox' );
function wpst_video_information_metabox() {
	$options = array(
		'id'            => 'video-information-metabox',
		'title'         => wp_get_theme()->get( 'Name' ) . ' - ' . esc_html__( 'Video information', 'wpst' ),
		'post_types'    => array( 'post' ),
		'layout'        => 'wide', // boxed
		'skin'          => 'pink',
		'fields_prefix' => '',
		'header'        => array(),
	);

	$xbox = xbox_new_metabox( $options );

	/* Video URL */
	$xbox->add_field(
		array(
			'id'   => 'video_url',
			'name' => esc_html__( 'Video URL', 'wpst' ),
			'type' => 'file',
			'desc' => __( 'Paste here the video URL or upload a video file (mp4, webm).', 'wpst' ),
		)
	);


	/* Optional subtitle — directly below the Video URL field. */
	$xbox->add_field(
		array(
			'id'   => '_wps_subtitle_source_url',
			'name' => esc_html__( 'ไฟล์ซับไตเติล (ไม่บังคับ)', 'wpst' ),
			'type' => 'file',
			'desc' => esc_html__( 'เลือกไฟล์ .srt หรือ .vtt เฉพาะเรื่องที่มีซับ ปล่อยว่างได้ หมายเหตุ: ซับจากเว็บนี้แสดงได้กับวิดีโอ MP4/WebM ที่เล่นด้วย HTML5 เท่านั้น ไม่สามารถแทรกเข้า iframe ภายนอก เช่น ggcdn ได้', 'wpst' ),
			'options' => array(
				'mime_types' => array( 'srt', 'vtt' ),
				'protocols'  => array( 'http', 'https' ),
			),
		)
	);
	$xbox->add_field(
		array(
			'id'      => '_wps_subtitle_language',
			'name'    => esc_html__( 'ภาษาซับ', 'wpst' ),
			'type'    => 'text',
			'default' => 'th',
			'desc'    => esc_html__( 'รหัสภาษา เช่น th หรือ en', 'wpst' ),
		)
	);
	$xbox->add_field(
		array(
			'id'      => '_wps_subtitle_label',
			'name'    => esc_html__( 'ชื่อที่แสดงในปุ่มซับ', 'wpst' ),
			'type'    => 'text',
			'default' => 'ไทย',
			'desc'    => esc_html__( 'เช่น ไทย หรือ English', 'wpst' ),
		)
	);

	/* Duration */
	$xbox->add_field(
		array(
			'name' => esc_html__( 'Duration', 'wpst' ),
			'id'   => 'duration',
			'type' => 'html',
		)
	);

	/* Views */
	$xbox->add_field(
		array(
			'id'         => 'post_views_count',
			'name'       => esc_html__( 'Views', 'wpst' ),
			'type'       => 'number',
			'options'    => array(
				'unit'            => 'views',
				'show_unit'       => true,
				'show_spinner'    => true,
				'disable_spinner' => false,
			),
			'attributes' => array(
				'min'       => 0,
				'step'      => 1,
				'precision' => 0,
			),
		)
	);

	/* Likes */
	$xbox->add_field(
		array(
			'id'         => 'likes_count',
			'name'       => esc_html__( 'Likes', 'wpst' ),
			'type'       => 'number',
			'options'    => array(
				'unit'            => '<i class="xbox-icon xbox-icon-thumbs-up"></i>',
				'show_unit'       => true,
				'show_spinner'    => true,
				'disable_spinner' => false,
			),
			'attributes' => array(
				'min'       => 0,
				'step'      => 1,
				'precision' => 0,
			),
		)
	);

	/* Dislikes */
	$xbox->add_field(
		array(
			'id'         => 'dislikes_count',
			'name'       => esc_html__( 'Dislikes', 'wpst' ),
			'type'       => 'number',
			'options'    => array(
				'unit'            => '<i class="xbox-icon xbox-icon-thumbs-down"></i>',
				'show_unit'       => true,
				'show_spinner'    => true,
				'disable_spinner' => false,
			),
			'attributes' => array(
				'min'       => 0,
				'step'      => 1,
				'precision' => 0,
			),
		)
	);

	/* Video URL Resolutions */
	$xbox->open_tab_item( 'video-url-resolutions' );
		$xbox->open_mixed_field( array( 'name' => 'Resolutions' ) );
			$xbox->add_field(
				array(
					'id'   => 'video_url_240',
					'name' => __( '240p', 'wpst' ),
					'type' => 'file',
					'grid' => '4-of-8',
				)
			);
			$xbox->add_field(
				array(
					'id'   => 'video_url_360',
					'name' => __( '360p', 'wpst' ),
					'type' => 'file',
					'grid' => '4-of-8 last',
				)
			);
			$xbox->add_field(
				array(
					'id'   => 'video_url_480',
					'name' => __( '480p', 'wpst' ),
					'type' => 'file',
					'grid' => '4-of-8',
				)
			);
			$xbox->add_field(
				array(
					'id'   => 'video_url_720',
					'name' => __( '720p', 'wpst' ),
					'type' => 'file',
					'grid' => '4-of-8 last',
				)
			);
			$xbox->add_field(
				array(
					'id'   => 'video_url_1080',
					'name' => __( '1080p', 'wpst' ),
					'type' => 'file',
					'grid' => '4-of-8',
				)
			);
			$xbox->add_field(
				array(
					'id'   => 'video_url_4k',
					'name' => __( '4k', 'wpst' ),
					'type' => 'file',
					'grid' => '4-of-8 last',
				)
			);
		$xbox->close_mixed_field();
	$xbox->close_tab_item( 'video-url-resolutions' );

	/* Video embed code */
	$xbox->add_field(
		array(
			'id'   => 'embed',
			'name' => esc_html__( 'Video embed code', 'wpst' ),
			'type' => 'textarea',
			'desc' => esc_html__( 'Paste here the embed code (eg. Youtube iframe). It will automatically display the video player in the front of your site.', 'wpst' ),
		)
	);
	/* Video shortcode */
	$xbox->add_field(
		array(
			'id'   => 'shortcode',
			'name' => esc_html__( 'Video shortcode', 'wpst' ),
			'type' => 'text',
			'desc' => esc_html__( 'Paste here the video shortcode (eg. [TM plugin="https://drive.google.com/..."]). It will automatically display the video player in the front of your site.', 'wpst' ),
		)
	);

	/* Video trailer */
	$xbox->add_field(
		array(
			'id'      => 'trailer_url',
			'name'    => esc_html__( 'Video trailer URL', 'wpst' ),
			'type'    => 'file',
			'desc'    => esc_html__( 'Paste here the video trailer URL or upload a video file (mp4 or webm). It will be used as video preview on mouse hover.', 'wpst' ),
			'options' => array(
				'mime_types' => array( 'mp4', 'webm' ),
			),
		)
	);

	/* Main thumb */
	$xbox->add_field(
		array(
			'id'      => 'thumb',
			'name'    => esc_html__( 'Main thumbnail', 'wpst' ),
			'type'    => 'file',
			'desc'    => esc_html__( 'Paste here the main thumb URL or upload an image file. If there isn\'t a featured image, this image will be used as main thumb.', 'wpst' ),
			'options' => array(
				'mime_types'   => array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), // Default: array()
				'protocols'    => array( 'http', 'https' ), // Default: array()
				'preview_size' => array(
					'width'  => '320px',
					'height' => '200px',
				), // Default: array( 'width' => '64px', 'height' => 'auto' )
			),
		)
	);

	/* Thumbnail Rotation */
	$xbox->add_field(
		array(
			'id'   => 'thumbnails',
			'name' => esc_html__( 'Thumbnails', 'wpst' ),
			'type' => 'html',
			'desc' => esc_html__( 'Used for thumbnails rotation.', 'wpst' ),
		)
	);

	/* Tracking URL */
	$xbox->add_field(
		array(
			'id'   => 'tracking_url',
			'name' => esc_html__( 'Tracking URL', 'wpst' ),
			'type' => 'text',
			'desc' => esc_html__( 'Paste here the tracking URL you want to display as link under the video in the front of your site (https://...).', 'wpst' ),
		)
	);

	/* Advertising under the video player */
	$xbox->add_field(
		array(
			'id'   => 'unique_ad_under_player',
			'name' => esc_html__( 'Advertising under the video player', 'wpst' ),
			'type' => 'textarea',
			'desc' => esc_html__( 'Paste here the code of your banner, it will be displayed under the video player.', 'wpst' ),
		)
	);
}

/*
|---------------------------------------------------------------------------------------------------
| Inserting thumbnail in Ajax
|---------------------------------------------------------------------------------------------------
*/
function xbox_ajax_insert_thumb() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid request.', 'wpst' ) ), 403 );
	}

	$post_id   = xbox_ajax_thumb_post_id();
	$thumb_url = isset( $_POST['thumb_url'] ) ? esc_url_raw( wp_unslash( $_POST['thumb_url'] ) ) : '';
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || '' === $thumb_url ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid thumbnail request.', 'wpst' ) ), 400 );
	}

	$result = add_post_meta( $post_id, 'thumbs', $thumb_url, false );
	wp_send_json( array( 'result' => (bool) $result ) );
}
add_action( 'wp_ajax_xbox_ajax_insert_thumb', 'xbox_ajax_insert_thumb' );

/*
|---------------------------------------------------------------------------------------------------
| Removing thumbnail in Ajax
|---------------------------------------------------------------------------------------------------
*/
function xbox_ajax_remove_thumb() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid request.', 'wpst' ) ), 403 );
	}

	$post_id   = xbox_ajax_thumb_post_id();
	$thumb_url = isset( $_POST['thumb_url'] ) ? esc_url_raw( wp_unslash( $_POST['thumb_url'] ) ) : '';
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || '' === $thumb_url ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid thumbnail request.', 'wpst' ) ), 400 );
	}

	$result = delete_post_meta( $post_id, 'thumbs', $thumb_url );
	wp_send_json( array( 'result' => (bool) $result ) );
}
add_action( 'wp_ajax_xbox_ajax_remove_thumb', 'xbox_ajax_remove_thumb' );

/** Resolve and validate the post ID used by the legacy thumbnail metabox. */
function xbox_ajax_thumb_post_id() {
	$current_url = isset( $_POST['current_url'] ) ? esc_url_raw( wp_unslash( $_POST['current_url'] ) ) : '';
	if ( '' === $current_url ) {
		return 0;
	}
	$parts = wp_parse_url( $current_url );
	if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
		return 0;
	}
	parse_str( $parts['query'], $query );
	return isset( $query['post'] ) ? absint( $query['post'] ) : 0;
}
