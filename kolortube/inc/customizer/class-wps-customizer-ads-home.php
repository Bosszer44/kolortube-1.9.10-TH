<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Ads_Home {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		/**
		 * HOME ADS
		 */
		$wp_customize->add_section(
			'wpst_ads_home',
			array(
				'priority'       => 50,
				'capability'     => 'edit_theme_options',
				'theme_supports' => '',
				'title'          => __( 'Homepage', 'wpst' ),
				'description'    => __( 'Add your own ads on the homepage.', 'wpst' ),
				'panel'          => 'wpst_ads',
			)
		);

		// Home Ad before videos list
		$wp_customize->add_setting(
			'ads_home_before_list',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_home_before_list',
				array(
					'label'    => esc_html__( 'Ad zone before videos list', 'wpst' ),
					'section'  => 'wpst_ads_home',
					'settings' => 'ads_home_before_list',
					'type'     => 'textarea',
				)
			)
		);

		// Home Ad inside videos list
		$wp_customize->add_setting(
			'ads_home_inside_list',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_home_inside_list',
				array(
					'label'    => esc_html__( 'Ad zone inside videos list', 'wpst' ),
					'section'  => 'wpst_ads_home',
					'settings' => 'ads_home_inside_list',
					'type'     => 'textarea',
				)
			)
		);

		// Home Ad after videos list
		$wp_customize->add_setting(
			'ads_home_after_list',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_home_after_list',
				array(
					'label'    => esc_html__( 'Ad zone after videos list', 'wpst' ),
					'section'  => 'wpst_ads_home',
					'settings' => 'ads_home_after_list',
					'type'     => 'textarea',
				)
			)
		);
	}
}
