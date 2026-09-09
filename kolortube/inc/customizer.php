<?php
/**
 * Modular Customizer bootstrap.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

$wps_customizer_files = array(
	'/customizer/sanitizers.php',
	'/customizer/color-registry.php',
	'/customizer/class-wps-customizer.php',
	'/customizer/class-wps-customizer-framework.php',
	'/customizer/class-wps-customizer-video.php',
	'/customizer/class-wps-customizer-colors.php',
	'/customizer/class-wps-customizer-layout.php',
	'/customizer/class-wps-customizer-ads-panel.php',
	'/customizer/class-wps-customizer-ads-home.php',
	'/customizer/class-wps-customizer-ads-single.php',
	'/customizer/class-wps-customizer-ads-actor.php',
	'/customizer/class-wps-customizer-ads-category.php',
	'/customizer/class-wps-customizer-ads-tag.php',
	'/customizer/class-wps-customizer-ads-search.php',
	'/customizer/class-wps-customizer-seo-panel.php',
	'/customizer/class-wps-customizer-seo-home.php',
	'/customizer/class-wps-customizer-seo-video.php',
	'/customizer/class-wps-customizer-seo-category.php',
	'/customizer/class-wps-customizer-seo-tag.php',
	'/customizer/class-wps-customizer-seo-search.php',
	'/customizer/class-wps-customizer-copyright.php',
	'/customizer/class-wps-customizer-developer.php',
	'/customizer/customizer-output.php',
);

foreach ( $wps_customizer_files as $wps_customizer_file ) {
	require_once get_template_directory() . '/inc' . $wps_customizer_file;
}

if ( ! function_exists( 'wpst_customize_register' ) ) {
	function wpst_customize_register( $wp_customize ) {
		$wp_customize->remove_section( 'tagline' );
		$wp_customize->remove_section( 'colors' );
		$wp_customize->remove_section( 'static_front_page' );
		$wp_customize->remove_section( 'background_image' );
	}
}
add_action( 'customize_register', 'wpst_customize_register' );

if ( ! function_exists( 'wpst_theme_customize_register' ) ) {
	function wpst_theme_customize_register( $wp_customize ) {
		require_once get_template_directory() . '/inc/customizer/class-wps-text-editor-control.php';
		WPS_Customizer::register( $wp_customize );
	}
}
add_action( 'customize_register', 'wpst_theme_customize_register' );

if ( ! function_exists( 'wpst_editor_customizer_script' ) ) {
	function wpst_editor_customizer_script() {
		$path    = get_template_directory() . '/js/customizer-panel.js';
		$version = file_exists( $path ) ? (string) filemtime( $path ) : WPS_VERSION;
		wp_enqueue_script( 'wp-editor-customizer', get_template_directory_uri() . '/js/customizer-panel.js', array( 'jquery' ), $version, true );
	}
}
add_action( 'customize_controls_enqueue_scripts', 'wpst_editor_customizer_script' );
