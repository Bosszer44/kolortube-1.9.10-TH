<?php
$sidebar_pos       = get_theme_mod( 'sidebar_position', 'left' );
$video_loop_has_ad = get_query_var( 'video_loop_has_ad', false );
?>
<div class="col-6 <?php echo 'none' !== $sidebar_pos ? 'col-md-4' : 'col-md-3'; ?> col-lg-3 col-xl-2">
	<div class="video-block video-block-cat">
		<a class="thumb" href="<?php echo get_category_link( get_cat_ID( $category_name ) ); ?>" title="<?php echo $category_name; ?>">
			<?php echo $category_thumb; ?>
		</a>
		<a class="infos" href="<?php echo get_category_link( get_cat_ID( $category_name ) ); ?>" title="<?php the_title(); ?>">
			<span class="title"><?php echo $category_name; ?></span>
			<span class="cat-videos-count"><?php echo intval( $category_videos_count ); ?> <?php echo class_exists( 'WPS_Professional_Suite' ) ? esc_html( WPS_Professional_Suite::text( 'เรื่อง', 'videos' ) ) : esc_html__( 'videos', 'wpst' ); ?></span>
	</a>
	</div>
</div>

