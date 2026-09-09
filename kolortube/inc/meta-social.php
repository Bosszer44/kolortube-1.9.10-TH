<?php
$post_data = wpst_get_post_data( $post->ID );
$url_image = wpst_get_video_thumb_url( $post->ID, 'full', false );
$current_url   = get_permalink( $post->ID );
$current_title = $post_data->post_title;
$current_desc  = $current_title;
if ( ! empty( $post_data->post_content ) ) {
	$current_desc = wp_trim_words( wp_strip_all_tags( strip_shortcodes( apply_filters( 'the_content', $post_data->post_content ) ) ), 55, '...' );
}
?>

<!-- Meta Facebook -->
<meta property="fb:app_id" content="966242223397117" />
<meta property="og:url" content="<?php echo esc_url( $current_url ); ?>" />
<meta property="og:type" content="article" />
<meta property="og:title" content="<?php echo esc_html( $current_title ); ?>" />
<meta property="og:description" content="<?php echo esc_html( $current_desc ); ?>" />
<meta property="og:image" content="<?php echo esc_url( $url_image ); ?>" />
<meta property="og:image:width" content="200" />
<meta property="og:image:height" content="200" />

<!-- Meta Twitter -->
<meta name="twitter:card" content="summary">
<!--<meta name="twitter:site" content="@site_username">-->
<meta name="twitter:title" content="<?php echo esc_html( $current_title ); ?>">
<meta name="twitter:description" content="<?php echo esc_html( $current_desc ); ?>">
<!--<meta name="twitter:creator" content="@creator_username">-->
<meta name="twitter:image" content="<?php echo esc_url( $url_image ); ?>">
<!--<meta name="twitter:domain" content="YourDomain.com">-->
