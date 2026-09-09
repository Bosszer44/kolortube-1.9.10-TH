<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Layout {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		/*********************************/
		/****** NEW SECTION SIDEBAR */
		/*********************************/
		$wp_customize->add_section(
			'wpst_theme_layout_options',
			array(
				'title'      => __( 'Sidebar', 'wpst' ),
				'capability' => 'edit_theme_options',
				// 'description' => __( 'Container width and sidebar defaults', 'wpst' ),
				'priority'   => 30,
			)
		);

		$wp_customize->add_setting(
			'sidebar_position',
			array(
				'default'   => 'left',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_sidebar_position',
				array(
					'label'             => __( 'Sidebar Positioning', 'wpst' ),
					'description'       => __(
						'Set sidebar\'s default position. Can either be: right, left or none.',
						'wpst'
					),
					'section'           => 'wpst_theme_layout_options',
					'settings'          => 'sidebar_position',
					'type'              => 'select',
					'sanitize_callback' => 'wpst_theme_slug_sanitize_select',
					'choices'           => array(
						'left'  => __( 'Left sidebar', 'wpst' ),
						'right' => __( 'Right sidebar', 'wpst' ),
						'none'  => __( 'No sidebar', 'wpst' ),
					),
					'priority'          => '30',
				)
			)
		);

			/*********************************/
			/****** NEW SECTION MOBILE */
			/*********************************/
			$wp_customize->add_section(
				'wpst_mobile',
				array(
					'title'      => __( 'Mobile', 'wpst' ),
					'capability' => 'edit_theme_options',
					'priority'   => 35,
				)
			);

			$wp_customize->add_setting(
				'mobile_columns',
				array(
					'default'           => '2',
					'transport'         => 'refresh',
					'sanitize_callback' => 'wpst_sanitize_mobile_columns',
				)
			);

			$wp_customize->add_control(
				new WP_Customize_Control(
					$wp_customize,
					'wpst_mobile_columns',
					array(
						'label'       => __( 'Mobile Columns', 'wpst' ),
						'description' => __( 'Choose number of columns for mobile display. Default is 2 columns.', 'wpst' ),
						'section'     => 'wpst_mobile',
						'settings'    => 'mobile_columns',
						'type'        => 'select',
						'choices'     => array(
							'1' => __( '1 column', 'wpst' ),
							'2' => __( '2 columns', 'wpst' ),
						),
					)
				)
			);
	}
}
