<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
get_header();
?>

<div class="wrapper" id="page-wrapper">
	<div class="container" id="content" tabindex="-1">
		<div class="row">
			<div class="col-12 col-md-6 mx-auto">
				<main class="site-main" id="main">
					<section class="error-404 not-found text-center">
						<header class="page-header">
							<h1 class="page-title"><?php esc_html_e( 'Oops! That page can\'t be found.', 'wpst' ); ?></h1>
						</header><!-- .page-header -->
						<div class="page-content">
							<p><?php esc_html_e( 'It looks like nothing was found. Please try another search.', 'wpst' ); ?></p>
						</div><!-- .page-content -->
					</section><!-- .error-404 -->
				</main><!-- #main -->
			</div><!-- #primary -->
		</div><!-- .row -->
	</div><!-- #content -->
	<div class="video-loop">
		<div class="container container-lg">
			<h2 class="text-center mb-4"><?php esc_html_e( 'In the meantime here are some videos you may like...', 'wpst' ); ?></h2>
			<div class="row no-gutters">
				<?php
				$args       = array(
					'numberposts' => 60,
					'orderby'     => 'rand',
				);
				$rand_posts = get_posts( $args );
				foreach ( $rand_posts as $post ) {
					get_template_part( 'loop-templates/loop', 'video' );
				}
				?>
			</div>
		</div>
	</div>
</div><!-- #error-404-wrapper -->

<?php get_footer(); ?>
