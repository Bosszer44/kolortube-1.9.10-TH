<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_SEO_Tag {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		$wp_customize->add_section(
			'wpst_seo_video_tag',
			array(
				'priority'       => 60,
				'capability'     => 'edit_theme_options',
				'theme_supports' => '',
				'title'          => __( 'Tag pages', 'wpst' ),
				'description'    => __( 'Rewrite content on tag pages to improve SEO.', 'wpst' ) . wpst_add_variables(
					array(
						'%%tag%%' => __( 'display tag name.', 'wpst' ),
					)
				),
				'panel'          => 'wpst_seo',
			)
		);

		// Tag Hero Position
		$wp_customize->add_setting(
			'seo_video_tag_position',
			array(
				'default'   => 'bottom',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_video_tag_position',
				array(
					'label'             => esc_html__( 'Position', 'wpst' ),
					'description'       => esc_html__( 'Set the position of the title and description of this page. Can either be: top or bottom.', 'wpst' ),
					'section'           => 'wpst_seo_video_tag',
					'settings'          => 'seo_video_tag_position',
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

		// Tag SEO Slug
		// $wp_customize->add_setting(
		// 'seo_video_tag_slug',
		// array(
		// 'default'   => 'video-tag',
		// 'transport' => 'refresh',
		// )
		// );

		// $wp_customize->add_control(
		// new WP_Customize_Control(
		// $wp_customize,
		// 'wpst_seo_video_tag_slug',
		// array(
		// 'label'    => __( 'Tag Slug', 'wpst' ),
		// 'section'  => 'wpst_seo_video_tag',
		// 'settings' => 'seo_video_tag_slug',
		// 'type'     => 'text',
		// )
		// )
		// );

		// Tag SEO Title
		$wp_customize->add_setting(
			'seo_video_tag_title',
			array(
				'default'   => '%%tag%%',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_video_tag_title',
				array(
					'label'    => __( 'Tag Title', 'wpst' ),
					'section'  => 'wpst_seo_video_tag',
					'settings' => 'seo_video_tag_title',
					'type'     => 'text',
				)
			)
		);
		// Tag SEO Description
		$wp_customize->add_setting(
			'seo_video_tag_description',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new Text_Editor_Custom_Control(
				$wp_customize,
				'wpst_seo_video_tag_description',
				array(
					'label'    => __( 'Tag Description', 'wpst' ),
					'section'  => 'wpst_seo_video_tag',
					'settings' => 'seo_video_tag_description',
					'type'     => 'textarea',
				)
			)
		);

		/**
		 * SEARCH
		 */
	}
}
