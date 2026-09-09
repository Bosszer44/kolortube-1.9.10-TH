<?php
/** Security and cleanup module. */
defined( 'ABSPATH' ) || exit;

final class WPS_Security {
	public static function register() {
		if ( get_theme_mod( 'wps_basic_hardening', false ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			add_filter( 'the_generator', '__return_empty_string' );
			add_filter( 'wp_headers', array( __CLASS__, 'security_headers' ) );
			add_action( 'template_redirect', array( __CLASS__, 'block_author_enumeration' ), 1 );
		}
		if ( get_theme_mod( 'wps_protect_admin', false ) ) {
			add_filter( 'login_errors', static function () { return __( 'Login failed.', 'wpst' ); } );
			add_filter( 'auto_update_theme', '__return_true' );
			// Keep administrator file editing available. Security hardening must not
			// force DISALLOW_FILE_EDIT because WP MY BOSS provides its own nonce,
			// path validation, backup and audit protections for file changes.
		}
		if ( get_theme_mod( 'wps_disable_rest_users', false ) ) {
			add_filter( 'rest_endpoints', array( __CLASS__, 'remove_rest_user_endpoints' ) );
		}
		if ( get_theme_mod( 'wps_redirect_attachment_pages', false ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'redirect_attachment_pages' ) );
		}
	}

	public static function disable_comments() {
		if ( ! get_theme_mod( 'wps_disable_comments', true ) ) {
			return;
		}
		add_filter( 'comments_open', '__return_false', 20, 2 );
		add_filter( 'pings_open', '__return_false', 20, 2 );
		add_filter( 'comments_array', '__return_empty_array', 10, 2 );
	}

	public static function disable_feed() {
		$enabled = get_theme_mod( 'wps_disable_feeds', true );
		if ( class_exists( 'WPS_Professional_Suite' ) ) {
			$enabled = WPS_Professional_Suite::get( 'feed_redirect_enabled', 1 );
		}
		if ( ! $enabled ) {
			return;
		}
		wp_safe_redirect( home_url( '/' ), 301, 'KolorTube' );
		exit;
	}

	public static function security_headers( $headers ) {
		$headers['X-Content-Type-Options'] = 'nosniff';
		$headers['X-Frame-Options']        = 'SAMEORIGIN';
		$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
		return $headers;
	}

	public static function block_author_enumeration() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( isset( $_GET['author'] ) && is_numeric( $_GET['author'] ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	public static function remove_rest_user_endpoints( $endpoints ) {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	public static function redirect_attachment_pages() {
		if ( ! is_attachment() ) {
			return;
		}
		$parent = wp_get_post_parent_id( get_queried_object_id() );
		wp_safe_redirect( $parent ? get_permalink( $parent ) : home_url( '/' ), 301 );
		exit;
	}
}
