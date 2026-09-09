<?php
/**
 * Template Name: Tags
 **/
get_header();
$posts_per_row  = 3;
$posts_per_page = 15;
$sidebar_pos    = get_theme_mod( 'wpst_sidebar_position', 'none' ); ?>

<style type="text/css">
.letter-group { width: 100%; }
.letter-cell { width: 5%; height: 2em; text-align: center; padding-top: 8px; margin-bottom: 8px; background: #e0e0e0; float: left; }
.row-cells { width: 70%; float: right; margin-right: 180px; }
.title-cell { width: 30%;  float: left; overflow: hidden; margin-bottom: 8px; }
.clear { clear: both; }
</style>

<div class="hero" <?php echo 'none' !== $sidebar_pos ? 'style="flex: 1;"' : ''; ?>>
	<div class="container" tabindex="-1">
		<div class="row hero-text">
			<div class="col-12 col-md-8 mx-auto">
				<h1><?php the_title(); ?></h1>
			</div>
		</div>
	</div>
</div>
<div class="archive-tags-list">
	<div class="archive-content clearfix-after template-tags">
		<?php
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
				the_content();
				$terms = get_terms( 'post_tag' );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$term_list = array();
					foreach ( $terms as $term ) {
						$term_name    = wpst_removeAccents( $term->name );
						$first_letter = mb_substr( $term_name, 0, 1, 'utf8' );
						if ( is_numeric( $first_letter ) ) {
							$first_letter = '#';
						} else {
							$first_letter = strtoupper( $first_letter );
						}
						$term_list[ $first_letter ][] = $term;
					}
					unset( $term );
					foreach ( $term_list as $key => $value ) {
						echo '<div class="tags-letter-block"><div class="tag-letter">' . $key . '</div>';
						echo '<div class="tag-items">';
						foreach ( $value as $term ) {
							echo '<div class="tag-item"><a href="' . get_term_link( $term ) . '" title="' . sprintf( __( 'View all post filed under %s', 'my_localization_domain' ), $term->name ) . '">' . $term->name . '</a></div>';
						}
						echo '</div></div><div class="clear"></div>';
					}
				}
				?>
	</div>
				<?php
	endwhile;
endif;
		?>
</div>

<?php
get_footer();

