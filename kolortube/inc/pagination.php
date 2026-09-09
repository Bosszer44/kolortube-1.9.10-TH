<?php
/**
 * @package kolortube
 *
 * @package understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpst_pagination' ) ) {
	/**
	 * Pagination function.
	 *
	 * @param WP_Query $query  A custom WP_Query to build the pagination.
	 * @param int      $max_num_pages  The maximum number of pages we want to paginate.
	 *
	 * @return void
	 */
	function wpst_pagination( $query = null, $max_num_pages = null ) {
		if ( null === $query ) {
			global $wp_query;
			$query = $wp_query;
		}
		if ( null === $max_num_pages ) {
			$max_num_pages = intval( $query->max_num_pages );
		}
		if ( $max_num_pages <= 1 ) {
			return;
		}
		// Define $current_page.
		$current_page = 1;
		if ( get_query_var( 'page' ) >= 1 ) {
			$current_page = intval( get_query_var( 'page' ) );
		}
		if ( get_query_var( 'paged' ) >= 1 ) {
			$current_page = intval( get_query_var( 'paged' ) );
		}
		// Generate desktop pagination links.
		$args  = array(
			'mid_size'           => 2,
			'prev_next'          => true,
			'prev_text'          => __( '&laquo;', 'wpst' ),
			'next_text'          => __( '&raquo;', 'wpst' ),
			'screen_reader_text' => __( 'Posts navigation', 'wpst' ),
			'type'               => 'array',
			'current'            => max( 1, $current_page ),
			'total'              => $max_num_pages,
		);
		$links = paginate_links( $args );
		?>
		<nav aria-label="<?php echo esc_html( $args['screen_reader_text'] ); ?>" class="d-none d-md-block col-12">
			<ul class="pagination pagination-lg my-4 justify-content-center">
				<?php foreach ( $links as $key => $link ) : ?>
					<li class="page-item <?php echo strpos( $link, 'current' ) ? 'active' : ''; ?>">
						<?php echo str_replace( 'page-numbers', 'page-link', $link ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
		// Generate mobile pagination links.
		$previous_posts_url = get_pagenum_link( $current_page - 1 );
		$next_posts_url     = get_pagenum_link( $current_page + 1 );
		?>
		<nav aria-label="<?php echo esc_html( $args['screen_reader_text'] ); ?>" class="d-block d-md-none col-12">
			<ul class="pagination pagination-lg my-4 justify-content-center">
				<?php if ( $current_page > 1 ) : ?>
					<li class="page-item"><a class="page-link" href="<?php echo esc_url( $previous_posts_url ); ?>"><?php esc_html_e( '&laquo;', 'wpst' ); ?></a></li>
				<?php endif; ?>
				<li class="page-item active"><span aria-current="page" class="page-link current"><?php echo intval( $current_page ); ?></span></li>
				<?php if ( $current_page < $max_num_pages ) : ?>
				<li class="page-item"> <a class="page-link" href="<?php echo esc_url( $next_posts_url ); ?>"><?php esc_html_e( '&raquo;', 'wpst' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</nav>
		<?php
	}
}
