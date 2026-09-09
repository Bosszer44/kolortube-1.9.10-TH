<?php
/**
 * Loop Video Template
 * ✅ อัปเดต: ลบวิว/เวลา/เรตติ้ง ออกทั้งหมด เหลือแค่รูปปก + ชื่อเรื่อง
 */
$sidebar_pos            = get_theme_mod( 'sidebar_position', 'left' );
$show_title             = get_theme_mod( 'video_listing_general_show_title', 'yes' );
$mobile_columns         = get_theme_mod( 'mobile_columns', '2' );
$video_id               = get_the_ID();
$video_display_name     = get_the_title();
$video_loop_has_ad      = get_query_var( 'video_loop_has_ad', false );
$video_counter          = get_query_var( 'video_counter', 0 );
$video_order            = '';
$trailer_url            = get_post_meta( get_the_ID(), 'trailer_url', true );
$enable_video_preview   = get_theme_mod( 'enable_video_preview', 'yes' );
$enable_thumbs_rotation = get_theme_mod( 'enable_thumbs_rotation', 'yes' );

// ✅ ลบส่วนที่เกี่ยวข้องกับ duration/views/เรตติ้ง ออกหมดแล้ว

// Keep original KolorTube column logic, but use a real src for thumbnails so cards load even when lazyload JS is disabled/deferred.
$video_thumb_url = '';
if ( function_exists( 'wps_get_post_best_image_url' ) ) {
	$video_thumb_url = wps_get_post_best_image_url( get_the_ID(), 'video-thumb' );
}
if ( ! $video_thumb_url && has_post_thumbnail() ) {
	$video_thumb_url = get_the_post_thumbnail_url( get_the_ID(), 'video-thumb' );
} elseif ( ! $video_thumb_url && '' !== get_post_meta( get_the_ID(), 'thumb', true ) ) {
	$video_thumb_url = get_post_meta( get_the_ID(), 'thumb', true );
}
$video_thumb_fallback = get_template_directory_uri() . '/img/no-thumb.png';
$video_rotation_thumbs = ( 'yes' === $enable_thumbs_rotation && function_exists( 'wpst_get_multithumbs' ) ) ? wpst_get_multithumbs( get_the_ID() ) : '';
?>
<?php
if ( $video_loop_has_ad ) :
	$video_order = implode(
		' ',
		array(
			''   => 'order-' . ( $video_counter <= 2 ? '0' : '2' ),
			'sm' => 'order-sm-' . ( $video_counter <= 2 ? '0' : '2' ),
			'md' => 'order-md-' . ( 'none' === $sidebar_pos ? ( $video_counter <= 2 ? '0' : '2' ) : ( $video_counter <= 1 ? '0' : '2' ) ),
			'lg' => 'order-lg-' . ( $video_counter <= 2 ? '0' : '2' ),
			'xl' => 'order-xl-' . ( $video_counter <= 4 ? '0' : '2' ),
		)
	);
endif;
$col_mobile = 'col-' . intval( 12 / intval( $mobile_columns ) );
?>
<div class="<?php echo esc_html( $video_order ); ?> <?php echo esc_html( $col_mobile ); ?> <?php echo 'none' !== $sidebar_pos ? 'col-md-4' : 'col-md-3'; ?> col-lg-3 col-xl-2">
	<div
		class="video-block <?php echo ( '' !== $trailer_url ? 'video-with-trailer' : 'thumbs-rotation' ); ?>"
		data-post-id="<?php echo intval( $video_id ); ?>"
		<?php if ( ! $trailer_url && '' !== $video_rotation_thumbs ) : ?>
			data-thumbs="<?php echo esc_attr( $video_rotation_thumbs ); ?>"
		<?php endif; ?>
	>
		<?php echo apply_filters( 'wps_paywall_premium_badge', '', $post->ID ); ?>
		<a class="thumb" href="<?php the_permalink(); ?>">
			<?php if ( $enable_video_preview == 'yes' ) : ?>
				<div class="video-debounce-bar"></div>
				<div class="lds-dual-ring"></div>
			<?php endif; ?>
				<?php if ( $video_thumb_url ) : ?>
					<img class="video-img img-fluid loaded" src="<?php echo esc_url( $video_thumb_url ); ?>" data-src="<?php echo esc_url( $video_thumb_url ); ?>" data-default-thumb="<?php echo esc_url( $video_thumb_url ); ?>" alt="<?php the_title(); ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?php echo esc_url( $video_thumb_fallback ); ?>';">
				<?php else : ?>
					<img class="video-img img-fluid loaded" src="<?php echo esc_url( $video_thumb_fallback ); ?>" data-default-thumb="<?php echo esc_url( $video_thumb_fallback ); ?>" alt="<?php the_title(); ?>" loading="lazy" decoding="async">
				<?php endif; ?>
			<?php if ( $enable_video_preview == 'yes' ) : ?>
				<div class="video-preview"></div>
			<?php endif; ?>
		</a>
		<a class="infos" href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
			<span class="title"><?php the_title(); ?></span>
		</a>
	</div>
</div>
<?php
// Add line breakers for ad zone.
// No breaker needed for <= small devices.
if ( $video_loop_has_ad ) :
	// Sidebar OFF / md + lg case.
	if ( 'none' === $sidebar_pos && 4 === $video_counter ) {
			echo '<div class="d-none d-md-block d-xl-none order-2 w-100"></div>';
	}
	// Sidebar ON / md case.
	if ( 'none' !== $sidebar_pos && 2 === $video_counter ) {
		echo '<div class="d-none d-md-block d-lg-none order-2 w-100"></div>';
	}
	// Sidebar ON / lg case.
	if ( 'none' !== $sidebar_pos && 4 === $video_counter ) {
		echo '<div class="d-none d-lg-block d-xl-none order-2 w-100"></div>';
	}
	// Sidebar ON + OFF / xl case.
	if ( 8 === $video_counter ) {
		echo '<div class="d-none d-xl-block order-2 w-100"></div>';
	}
endif;
