<?php
/**
 * @package kolortubeme's default settings
 *
 * @package understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpst_setup_theme_default_settings' ) ) {
	function wpst_setup_theme_default_settings() {

		// check if settings are set, if not set defaults.
		// Caution: DO NOT check existence using === always check with == .
		// Latest blog posts style.
		$wpst_posts_index_style = get_theme_mod( 'wpst_posts_index_style' );
		if ( '' == $wpst_posts_index_style ) {
			set_theme_mod( 'wpst_posts_index_style', 'default' );
		}

		// Sidebar position.
		$wpst_sidebar_position = get_theme_mod( 'wpst_sidebar_position' );
		if ( '' == $wpst_sidebar_position ) {
			set_theme_mod( 'wpst_sidebar_position', 'left' );
		}

		// Container width.
		// $wpst_container_type = get_theme_mod( 'wpst_container_type' );
		// if ( '' == $wpst_container_type ) {
		// set_theme_mod( 'wpst_container_type', 'container' );
		// }
	}
}
