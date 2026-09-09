<?php
defined( 'ABSPATH' ) || exit;
get_header(); ?>
<?php
	$sidebar_pos = get_theme_mod( 'sidebar_position', 'left' );
	$wps_home_sections_active = class_exists( 'WPS_Home_Sections' ) && WPS_Home_Sections::enabled();
	$ads         = array(
		'before_list' => WPS_Ads::html( 'ads_home_before_list' ),
		'inside_list' => WPS_Ads::html( 'ads_home_inside_list' ),
		'after_list'  => WPS_Ads::html( 'ads_home_after_list' ),
	);
	add_filter(
		'theme_mod_seo_home_description',
		function ( $value ) {
			$value = str_replace( "\n", '<br>', $value );
			return $value;
		}
	);

	$seo_home_title = get_theme_mod( 'seo_home_title', get_bloginfo( 'description' ) );
	$seo_home_description = get_theme_mod( 'seo_home_description', 'The best porn videos are on ' . get_bloginfo( 'name' ) . '!' );
	?>
<div id="content">
	<?php if ( ! $wps_home_sections_active && 'top' === get_theme_mod( 'seo_home_position', 'bottom' ) ) : ?>
		<section class="hero home-seo-block" aria-labelledby="home-seo-title" <?php echo 'none' !== $sidebar_pos ? 'style="flex: 1;"' : ''; ?>>
			<div class="container" tabindex="-1">
				<div class="row hero-text">
					<div class="col-12 col-md-8 mx-auto">
						<h1 id="home-seo-title"><?php echo esc_html( $seo_home_title ); ?></h1>
						<?php if ( '' !== trim( $seo_home_description ) ) : ?>
							<p class="hero-desc">
								<?php echo wp_kses_post( $seo_home_description ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>
	<?php if ( $ads['before_list'] ) : ?>
		<div class="happy-section"><?php echo $ads['before_list']; ?></div>
	<?php endif; ?>
	<?php if ( $wps_home_sections_active && method_exists( 'WPS_Home_Sections', 'render_before_latest' ) ) { WPS_Home_Sections::render_before_latest(); } elseif ( $wps_home_sections_active ) { WPS_Home_Sections::render(); } ?>
	<div class="container container-lg p-0 <?php echo 'none' !== $sidebar_pos ? 'd-flex flex-wrap' : ''; ?>">
		<?php if ( 'left' === $sidebar_pos ) : ?>
			<?php get_sidebar(); ?>
		<?php endif; ?>
		<div class="video-loop" <?php echo 'none' !== $sidebar_pos ? 'style="flex: 1;"' : ''; ?>>
			<div class="row no-gutters">
				<div class="col-12">					
					<div class="row no-gutters">
						<div class="order-1 order-sm-1 order-md-1 order-lg-1 order-xl-1 col-12 <?php echo 'none' !== $sidebar_pos ? 'col-md-8' : 'col-md-6'; ?> col-lg-6 col-xl-4">
							<?php if ( '' !== $ads['inside_list'] && wp_count_posts() > '1' ) : ?>
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
	<?php if ( $wps_home_sections_active && method_exists( 'WPS_Home_Sections', 'render_after_latest' ) ) { WPS_Home_Sections::render_after_latest(); } ?>
	<?php if ( $ads['after_list'] ) : ?>
		<div class="happy-section"><?php echo $ads['after_list']; ?></div>
	<?php endif; ?>
	<?php if ( ! $wps_home_sections_active && 'bottom' === get_theme_mod( 'seo_home_position', 'bottom' ) ) : ?>
		<section class="hero home-seo-block" aria-labelledby="home-seo-title">
			<div class="container" tabindex="-1">
				<div class="row hero-text">
					<div class="col-12 col-md-8 mx-auto">
						<h1 id="home-seo-title"><?php echo esc_html( $seo_home_title ); ?></h1>
						<?php if ( '' !== trim( $seo_home_description ) ) : ?>
							<p class="hero-desc">
								<?php echo wp_kses_post( $seo_home_description ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
