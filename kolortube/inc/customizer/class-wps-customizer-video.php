<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Video {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		/*********************************/
		/****** NEW SECTION GENERAL */
		/*********************************/
		$wp_customize->add_section(
			'wpst_general',
			array(
				'title'    => __( 'General', 'wpst' ),
				'priority' => 10,
			)
		);

		$wp_customize->add_setting(
			'enable_video_preview',
			array(
				'default'   => 'yes',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_enable_video_preview',
				array(
					'label'             => __( 'Enable video preview', 'wpst' ),
					'description'       => __( 'Enable video preview on thumb mouse hover.', 'wpst' ),
					'section'           => 'wpst_general',
					'settings'          => 'enable_video_preview',
					'type'              => 'select',
					'sanitize_callback' => 'wpst_theme_slug_sanitize_select',
					'choices'           => array(
						'yes' => __( 'Yes', 'wpst' ),
						'no'  => __( 'No', 'wpst' ),
					),
					// 'priority'          => '10',
				)
			)
		);

		$wp_customize->add_setting(
			'enable_thumbs_rotation',
			array(
				'default'   => 'yes',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_enable_thumbs_rotation',
				array(
					'label'             => __( 'Enable thumbs rotation', 'wpst' ),
					'description'       => __( 'Enable thumbs rotation on thumb mouse hover.', 'wpst' ),
					'section'           => 'wpst_general',
					'settings'          => 'enable_thumbs_rotation',
					'type'              => 'select',
					'sanitize_callback' => 'wpst_theme_slug_sanitize_select',
					'choices'           => array(
						'yes' => __( 'Yes', 'wpst' ),
						'no'  => __( 'No', 'wpst' ),
					),
					// 'priority'          => '10',
				)
			)
		);

		$wp_customize->add_setting(
			'enable_video_tracking_link',
			array(
				'default'   => 'no',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_enable_video_tracking_link',
				array(
					'label'             => __( 'Enable video tracking link', 'wpst' ),
					'description'       => __( 'Display a banner with the video tracking link.', 'wpst' ),
					'section'           => 'wpst_general',
					'settings'          => 'enable_video_tracking_link',
					'type'              => 'select',
					'sanitize_callback' => 'wpst_theme_slug_sanitize_select',
					'choices'           => array(
						'yes' => __( 'Yes', 'wpst' ),
						'no'  => __( 'No', 'wpst' ),
					),
					// 'priority'          => '10',
				)
			)
		);
	}
}
