<?php
/**
 * Left sidebar check.
 *
 * @package wpst
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$sidebar_pos = get_theme_mod( 'wpst_sidebar_position' );
?>

<?php if ( 'left' === $sidebar_pos ) : ?>
	<?php get_template_part( 'sidebar-templates/sidebar', 'left' ); ?>
<?php endif; ?>

<div class="col-md content-area" id="primary">
