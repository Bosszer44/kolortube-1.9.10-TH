<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_SEO_Search {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		$wp_customize->add_section(
			'wpst_seo_search',
			array(
				'priority'       => 60,
				'capability'     => 'edit_theme_options',
				'theme_supports' => '',
				'title'          => __( 'Search result pages', 'wpst' ),
				'description'    => __( 'Rewrite content on search result pages.', 'wpst' ) . wpst_add_variables(
					array(
						'%%search_tag%%'    => __( 'display search tag name.', 'wpst' ),
						'%%search_number%%' => __( 'display search result number.', 'wpst' ),
					)
				),
				'panel'          => 'wpst_seo',
			)
		);

		// Tag Hero Position
		$wp_customize->add_setting(
			'seo_search_position',
			array(
				'default'   => 'top',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_search_position',
				array(
					'label'             => esc_html__( 'Position', 'wpst' ),
					'description'       => esc_html__( 'Set the position of the title and description of this page. Can either be: top or bottom.', 'wpst' ),
					'section'           => 'wpst_seo_search',
					'settings'          => 'seo_search_position',
					'type'              => 'select',
					'sanitize_callback' => 'wpst_theme_slug_sanitize_select',
					'choices'           => array(
						'top'    => esc_html__( 'Top', 'wpst' ),
						'bottom' => esc_html__( 'Bottom', 'wpst' ),
					),
					// 'priority'          => '20',
				)
			)
		);

		// Search SEO Title
		$wp_customize->add_setting(
			'seo_search_title',
			array(
				'default'   => 'ผลการค้นหา: %%search_tag%%',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_search_title',
				array(
					'label'    => __( 'Search Title', 'wpst' ),
					'section'  => 'wpst_seo_search',
					'settings' => 'seo_search_title',
					'type'     => 'text',
				)
			)
		);
		// Search SEO Description
		$wp_customize->add_setting(
			'seo_search_description',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new Text_Editor_Custom_Control(
				$wp_customize,
				'wpst_seo_search_description',
				array(
					'label'    => __( 'Search Description', 'wpst' ),
					'section'  => 'wpst_seo_search',
					'settings' => 'seo_search_description',
					'type'     => 'textarea',
				)
			)
		);
	}
}
