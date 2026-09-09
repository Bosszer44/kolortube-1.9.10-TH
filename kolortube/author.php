<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
get_header();
?>

<div class="wrapper" id="author-wrapper">
	<div class="container" id="content" tabindex="-1">
		<div class="row">
			<!-- Do the left sidebar check -->
			<?php get_template_part( 'global-templates/left-sidebar-check' ); ?>
			<main class="site-main" id="main">
				<header class="page-header author-header">
					<?php
					$requested_author = isset( $_GET['author_name'] ) ? sanitize_title( wp_unslash( $_GET['author_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$curauth          = $requested_author ? get_user_by( 'slug', $requested_author ) : get_userdata( absint( $author ) );
					if ( ! $curauth ) {
						$curauth = (object) array( 'ID' => 0, 'nickname' => '', 'user_url' => '', 'user_description' => '' );
					}
					?>
					<h1><?php echo esc_html__( 'About:', 'wpst' ) . ' ' . esc_html( $curauth->nickname ); ?></h1>
					<?php if ( ! empty( $curauth->ID ) ) : ?>
						<?php echo get_avatar( $curauth->ID ); ?>
					<?php endif; ?>
					<?php if ( ! empty( $curauth->user_url ) || ! empty( $curauth->user_description ) ) : ?>
						<dl>
							<?php if ( ! empty( $curauth->user_url ) ) : ?>
								<dt><?php esc_html_e( 'Website', 'wpst' ); ?></dt>
								<dd>
									<a href="<?php echo esc_url( $curauth->user_url ); ?>"><?php echo esc_html( $curauth->user_url ); ?></a>
								</dd>
							<?php endif; ?>
							<?php if ( ! empty( $curauth->user_description ) ) : ?>
								<dt><?php esc_html_e( 'Profile', 'wpst' ); ?></dt>
								<dd><?php esc_html_e( $curauth->user_description ); ?></dd>
							<?php endif; ?>
						</dl>
					<?php endif; ?>
					<h2><?php echo esc_html__( 'Posts by', 'wpst' ) . ' ' . esc_html( $curauth->nickname ); ?>:</h2>
				</header><!-- .page-header -->
				<ul>
					<!-- The Loop -->
					<?php if ( have_posts() ) : ?>
						<?php
						while ( have_posts() ) :
							the_post();
							?>
							<li>
								<?php
								printf(
									'<a rel="bookmark" href="%1$s" title="%2$s %3$s">%3$s</a>',
									esc_url( apply_filters( 'the_permalink', get_permalink( $post ), $post ) ),
									esc_attr( __( 'Permanent Link:', 'wpst' ) ),
									the_title( '', '', false )
								);
								?>
								<?php wpst_posted_on(); ?>
								<?php esc_html_e( 'in', 'wpst' ); ?>
								<?php the_category( '&' ); ?>
							</li>
						<?php endwhile; ?>
					<?php else : ?>
						<?php get_template_part( 'loop-templates/content', 'none' ); ?>
					<?php endif; ?>
				<!-- End Loop -->
				</ul>
			</main><!-- #main -->
			<!-- The pagination component -->
			<?php wpst_pagination(); ?>
			<!-- Do the right sidebar check -->
			<?php get_template_part( 'global-templates/right-sidebar-check' ); ?>
		</div> <!-- .row -->
	</div><!-- #content -->
</div><!-- #author-wrapper -->
<?php get_footer(); ?>
