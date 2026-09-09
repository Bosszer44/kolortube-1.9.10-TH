<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Copyright {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		/***********************************/
		/****** NEW SECTION COPYRIGHT */
		/***********************************/
		$wp_customize->add_section(
			'wpst_copyright',
			array(
				'title'    => __( 'Copyright', 'wpst' ),
				'priority' => 70,
			)
		);

		// Copyright Text
		$wp_customize->add_setting(
			'copyright_content',
			array(
				'default'   => date( 'Y' ) . ' - ' . get_bloginfo( 'name' ) . '. ' . esc_html__( 'All rights reserved. Powered by WP-Script.com', 'wpst' ),
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new Text_Editor_Custom_Control(
				$wp_customize,
				'wpst_copyright_content',
				array(
					'label'    => __( 'Content', 'wpst' ),
					'section'  => 'wpst_copyright',
					'settings' => 'copyright_content',
					'type'     => 'textarea',
				)
			)
		);
	}
}
