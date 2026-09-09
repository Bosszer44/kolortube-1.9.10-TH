<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
get_header();

add_filter(
	'theme_mod_seo_related_videos_title',
	function ( $value ) {
		$video_title = get_the_title();
		$value       = str_replace( '%%video_title%%', $video_title, $value );
		return $value;
	}
); ?>

<div class="wrapper" id="single-wrapper">
	<?php
	$current_post_id = 0;
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			$current_post_id = get_the_ID();
			get_template_part( 'loop-templates/content', 'single-video' );
		endwhile;
	endif;
	?>
	<?php
		$related_posts = get_posts(
			array(
				'category__in' => wp_get_post_categories( $current_post_id ),
				'numberposts'  => 12,
				'post__not_in' => array( $current_post_id ),
				'orderby'      => 'rand',
				'order'        => 'ASC',
			)
		);
		if ( $related_posts ) :
			$related_ad = WPS_Ads::html( 'ads_home_inside_related_videos_list' );
			?>
		<section class="related-videos">
			<div class="video-loop">
				<div class="container container-lg p-0">
					<h2 class="text-center"><?php echo esc_html( get_theme_mod( 'seo_related_videos_title', 'You like this video? You will also like...' ) ); ?></h2>
					<div class="row no-gutters">
						<?php if ( '' !== trim( $related_ad ) && count( $related_posts ) > 1 ) : ?>
							<div class="order-1 order-sm-1 order-md-1 order-lg-1 order-xl-1 col-12 col-md-6 col-lg-6 col-xl-4">
								<div class="video-block-happy">
									<div class="video-block-happy-absolute d-flex align-items-center justify-content-center">
										<?php echo $related_ad; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted administrator ad/shortcode markup. ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
						<?php
						$video_counter = 0;
						set_query_var( 'video_loop_has_ad', '' !== trim( $related_ad ) );
						foreach ( $related_posts as $related_post ) {
							++$video_counter;
							$post = $related_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by legacy template part.
							setup_postdata( $post );
							set_query_var( 'video_counter', $video_counter );
							get_template_part( 'loop-templates/loop', 'video' );
						}
						set_query_var( 'video_loop_has_ad', false );
						set_query_var( 'video_counter', 0 );
						wp_reset_postdata();
						?>
					</div>
				</div>
			</div>
			<?php
			$categories = get_the_category( $current_post_id );
			if ( ! empty( $categories ) ) :
				$category_url = get_category_link( $categories[0]->term_id );
				if ( ! is_wp_error( $category_url ) ) :
					?>
					<div class="text-center">
						<a class="btn btn-primary" href="<?php echo esc_url( $category_url ); ?>"><?php echo esc_html( get_theme_mod( 'seo_more_related_videos_button', 'Show more related videos' ) ); ?></a>
					</div>
					<?php
				endif;
			endif;
			wpst_pagination();
			?>
		</section>

		<?php if ( comments_open( $current_post_id ) || get_comments_number( $current_post_id ) ) : ?>
		<section class="single-video-comments">
			<div class="container">
				<div class="row">
					<div class="col-12 col-md-6 mx-auto">
						<?php comments_template(); ?>
					</div>
				</div>
			</div>
		</section>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
