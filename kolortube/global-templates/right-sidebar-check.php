<?php
/**
 * @package kolortube.
 *
 * @package understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>

</div><!-- #closing the primary container from /global-templates/left-sidebar-check.php -->

<?php $sidebar_pos = get_theme_mod( 'wpst_sidebar_position' ); ?>

<?php if ( 'right' === $sidebar_pos || 'both' === $sidebar_pos ) : ?>

	<?php get_template_part( 'sidebar-templates/sidebar', 'right' ); ?>

<?php endif; ?>
