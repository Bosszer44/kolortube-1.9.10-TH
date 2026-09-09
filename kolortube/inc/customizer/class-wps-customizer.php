<?php
/**
 * Customizer module orchestrator.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer {
	/** @var array<int,string> */
	private static $modules = array(
		'WPS_Customizer_Video',
		'WPS_Customizer_Colors',
		'WPS_Customizer_Layout',
		'WPS_Customizer_Ads_Panel',
		'WPS_Customizer_Ads_Home',
		'WPS_Customizer_Ads_Single',
		'WPS_Customizer_Ads_Actor',
		'WPS_Customizer_Ads_Category',
		'WPS_Customizer_Ads_Tag',
		'WPS_Customizer_Ads_Search',
		'WPS_Customizer_SEO_Panel',
		'WPS_Customizer_SEO_Home',
		'WPS_Customizer_SEO_Video',
		'WPS_Customizer_SEO_Category',
		'WPS_Customizer_SEO_Tag',
		'WPS_Customizer_SEO_Search',
		'WPS_Customizer_Copyright',
		'WPS_Customizer_Developer',
		'WPS_Customizer_Framework',
	);

	/**
	 * Register all modules.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		foreach ( self::$modules as $module ) {
			if ( is_callable( array( $module, 'register' ) ) ) {
				call_user_func( array( $module, 'register' ), $wp_customize );
			}
		}

		self::apply_legacy_sanitizers( $wp_customize );
	}

	/**
	 * Add sanitizers to every legacy setting without changing its key or value.
	 */
	private static function apply_legacy_sanitizers( $wp_customize ) {
		$selects = array(
			'enable_video_preview', 'enable_thumbs_rotation', 'enable_video_tracking_link',
			'video_listing_general_show_duration', 'video_listing_general_show_title',
			'sidebar_position', 'wpst_sidebar_position', 'mobile_columns', 'wpst_container_type',
			'wpst_posts_index_style', 'wps_color_preset', 'seo_home_position', 'seo_video_cat_position',
			'seo_video_tag_position', 'seo_search_position',
		);
		$colors = array( 'main_color', 'link_color', 'body_background_color' );
		$html   = array(
			'ads_home_before_list', 'ads_home_inside_list', 'ads_home_after_list',
			'ads_single_video_page_in_player_1', 'ads_single_video_page_in_player_2',
			'ads_single_video_page_under_player', 'ads_single_video_page_beside_player_1',
			'ads_single_video_page_beside_player_2', 'ads_single_video_page_before_related_videos',
			'ads_home_inside_related_videos_list', 'ads_actor_page_before_list',
			'ads_actor_page_inside_list', 'ads_actor_page_after_list', 'ads_category_page_before_list',
			'ads_category_page_inside_list', 'ads_category_page_after_list', 'ads_tag_page_before_list',
			'ads_tag_page_inside_list', 'ads_tag_page_after_list', 'ads_search_result_page_before_list',
			'ads_search_result_page_inside_list', 'ads_search_result_page_after_list',
			'copyright_content', 'google_analytics_code', 'meta_verification_code', 'other_script_codes',
			'seo_home_description', 'seo_video_cat_description', 'seo_video_tag_description', 'seo_search_description',
		);
		$integers = array( 'custom_logo' );
		$legacy   = class_exists( 'WPS_Compatibility' ) ? WPS_Compatibility::legacy_theme_mod_keys() : array();

		foreach ( $legacy as $id ) {
			$setting = $wp_customize->get_setting( $id );
			if ( ! $setting || 'nav_menu_locations' === $id ) {
				continue;
			}

			$setting->type = 'theme_mod';
			if ( in_array( $id, $selects, true ) ) {
				$setting->sanitize_callback = 'wps_sanitize_select';
			} elseif ( in_array( $id, $colors, true ) ) {
				$setting->sanitize_callback = 'wps_sanitize_color';
			} elseif ( in_array( $id, $html, true ) ) {
				$setting->sanitize_callback = 'wps_sanitize_html';
			} elseif ( in_array( $id, $integers, true ) ) {
				$setting->sanitize_callback = 'wps_sanitize_integer';
			} elseif ( 0 === strpos( $id, 'seo_' ) ) {
				$setting->sanitize_callback = 'wps_sanitize_text';
			} elseif ( ! is_callable( $setting->sanitize_callback ) ) {
				$setting->sanitize_callback = 'wps_sanitize_text';
			}
		}
	}

}
