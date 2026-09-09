<?php
defined( 'ABSPATH' ) || exit;
get_header(); ?>
<?php
	global $wp_query;
	$search_number = $wp_query->found_posts;
	$search_tag    = get_search_query();
	$sidebar_pos   = get_theme_mod( 'wpst_sidebar_position', 'left' );
	$ads           = array(
		'before_list' => WPS_Ads::html( 'ads_search_result_page_before_list' ),
		'inside_list' => WPS_Ads::html( 'ads_search_result_page_inside_list' ),
		'after_list'  => WPS_Ads::html( 'ads_search_result_page_after_list' ),
	);
	add_filter(
		'theme_mod_seo_search_title',
		function ( $value ) {
			global $wp_query;
			$search_number = $wp_query->found_posts;
			$search_tag    = get_search_query();
			$value         = str_replace( '%%search_tag%%', $search_tag, $value );
			$value         = str_replace( '%%search_number%%', $search_number, $value );
			return $value;
		}
	);
	add_filter(
		'theme_mod_seo_search_description',
		function ( $value ) {
			global $wp_query;
			$search_number = $wp_query->found_posts;
			$search_tag    = get_search_query();
			$value         = str_replace( '%%search_tag%%', $search_tag, $value );
			$value         = str_replace( '%%search_number%%', $search_number, $value );
			$value         = str_replace( "\n", '<br>', $value );
			return $value;
		}
	);
	?>
<div id="content">
	<?php if ( 'top' === get_theme_mod( 'seo_search_position', 'top' ) ) : ?>
		<div class="hero" <?php echo 'none' !== $sidebar_pos ? 'style="flex: 1;"' : ''; ?>>
			<div class="container" tabindex="-1">
				<div class="row hero-text">
					<div class="col-12 col-md-8 mx-auto">
						<h1><?php echo get_theme_mod( 'seo_search_title', $search_number . ' video found with ' . $search_tag ); ?></h1>
						<p class="hero-desc">
							<?php echo get_theme_mod( 'seo_search_description', 'The best ' . $search_tag . ' porn videos.' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
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
						<div class="order-1 order-sm-1 order-md-1 order-lg-1 order-xl-1 col-12 <?php echo 'none' !== $sidebar_pos ? 'col-md-8' : 'col-md-6'; ?> col-lg-6 col-xl-4">
							<?php if ( '' !== $ads['inside_list'] ) : ?>
							<div class="video-block-happy">
								<div class="video-block-happy-absolute d-flex align-items-center justify-content-center">
									<?php echo $ads['inside_list']; ?>
								</div>
							</div>
							<?php endif; ?>
						</div>
						<?php
						if ( have_posts() ) :
							$video_counter = 0;
							set_query_var( 'video_loop_has_ad', ( '' !== $ads['inside_list'] ) );
							while ( have_posts() ) :
								++$video_counter;
								set_query_var( 'video_counter', $video_counter );
								the_post();
								get_template_part( 'loop-templates/loop', 'video' );
							endwhile;
						endif;
						?>
					</div>
				</div>
			</div>
			<?php wpst_pagination(); ?>
			<?php wp_reset_postdata(); ?>			
		</div>
		<?php if ( 'right' === $sidebar_pos ) : ?>
			<?php get_sidebar(); ?>
		<?php endif; ?>
	</div>
	<?php if ( $ads['after_list'] ) : ?>
		<div class="happy-section"><?php echo $ads['after_list']; ?></div>
	<?php endif; ?>
	<?php if ( 'bottom' === get_theme_mod( 'seo_search_position', 'top' ) ) : ?>
		<div class="hero" <?php echo 'none' !== $sidebar_pos ? 'style="flex: 1;"' : ''; ?>>
			<div class="container" tabindex="-1">
				<div class="row hero-text">
					<div class="col-12 col-md-8 mx-auto">
						<h1><?php echo get_theme_mod( 'seo_search_title', $search_number . ' video found with ' . $search_tag ); ?></h1>
						<p class="hero-desc">
							<?php echo get_theme_mod( 'seo_search_description', 'The best ' . $search_tag . ' porn videos.' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php
	get_footer();
