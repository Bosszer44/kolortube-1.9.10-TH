<?php
/**
 * Template Name: Categories
 **/
get_header(); ?>
<?php
	$sidebar_pos = get_theme_mod( 'sidebar_position', 'left' );
	$ads         = array(
		'before_list' => WPS_Ads::html( 'ads_category_page_before_list' ),
		'inside_list' => WPS_Ads::html( 'ads_category_page_inside_list' ),
		'after_list'  => WPS_Ads::html( 'ads_category_page_after_list' ),
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
								$per_page = 60;
								// count total number of terms related to passed taxonomy
								$categories = get_terms(
array(
'taxonomy' => 'category',
'hide_empty' => true,
)
);

$number_of_series = is_array( $categories ) ? count( $categories ) : 0;
								$offset           = ( $page - 1 ) * $per_page;
								$max_num_pages    = (int) ceil( $number_of_series / $per_page );
								if ( $page > $max_num_pages ) {
									global $wp_query;
									$wp_query->set_404();
									status_header( 404 );
									get_template_part( 404 );
									exit();
								}
								$terms = get_terms(
array(
'taxonomy' => 'category',
'hide_empty' => true,
'number' => $per_page,
'offset' => $offset,
'orderby' => 'count',
'order' => 'DESC',
)
);
								$count = is_array( $terms ) ? count( $terms ) : 0;
								if ( $count > 0 ) :
									foreach ( $terms as $term ) {
										$args                = array(
											'post_type'   => 'post',
											'posts_per_page' => 1,
											'show_count'  => 1,
											'orderby' => 'date',
'order'   => 'DESC',
											'post_status' => 'publish',
											'tax_query'   => array(
												array(
													'taxonomy' => 'category',
													'field' => 'slug',
													'terms' => $term->slug,
												),
											),
										);
										$video_from_category = new WP_Query( $args );
										if ( $video_from_category->have_posts() ) {
											$video_from_category->the_post();
										}

										$thumb = function_exists( 'wps_term_image_img_html' ) ? wps_term_image_img_html( $term, 'category', 'video-thumb' ) : '';
										++$video_counter;
										set_query_var( 'video_counter', $video_counter );
										set_query_var( 'category_thumb', $thumb );
										set_query_var( 'category_name', $term->name );
										set_query_var( 'category_videos_count', $term->count );

										get_template_part( 'loop-templates/loop', 'category' );
										?>
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

