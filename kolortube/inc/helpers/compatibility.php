<?php
/**
 * Public compatibility layer for KolorTube and WP-Script integrations.
 *
 * Existing public function names remain available while their implementations
 * are delegated to modular classes when those classes are present.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Compatibility {
	/** Return legacy theme_mod keys used by the parent theme. */
	public static function legacy_theme_mod_keys() {
		return array(
			'ads_actor_page_after_list', 'ads_actor_page_before_list', 'ads_actor_page_inside_list',
			'ads_category_page_after_list', 'ads_category_page_before_list', 'ads_category_page_inside_list',
			'ads_home_after_list', 'ads_home_before_list', 'ads_home_inside_list', 'ads_home_inside_related_videos_list',
			'ads_search_result_page_after_list', 'ads_search_result_page_before_list', 'ads_search_result_page_inside_list',
			'ads_single_video_page_before_related_videos', 'ads_single_video_page_beside_player_1', 'ads_single_video_page_beside_player_2',
			'ads_single_video_page_in_player_1', 'ads_single_video_page_in_player_2', 'ads_single_video_page_under_player',
			'ads_tag_page_after_list', 'ads_tag_page_before_list', 'ads_tag_page_inside_list',
			'body_background_color', 'copyright_content', 'custom_logo', 'enable_thumbs_rotation', 'enable_video_preview',
			'enable_video_tracking_link', 'google_analytics_code', 'link_color', 'main_color', 'meta_verification_code',
			'mobile_columns', 'nav_menu_locations', 'other_script_codes', 'seo_home_description', 'seo_home_position',
			'seo_home_title', 'seo_more_related_videos_button', 'seo_related_videos_title', 'seo_search_description',
			'seo_search_position', 'seo_search_title', 'seo_video_cat_description', 'seo_video_cat_position',
			'seo_video_cat_title', 'seo_video_tag_description', 'seo_video_tag_position', 'seo_video_tag_title',
			'seo_video_title', 'seo_video_tracking_button', 'sidebar_position', 'video_listing_general_show_duration',
			'video_listing_general_show_title', 'wpst_container_type', 'wpst_posts_index_style', 'wpst_sidebar_position',
		);
	}

	/** Return the legacy external thumbnail URL, if one is configured. */
	public static function legacy_cover_url( $post_id ) {
		$url = get_post_meta( absint( $post_id ), 'thumb', true );
		return is_string( $url ) ? esc_url_raw( trim( $url ) ) : '';
	}

	/**
	 * Fall back to the old Understrap sidebar key only when the canonical
	 * KolorTube key has never been stored. Priority 5 allows a Customizer
	 * preview filter to override this fallback later in the filter chain.
	 */
	public static function canonical_sidebar_position( $value ) {
		$allowed = array( 'left', 'right', 'none' );
		$mods    = get_theme_mods();
		if ( ! array_key_exists( 'sidebar_position', $mods ) && isset( $mods['wpst_sidebar_position'] ) && in_array( $mods['wpst_sidebar_position'], $allowed, true ) ) {
			return $mods['wpst_sidebar_position'];
		}
		return in_array( $value, $allowed, true ) ? $value : 'left';
	}

	/** Keep old templates/child themes reading wpst_sidebar_position in sync. */
	public static function legacy_sidebar_position( $value ) {
		$value = in_array( $value, array( 'left', 'right', 'none' ), true ) ? $value : 'left';
		return get_theme_mod( 'sidebar_position', $value );
	}
}

add_filter( 'theme_mod_sidebar_position', array( 'WPS_Compatibility', 'canonical_sidebar_position' ), 5 );
add_filter( 'theme_mod_wpst_sidebar_position', array( 'WPS_Compatibility', 'legacy_sidebar_position' ), 20 );


