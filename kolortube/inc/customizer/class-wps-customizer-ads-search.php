<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Ads_Search {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		$wp_customize->add_section(
			'wpst_ads_search_result_page',
			array(
				'priority'       => 50,
				'capability'     => 'edit_theme_options',
				'theme_supports' => '',
				'title'          => __( 'Search Result Page', 'wpst' ),
				'description'    => __( 'Add your own ads on the search result pages.', 'wpst' ),
				'panel'          => 'wpst_ads',
			)
		);

		// Search result page Ad before videos list
		$wp_customize->add_setting(
			'ads_search_result_page_before_list',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_search_result_page_before_list',
				array(
					'label'    => esc_html__( 'Ad zone before videos list', 'wpst' ),
					'section'  => 'wpst_ads_search_result_page',
					'settings' => 'ads_search_result_page_before_list',
					'type'     => 'textarea',
				)
			)
		);

		// Search result page Ad inside videos list
		$wp_customize->add_setting(
			'ads_search_result_page_inside_list',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_search_result_page_inside_list',
				array(
					'label'    => esc_html__( 'Ad zone inside videos list', 'wpst' ),
					'section'  => 'wpst_ads_search_result_page',
					'settings' => 'ads_search_result_page_inside_list',
					'type'     => 'textarea',
				)
			)
		);

		// Search result page Ad after videos list
		$wp_customize->add_setting(
			'ads_search_result_page_after_list',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_search_result_page_after_list',
				array(
					'label'    => esc_html__( 'Ad zone after videos list', 'wpst' ),
					'section'  => 'wpst_ads_search_result_page',
					'settings' => 'ads_search_result_page_after_list',
					'type'     => 'textarea',
				)
			)
		);
	}
}
