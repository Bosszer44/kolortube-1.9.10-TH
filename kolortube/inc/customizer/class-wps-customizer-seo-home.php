<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_SEO_Home {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		$wp_customize->add_section(
			'wpst_seo_home',
			array(
				'priority'       => 60,
				'capability'     => 'edit_theme_options',
				'theme_supports' => '',
				'title'          => __( 'Homepage', 'wpst' ),
				'description'    => __( 'Rewrite content on homepage to improve SEO.', 'wpst' ),
				'panel'          => 'wpst_seo',
			)
		);

		// Home Hero Position
		$wp_customize->add_setting(
			'seo_home_position',
			array(
				'default'   => 'bottom',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_home_position',
				array(
					'label'             => esc_html__( 'Position', 'wpst' ),
					'description'       => esc_html__( 'Set the position of the title and description of this page. Can either be: top or bottom.', 'wpst' ),
					'section'           => 'wpst_seo_home',
					'settings'          => 'seo_home_position',
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

		// HOME SEO Title
		$wp_customize->add_setting(
			'seo_home_title',
			array(
				'default'   => get_bloginfo( 'description' ),
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_home_title',
				array(
					'label'    => __( 'Home Title', 'wpst' ),
					'section'  => 'wpst_seo_home',
					'settings' => 'seo_home_title',
					'type'     => 'text',
				)
			)
		);
		// HOME SEO Description
		$wp_customize->add_setting(
			'seo_home_description',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			// new WP_Customize_Control(
			new Text_Editor_Custom_Control(
				$wp_customize,
				'wpst_seo_home_description',
				array(
					'label'    => __( 'Home Description', 'wpst' ),
					'section'  => 'wpst_seo_home',
					'settings' => 'seo_home_description',
					'type'     => 'textarea',
				)
			)
		);

		/**
		 * SINGLE VIDEO PAGE
		 */
	}
}
