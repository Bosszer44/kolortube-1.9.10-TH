<?php
/**
 * Template Name: Left Sidebar Layout
 *
 * @package kolortube
 * This template can be used to override the default template and sidebar setup
 *
 * @package understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

get_header();
$container = get_theme_mod( 'wpst_container_type' );
?>

<div class="wrapper" id="page-wrapper">
	<div class="<?php echo esc_attr( $container ); ?>" id="content">
		<div class="row">
			<?php get_template_part( 'sidebar-templates/sidebar', 'left' ); ?>
			<div class="<?php echo is_active_sidebar( 'left-sidebar' ) ? 'col-md-8' : 'col-md-12'; ?> content-area" id="primary">
				<main class="site-main" id="main" role="main">
					<?php if ( have_posts() ) : ?>
						<?php while ( have_posts() ) : ?>
							<?php the_post(); ?>
							<?php get_template_part( 'loop-templates/content', 'page' ); ?>
							<?php
							// If comments are open or we have at least one comment, load up the comment template.
							if ( comments_open() || get_comments_number() ) :
								comments_template();
							endif;
							?>
						<?php endwhile; // end of the loop. ?>
					<?php endif; ?>
				</main><!-- #main -->
			</div><!-- #primary -->
		</div><!-- .row -->
	</div><!-- #content -->
</div><!-- #page-wrapper -->

<?php get_footer(); ?>
