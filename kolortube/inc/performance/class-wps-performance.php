<?php
/** Performance module. */
defined( 'ABSPATH' ) || exit;

final class WPS_Performance {
	public static function register() {
		if ( get_theme_mod( 'wps_disable_emojis', false ) ) {
			self::disable_emojis();
		}
		if ( get_theme_mod( 'wps_disable_xmlrpc', false ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );
		}
		if ( get_theme_mod( 'wps_lazy_load', true ) && ! self::is_managed_by_litespeed( 'lazy_load' ) ) {
			add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'image_lazy_attributes' ), 10, 3 );
			add_filter( 'the_content', array( __CLASS__, 'lazy_load_iframes' ), 20 );
		}
		if ( get_theme_mod( 'wps_webp_support', true ) ) {
			add_filter( 'upload_mimes', array( __CLASS__, 'allow_webp' ) );
			add_filter( 'file_is_displayable_image', array( __CLASS__, 'displayable_webp' ), 10, 2 );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'heartbeat_control' ), 100 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'heartbeat_control' ), 100 );
	}

	/** Whether LiteSpeed owns an overlapping performance feature. */
	private static function is_managed_by_litespeed( $feature ) {
		return class_exists( 'WPS_LiteSpeed' ) && WPS_LiteSpeed::manages( $feature );
	}

	/**
	 * Return safe image attributes for custom theme markup.
	 *
	 * LiteSpeed receives clean source markup when it owns lazy loading. The
	 * framework adds native lazy loading only while operating as the fallback.
	 *
	 * @return string
	 */
	public static function image_loading_attributes() {
		$attributes = ' decoding="async"';
		if ( get_theme_mod( 'wps_lazy_load', true ) && ! self::is_managed_by_litespeed( 'lazy_load' ) ) {
			$attributes .= ' loading="lazy"';
		}
		return $attributes;
	}

	/** Return the effective performance ownership map for diagnostics. */
	public static function effective_status() {
		$litespeed = class_exists( 'WPS_LiteSpeed' ) && WPS_LiteSpeed::is_primary();
		return array(
			'lazy_load'         => $litespeed ? __( 'Managed by LiteSpeed Cache', 'wpst' ) : ( get_theme_mod( 'wps_lazy_load', true ) ? __( 'Framework enabled', 'wpst' ) : __( 'Disabled', 'wpst' ) ),
			'asset_optimization' => $litespeed ? __( 'Managed by LiteSpeed Cache', 'wpst' ) : __( 'Not provided by theme', 'wpst' ),
			'image_optimization' => $litespeed ? __( 'Managed by LiteSpeed Cache', 'wpst' ) : __( 'WebP upload compatibility only', 'wpst' ),
		);
	}

	private static function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', array( __CLASS__, 'remove_tinymce_emoji' ) );
		add_filter( 'wp_resource_hints', array( __CLASS__, 'remove_emoji_dns_prefetch' ), 10, 2 );
	}

	public static function remove_tinymce_emoji( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
	}

	public static function remove_emoji_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/' );
			$urls      = array_filter( $urls, static function ( $url ) use ( $emoji_url ) { return false === strpos( $url, $emoji_url ); } );
		}
		return $urls;
	}

	public static function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public static function image_lazy_attributes( $attr, $attachment, $size ) {
		unset( $attachment, $size );
		if ( empty( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
		}
		if ( empty( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}

	public static function lazy_load_iframes( $content ) {
		if ( is_admin() || false === stripos( $content, '<iframe' ) ) {
			return $content;
		}
		return preg_replace_callback(
			'/<iframe\b(?![^>]*\bloading=)([^>]*)>/i',
			static function ( $matches ) { return '<iframe loading="lazy"' . $matches[1] . '>'; },
			$content
		);
	}

	public static function allow_webp( $mimes ) {
		$mimes['webp'] = 'image/webp';
		return $mimes;
	}

	public static function displayable_webp( $result, $path ) {
		$filetype = wp_check_filetype( $path );
		if ( is_array( $filetype ) && isset( $filetype['type'] ) && 'image/webp' === $filetype['type'] ) {
			return true;
		}
		return $result;
	}

	public static function heartbeat_control() {
		$mode = get_theme_mod( 'wps_heartbeat_mode', 'default' );
		if ( 'reduce' === $mode ) {
			add_filter( 'heartbeat_settings', static function ( $settings ) { $settings['interval'] = 60; return $settings; } );
			return;
		}
		if ( 'disable_frontend' === $mode && ! is_admin() ) {
			wp_deregister_script( 'heartbeat' );
			return;
		}
		if ( 'disable' === $mode && ! self::is_post_editor() ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	private static function is_post_editor() {
		global $pagenow;
		return is_admin() && in_array( $pagenow, array( 'post.php', 'post-new.php' ), true );
	}
}
