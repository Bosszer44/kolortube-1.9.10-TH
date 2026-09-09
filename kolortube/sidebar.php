<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! is_active_sidebar( 'sidebar' ) ) {
	return;
}
$sidebar_pos = get_theme_mod( 'sidebar_position', 'left' );
?>

<?php if ( $sidebar_pos != 'none' ) : ?>
	<div class="widget-area" id="secondary" role="complementary">
		<?php dynamic_sidebar( 'sidebar' ); ?>
	</div><!-- #secondary -->
<?php endif; ?>
