<?php
/**
 * Template Name: Studio
 **/
get_header(); ?>
<?php
	$sidebar_pos = get_theme_mod( 'sidebar_position', 'left' );
	$ads         = array(
		'before_list' => WPS_Ads::html( 'ads_actor_page_before_list' ),
		'inside_list' => WPS_Ads::html( 'ads_actor_page_inside_list' ),
		'after_list'  => WPS_Ads::html( 'ads_actor_page_after_list' ),
	);
	?>

<div id="content">
	<div class="hero" <?php echo 'none' !== $sidebar_pos ? 'style="flex: 1;"' : ''; ?>>
		<div class="container container-lg" tabindex="-1">
			<div class="row hero-text">
				<div class="col-12 col-md-8 mx-auto">
					<h1><?php the_title(); ?></h1>
				</div>
			</div>
		</div>
	</div>
	<?php if ( $ads['before_list'] ) : ?>
		<div class="happy-section"><?php echo $ads['before_list']; ?></div>
	<?php endif; ?>
	<div class="container container-lg p-0 <?php echo 'none' !== $sidebar_pos ? 'd-flex flex-wrap' : ''; ?>">
		<?php if ( 'left' === $sidebar_pos ) : ?>
			<?php get_sidebar(); ?>
		<?php endif; ?>
		<div class="video-loop" <?php echo 'none' !== $sidebar_pos ? 'style="flex: 1;"' : ''; ?>>
			<div class="row no-gutters">
				<div class="col-12">
					<div class="row no-gutters">
						<?php
						if ( have_posts() ) :
							$video_counter = 0;
							set_query_var( 'video_loop_has_ad', ( '' !== $ads['inside_list'] ) );
							while ( have_posts() ) :
								the_post();
								// get_query_var to get page id from url
								$page = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
								// number of categories to show per-page
								$posts_per_page = 60;
								// count total number of terms related to passed taxonomy
								$studio = get_terms(
    array(
        'taxonomy'   => 'studio',
        'hide_empty' => true,
    )
);
								$number_of_series = is_array( $studio ) ? count( $studio ) : 0;
								$offset           = ( $page - 1 ) * $posts_per_page;
								$max_num_pages    = (int) ceil( $number_of_series /$posts_per_page );

								if ( $page > $max_num_pages ) {
									global $wp_query;
									$wp_query->set_404();
									status_header( 404 );
									get_template_part( 404 );
									exit();
								}
								$terms = get_terms(
array(
'taxonomy' => 'studio',
'hide_empty' => true,
'number' => $posts_per_page,
'offset' => $offset,
'orderby' => 'count',
'order' => 'DESC',
)
);
								$count = is_array( $terms ) ? count( $terms ) : 0;

								if ( $count > 0 ) :
									foreach ( $terms as $term ) {
										$args             = array(
											'post_type'   => 'post',
											'posts_per_page' => 1,
											'show_count'  => 1,
											'orderby' => 'date',
'order' => 'DESC',
											'post_status' => 'publish',
											'tax_query'   => array(
												array(
													'taxonomy' => 'studio',
													'field'    => 'slug',
													'terms'    => $term->slug,
												),
											),
										);
										$video_from_actor = new WP_Query( $args );
										if ( $video_from_actor->have_posts() ) {
											$video_from_actor->the_post();
										}
										++$video_counter;
										?>
										<div class="col-6 <?php echo 'none' !== $sidebar_pos ? 'col-md-4' : 'col-md-3'; ?> col-lg-3 col-xl-2">
											<div class="video-block video-block-cat">
												<a class="thumb" href="<?php echo get_term_link( $term ); ?>" title="<?php echo $term->name; ?>">
										<?php
										echo function_exists( 'wps_term_image_img_html' ) ? wps_term_image_img_html( $term, 'studio', 'video-thumb' ) : '';
										?>
												</a>
												<a class="infos" href="<?php echo get_term_link( $term ); ?>" title="<?php echo $term->name; ?>">
    <span class="title"><?php echo $term->name; ?></span>
    <span class="cat-videos-count"><?php echo intval( $term->count ); ?> <?php echo class_exists( 'WPS_Professional_Suite' ) ? esc_html( WPS_Professional_Suite::text( 'เรื่อง', 'videos' ) ) : esc_html__( 'videos', 'wpst' ); ?></span>
</a>
											</div>
										</div>
										<?php
									}
									wpst_pagination( null, $max_num_pages );
								endif;
							endwhile;
						endif;
						?>
					</div>
				</div>
			</div>
			<?php wp_reset_postdata(); ?>
		</div>
		<?php if ( 'right' === $sidebar_pos ) : ?>
			<?php get_sidebar(); ?>
		<?php endif; ?>
	</div>
</div>
<?php if ( $ads['after_list'] ) : ?>
	<div class="happy-section"><?php echo $ads['after_list']; ?></div>
<?php endif; ?>
<?php
	get_footer();

