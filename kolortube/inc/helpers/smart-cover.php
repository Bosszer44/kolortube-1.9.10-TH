<?php
/**
 * Smart cover fallback layer.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Smart_Cover {
	/** Register the fallback filter. */
	public static function register() {
		if ( get_theme_mod( 'wps_smart_cover_enabled', true ) ) {
			add_filter( 'post_thumbnail_html', array( __CLASS__, 'replace_missing_thumbnail' ), 10, 5 );
		}
	}

	/** Return a smart cover URL. */
	public static function get_url( $post_id ) {
		$cover = WPS_Bulk_Cover_Generator::resolve_cover( $post_id, true );
		return is_array( $cover ) ? $cover['url'] : '';
	}

	/** Preserve native featured markup; only fill empty thumbnails unless explicitly forced. */
	public static function replace_missing_thumbnail( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		$force = get_theme_mod( 'wps_force_smart_cover', false );
		if ( $html && ! $force ) {
			return $html;
		}

		$cover = WPS_Bulk_Cover_Generator::resolve_cover( $post_id, true );
		if ( ! $cover || empty( $cover['url'] ) ) {
			return $html;
		}

		if ( ! empty( $cover['attachment_id'] ) ) {
			$image = wp_get_attachment_image( $cover['attachment_id'], $size, false, is_array( $attr ) ? $attr : array() );
			if ( $image ) {
				return $image;
			}
		}

		$attributes = is_array( $attr ) ? $attr : array();
		$classes    = isset( $attributes['class'] ) ? $attributes['class'] : 'attachment-post-thumbnail size-post-thumbnail wp-post-image';
		$alt        = isset( $attributes['alt'] ) ? $attributes['alt'] : get_the_title( $post_id );
		return sprintf(
			'<img src="%1$s" class="%2$s"%4$s alt="%3$s">',
			esc_url( $cover['url'] ),
			esc_attr( $classes ),
			esc_attr( wp_strip_all_tags( $alt ) ),
			class_exists( 'WPS_Performance' ) ? WPS_Performance::image_loading_attributes() : ' decoding="async"'
		);
	}
}

if ( ! function_exists( 'av_get_smart_cover' ) ) {
	function av_get_smart_cover( $post_id ) {
		return WPS_Smart_Cover::get_url( $post_id );
	}
}
if ( ! function_exists( 'av_replace_thumbnail' ) ) {
	function av_replace_thumbnail( $html, $post_id ) {
		return WPS_Smart_Cover::replace_missing_thumbnail( $html, $post_id, get_post_thumbnail_id( $post_id ), 'post-thumbnail', array() );
	}
}
