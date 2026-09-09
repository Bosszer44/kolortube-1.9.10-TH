<?php
/**
 * @package kolortube
 *
 * @package understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpst_site_info' ) ) {
	/**
	 * Add site info hook to WP hook library.
	 */
	function wpst_site_info() {
		do_action( 'wpst_site_info' );
	}
}

if ( ! function_exists( 'wpst_add_site_info' ) ) {
	add_action( 'wpst_site_info', 'wpst_add_site_info' );

	/**
	 * Add site info content.
	 */
	function wpst_add_site_info() {
		$the_theme = wp_get_theme();

		$site_info = sprintf(
			'<a href="%1$s">%2$s</a><span class="sep"> | </span>%3$s(%4$s)',
			esc_url( __( 'https://wordpress.org/', 'wpst' ) ),
			sprintf(
				/* translators:*/
				esc_html__( 'Proudly powered by %s', 'wpst' ),
				'WordPress'
			),
			sprintf( // WPCS: XSS ok.
				/* translators:*/
				esc_html__( 'Theme: %1$s by %2$s.', 'wpst' ),
				$the_theme->get( 'Name' ),
				'<a href="' . esc_url( __( 'https://understrap.com', 'wpst' ) ) . '">understrap.com</a>'
			),
			sprintf( // WPCS: XSS ok.
				/* translators:*/
				esc_html__( 'Version: %1$s', 'wpst' ),
				$the_theme->get( 'Version' )
			)
		);

		echo apply_filters( 'wpst_site_info_content', $site_info ); // WPCS: XSS ok.
	}
}