if ( ! function_exists( 'wpst_get_video_preview' ) ) {
	function wpst_get_video_preview( $post_id = null ) {
		return class_exists( 'WPS_Media' ) ? WPS_Media::get_video_preview( $post_id ) : '';
	}
}
if ( ! function_exists( 'wpst_removeAccents' ) ) {
	function wpst_removeAccents( $str ) {
		return remove_accents( $str );
	}
}
if ( ! function_exists( 'wpst_selected_filter' ) ) {
	function wpst_selected_filter( $filter ) {
		return class_exists( 'WPS_Query' ) ? WPS_Query::selected_filter( $filter ) : false;
	}
}
if ( ! function_exists( 'wpst_get_filter_title' ) ) {
	function wpst_get_filter_title() {
		return class_exists( 'WPS_Query' ) ? WPS_Query::get_filter_title() : '';
	}
}
if ( ! function_exists( 'wpst_remove_mce_css' ) ) {
	function wpst_remove_mce_css( $stylesheets ) {
		unset( $stylesheets );
		return '';
	}
}
if ( ! function_exists( 'wpst_get_nopaging_url' ) ) {
	function wpst_get_nopaging_url() {
		return class_exists( 'WPS_Query' ) ? WPS_Query::get_nopaging_url() : home_url( '/' );
	}
}
if ( ! function_exists( 'wpst_duration_custom_field' ) ) {
	function wpst_duration_custom_field( $updated, $field ) {
		if ( class_exists( 'WPS_Query' ) ) {
			WPS_Query::duration_custom_field( $updated, $field );
		}
	}
}
if ( ! function_exists( 'wpst_render_shortcodes' ) ) {
	function wpst_render_shortcodes( $content ) {
		return class_exists( 'WPS_Query' ) ? WPS_Query::render_shortcodes( $content ) : $content;
	}
}
if ( ! function_exists( 'wpst_posts_filter' ) ) {
	function wpst_posts_filter( $query ) {
		return class_exists( 'WPS_Query' ) ? WPS_Query::posts_filter( $query ) : $query;
	}
}
if ( ! function_exists( 'wpst_change_post_label' ) ) {
	function wpst_change_post_label() {
		if ( class_exists( 'WPS_Admin_UI' ) ) {
			WPS_Admin_UI::change_post_label();
		}
	}
}
if ( ! function_exists( 'wpst_change_post_object' ) ) {
	function wpst_change_post_object() {
		if ( class_exists( 'WPS_Admin_UI' ) ) {
			WPS_Admin_UI::change_post_object();
		}
	}
}
if ( ! function_exists( 'wpst_change_cat_object' ) ) {
	function wpst_change_cat_object() {
		if ( class_exists( 'WPS_Admin_UI' ) ) {
			WPS_Admin_UI::change_taxonomy_object( 'category', __( 'Video Category', 'wpst' ), __( 'Video Categories', 'wpst' ) );
		}
	}
}
if ( ! function_exists( 'wpst_change_tag_object' ) ) {
	function wpst_change_tag_object() {
		if ( class_exists( 'WPS_Admin_UI' ) ) {
			WPS_Admin_UI::change_taxonomy_object( 'post_tag', __( 'Video Tag', 'wpst' ), __( 'Video Tags', 'wpst' ) );
		}
	}
}
if ( ! function_exists( 'replace_admin_menu_icons_css' ) ) {
	function replace_admin_menu_icons_css() {
		if ( class_exists( 'WPS_Admin_UI' ) ) {
			WPS_Admin_UI::admin_menu_icon_css();
		}
	}
}
if ( ! function_exists( 'wpst_rss_post_thumbnail' ) ) {
	function wpst_rss_post_thumbnail( $content ) {
		return class_exists( 'WPS_Admin_UI' ) ? WPS_Admin_UI::rss_post_thumbnail( $content ) : $content;
	}
}
if ( ! function_exists( 'wpst_remove_admin_bar' ) ) {
	function wpst_remove_admin_bar() {
		if ( class_exists( 'WPS_Admin_UI' ) ) {
			WPS_Admin_UI::remove_admin_bar();
		}
	}
}
if ( ! function_exists( 'wpst_get_sources_from_hls' ) ) {
	function wpst_get_sources_from_hls( $hls_url ) {
		return class_exists( 'WPS_Media' ) ? WPS_Media::get_sources_from_hls( $hls_url ) : array();
	}
}
if ( ! function_exists( 'wpst_curl' ) ) {
	function wpst_curl( $url, $referer, $type = null ) {
		return class_exists( 'WPS_Media' ) ? WPS_Media::request( $url, $referer, $type ) : false;
	}
}
if ( ! function_exists( 'disable_comments_sitewide' ) ) {
	function disable_comments_sitewide() {
		if ( class_exists( 'WPS_Security' ) ) {
			WPS_Security::disable_comments();
		}
	}
}
if ( ! function_exists( 'disable_all_feeds' ) ) {
	function disable_all_feeds() {
		if ( class_exists( 'WPS_Security' ) ) {
			WPS_Security::disable_feed();
		}
	}
}

add_action( 'pre_get_posts', 'wpst_posts_filter' );
add_filter( 'mce_css', 'wpst_remove_mce_css' );
add_action( 'xbox_after_save_field_duration', 'wpst_duration_custom_field', 10, 2 );
add_action( 'admin_menu', 'wpst_change_post_label' );
add_action( 'init', 'wpst_change_post_object' );
add_action( 'init', 'wpst_change_cat_object' );
add_action( 'init', 'wpst_change_tag_object' );
add_action( 'admin_head', 'replace_admin_menu_icons_css' );
add_filter( 'the_excerpt_rss', 'wpst_rss_post_thumbnail' );
add_filter( 'the_content_feed', 'wpst_rss_post_thumbnail' );
add_action( 'get_header', 'wpst_remove_admin_bar' );

if ( class_exists( 'WPS_Admin_UI' ) ) {
	add_filter( 'comment_form_defaults', array( 'WPS_Admin_UI', 'comment_form_defaults' ) );
	add_filter( 'manage_video_posts_columns', array( 'WPS_Admin_UI', 'add_video_columns' ) );
	add_action( 'manage_video_posts_custom_column', array( 'WPS_Admin_UI', 'render_video_column' ), 10, 2 );
	add_action( 'admin_head-widgets.php', array( 'WPS_Admin_UI', 'widgets_preview_assets' ) );
}

add_action( 'init', 'disable_comments_sitewide' );
foreach ( array( 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments' ) as $wps_feed_hook ) {
	add_action( $wps_feed_hook, 'disable_all_feeds', 1 );
}
