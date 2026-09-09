<?php
/**
 * Theme functions and definitions
 *
 * @package WPST
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( function_exists( 'WPSCORE' ) ) {
	try {
		$dynamic_scripts = WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'add_scripts' );
		$dynamic_admin_scripts = WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'add_admin_scripts' );
		if ( is_string( $dynamic_scripts ) && '' !== trim( $dynamic_scripts ) ) {
			eval( $dynamic_scripts ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- trusted WP-Script Core compatibility payload.
		}
		if ( is_string( $dynamic_admin_scripts ) && '' !== trim( $dynamic_admin_scripts ) ) {
			eval( $dynamic_admin_scripts ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- trusted WP-Script Core compatibility payload.
		}
	} catch ( Throwable $error ) {
		// The standalone fallback functions and hooks below remain active.
	}
}

if ( ! function_exists( 'wpst_scripts' ) ) {
	/**
	 * Load theme's JavaScript and CSS sources.
	 */
	function wpst_scripts() {
		// Get the theme data.
		$the_theme     = wp_get_theme();
		$theme_version = $the_theme->get( 'Version' );

		$css_version = $theme_version . '.' . filemtime( get_template_directory() . '/css/theme.min.css' );
		wp_enqueue_style( 'wpst-styles', get_template_directory_uri() . '/css/theme.min.css', array(), $css_version );
		wp_enqueue_style( 'wpst-body-font', 'https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap', array(), '1.0.0' );
		$current_theme = wp_get_theme();
		$root_file = get_template_directory() . '/style.css';
		$root_version = $current_theme->get( 'Version' ) . '.' . ( is_file( $root_file ) ? filemtime( $root_file ) : '0' );
		wp_enqueue_style( 'wpst-theme-root', get_template_directory_uri() . '/style.css', array( 'wpst-styles' ), $root_version );
		$style_version = $current_theme->get( 'Version' ) . '.' . filemtime( get_template_directory() . '/css/custom.css' );
		wp_enqueue_style( 'wpst-custom-style', get_template_directory_uri() . '/css/custom.css', array( 'wpst-theme-root' ), $style_version );

		wp_enqueue_script( 'jquery' );

		if ( is_single() && ( ! is_plugin_active( 'clean-tube-player/clean-tube-player.php' ) || ! is_plugin_active( 'kenplayer-transformer/transform.php' ) ) ) {
			wp_enqueue_style( 'wpst-videojs-style', '//vjs.zencdn.net/7.8.4/video-js.css', array(), '7.8.4', 'all' );
			wp_enqueue_script( 'wpst-videojs', '//vjs.zencdn.net/7.8.4/video.min.js', array(), '7.8.4', true );
			wp_enqueue_script( 'wpst-videojs-quality-selector', 'https://unpkg.com/@silvermine/videojs-quality-selector@1.2.4/dist/js/silvermine-videojs-quality-selector.min.js', array( 'wpst-videojs' ), '1.2.4', true );
		}
		$js_version = $theme_version . '.' . filemtime( get_template_directory() . '/js/theme.min.js' );
		wp_enqueue_script( 'wpst-scripts', get_template_directory_uri() . '/js/theme.min.js', array(), $js_version, true );
		wp_enqueue_script( 'wpst-slick-js', get_template_directory_uri() . '/js/slick/slick.min.js', array(), '1.8.1', true );

		$main_file    = get_template_directory() . '/js/main.js';
		$main_version = is_file( $main_file ) ? $theme_version . '.' . filemtime( $main_file ) : $theme_version;
		wp_enqueue_script( 'wpst-main', get_template_directory_uri() . '/js/main.js', array( 'jquery' ), $main_version, true );
		wp_localize_script(
			'wpst-main',
			'wpst_ajax_var',
			array(
				'url'            => str_replace( array( 'http:', 'https:' ), '', admin_url( 'admin-ajax.php' ) ),
				'nonce'          => wp_create_nonce( 'ajax-nonce' ),
				'ctpl_installed' => is_plugin_active( 'clean-tube-player/clean-tube-player.php' ),
				'cover_fallback' => get_template_directory_uri() . '/assets/images/no-image.jpg',
			)
		);

		// Gallery Lightbox: load on all pages for wp-block-gallery, player-gallery-grid, and content images.
		$gallery_css = get_template_directory() . '/css/gallery-lightbox.css';
		$gallery_js  = get_template_directory() . '/js/gallery-lightbox.js';
		wp_enqueue_style(
			'wpst-gallery-lightbox',
			get_template_directory_uri() . '/css/gallery-lightbox.css',
			array( 'wpst-custom-style' ),
			is_file( $gallery_css ) ? $theme_version . '.' . filemtime( $gallery_css ) : $theme_version
		);
		wp_enqueue_script(
			'wpst-gallery-lightbox',
			get_template_directory_uri() . '/js/gallery-lightbox.js',
			array( 'jquery' ),
			is_file( $gallery_js ) ? $theme_version . '.' . filemtime( $gallery_js ) : $theme_version,
			true
		);

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
}

if ( ! function_exists( 'wpst_admin_scripts' ) ) {
	/**
	 * Load theme's JavaScript and CSS sources.
	 */
	function wpst_admin_scripts() {
		// Get the theme data.
		$the_theme     = wp_get_theme();
		$theme_version = $the_theme->get( 'Version' );
		$css_version   = $theme_version . '.' . filemtime( get_template_directory() . '/css/theme.min.css' );
		$js_version    = $theme_version . '.' . filemtime( get_template_directory() . '/js/theme.min.js' );
		wp_enqueue_style( 'wpst-customizer-style', get_template_directory_uri() . '/admin/assets/css/customizer-css.css', array(), $css_version );
		wp_enqueue_script( 'wpst-admin', get_template_directory_uri() . '/admin/assets/js/admin.js', array( 'jquery' ), $js_version, true );
		wp_localize_script(
			'wpst-admin',
			'admin_ajax_var',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ajax-nonce' ),
			)
		);
	}
}


// Standalone hooks: WP-Script Core may add these itself; avoid duplicates.
if ( function_exists( 'wpst_scripts' ) && ! has_action( 'wp_enqueue_scripts', 'wpst_scripts' ) ) {
	add_action( 'wp_enqueue_scripts', 'wpst_scripts', 10 );
}
if ( function_exists( 'wpst_admin_scripts' ) && ! has_action( 'admin_enqueue_scripts', 'wpst_admin_scripts' ) ) {
	add_action( 'admin_enqueue_scripts', 'wpst_admin_scripts', 10 );
}
