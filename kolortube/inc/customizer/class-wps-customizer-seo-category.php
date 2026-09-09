<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_SEO_Category {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		$wp_customize->add_section(
			'wpst_seo_video_cat',
			array(
				'priority'       => 60,
				'capability'     => 'edit_theme_options',
				'theme_supports' => '',
				'title'          => __( 'Category pages', 'wpst' ),
				'description'    => __( 'Rewrite content on category pages to improve SEO.', 'wpst' ) . wpst_add_variables(
					array(
						'%%cat%%' => __( 'display category name.', 'wpst' ),
					)
				),
				'panel'          => 'wpst_seo',
			)
		);

		// Cat Hero Position
		$wp_customize->add_setting(
			'seo_video_cat_position',
			array(
				'default'   => 'top',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_video_cat_position',
				array(
					'label'             => esc_html__( 'Position', 'wpst' ),
					'description'       => esc_html__( 'Set the position of the title and description of this page. Can either be: top or bottom.', 'wpst' ),
					'section'           => 'wpst_seo_video_cat',
					'settings'          => 'seo_video_cat_position',
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

		// Cat SEO Slug
		// $wp_customize->add_setting(
		// 'seo_video_cat_slug',
		// array(
		// 'default'   => 'category',
		// 'transport' => 'refresh',
		// )
		// );

		// $wp_customize->add_control(
		// new WP_Customize_Control(
		// $wp_customize,
		// 'wpst_seo_video_cat_slug',
		// array(
		// 'label'    => __( 'Category Slug', 'wpst' ),
		// 'section'  => 'wpst_seo_video_cat',
		// 'settings' => 'seo_video_cat_slug',
		// 'type'     => 'text',
		// )
		// )
		// );

		// Cat SEO Title
		$wp_customize->add_setting(
			'seo_video_cat_title',
			array(
				'default'   => '%%cat%%',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_video_cat_title',
				array(
					'label'    => __( 'Category Title', 'wpst' ),
					'section'  => 'wpst_seo_video_cat',
					'settings' => 'seo_video_cat_title',
					'type'     => 'text',
				)
			)
		);
		// Cat SEO Description
		$wp_customize->add_setting(
			'seo_video_cat_description',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new Text_Editor_Custom_Control(
				$wp_customize,
				'wpst_seo_video_cat_description',
				array(
					'label'    => __( 'Category Description', 'wpst' ),
					'section'  => 'wpst_seo_video_cat',
					'settings' => 'seo_video_cat_description',
					'type'     => 'textarea',
				)
			)
		);

		/**
		 * TAG
		 */
	}
}
