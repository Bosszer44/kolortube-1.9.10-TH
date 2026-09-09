<?php
/**
 * Customizer sanitization callbacks.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wps_sanitize_checkbox' ) ) {
	function wps_sanitize_checkbox( $value ) {
		return (bool) $value;
	}
}

if ( ! function_exists( 'wps_sanitize_select' ) ) {
	/**
	 * Sanitize a select value against the choices for its setting.
	 *
	 * Legacy KolorTube control IDs do not always match their setting IDs, so
	 * the compatibility map must be checked before consulting the manager.
	 *
	 * @param mixed                $value   Submitted value.
	 * @param WP_Customize_Setting $setting Customizer setting instance.
	 * @return string
	 */
	function wps_sanitize_select( $value, $setting ) {
		$value = sanitize_key( (string) $value );
		$id    = isset( $setting->id ) ? (string) $setting->id : '';

		$allowed_values = array(
			'enable_video_preview'       => array( 'yes', 'no' ),
			'enable_thumbs_rotation'     => array( 'yes', 'no' ),
			'enable_video_tracking_link' => array( 'yes', 'no' ),
			'sidebar_position'           => array( 'left', 'right', 'none' ),
			'mobile_columns'             => array( '1', '2' ),
			'seo_home_position'          => array( 'top', 'bottom' ),
			'seo_video_cat_position'     => array( 'top', 'bottom' ),
			'seo_video_tag_position'     => array( 'top', 'bottom' ),
			'seo_search_position'        => array( 'top', 'bottom' ),
			'video_listing_general_show_duration' => array( 'yes', 'no' ),
			'video_listing_general_show_title'    => array( 'yes', 'no' ),
			'wpst_sidebar_position'        => array( 'left', 'right', 'none' ),
			'wpst_container_type'          => array( 'container', 'container-fluid' ),
			'wpst_posts_index_style'       => array( 'default', 'masonry' ),
			'wps_heartbeat_mode'        => array( 'default', 'reduce', 'disable_frontend', 'disable' ),
			'wps_robots_mode'           => array( 'default', 'index', 'noindex' ),
			'wps_color_preset'         => array( 'custom', 'unified_pink', 'pink_candy', 'neon_night', 'aurora_blue', 'royal_purple', 'emerald_dark' ),
		);
		$allowed_values = apply_filters( 'wps_customizer_select_allowed_values', $allowed_values, $setting );

		if ( isset( $allowed_values[ $id ] ) && in_array( $value, $allowed_values[ $id ], true ) ) {
			return $value;
		}

		if ( isset( $setting->manager ) && is_object( $setting->manager ) ) {
			$control = $setting->manager->get_control( $id );
			if ( $control && is_array( $control->choices ) && array_key_exists( $value, $control->choices ) ) {
				return $value;
			}
		}

		return isset( $setting->default ) ? (string) $setting->default : '';
	}
}

if ( ! function_exists( 'wpst_theme_slug_sanitize_select' ) ) {
	function wpst_theme_slug_sanitize_select( $value, $setting ) {
		return wps_sanitize_select( $value, $setting );
	}
}

if ( ! function_exists( 'wps_sanitize_text' ) ) {
	function wps_sanitize_text( $value ) {
		return sanitize_text_field( $value );
	}
}

if ( ! function_exists( 'wps_sanitize_textarea' ) ) {
	function wps_sanitize_textarea( $value ) {
		return sanitize_textarea_field( $value );
	}
}

if ( ! function_exists( 'wps_sanitize_html' ) ) {
	function wps_sanitize_html( $value ) {
		return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
	}
}

if ( ! function_exists( 'wps_sanitize_url' ) ) {
	function wps_sanitize_url( $value ) {
		return esc_url_raw( trim( (string) $value ) );
	}
}

if ( ! function_exists( 'wps_sanitize_color' ) ) {
	function wps_sanitize_color( $value ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : '';
	}
}

if ( ! function_exists( 'wps_sanitize_integer' ) ) {
	function wps_sanitize_integer( $value ) {
		return absint( $value );
	}
}
