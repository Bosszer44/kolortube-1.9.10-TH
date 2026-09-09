<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Developer {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		/*********************************/
		/****** NEW SECTION SCRIPTS */
		/*********************************/
		$wp_customize->add_section(
			'wpst_scripts_section',
			array(
				'title'    => __( 'Scripts', 'wpst' ),
				'priority' => 80,
			)
		);

		// Google Analytics
		$wp_customize->add_setting(
			'google_analytics_code',
			array(
				'default'   => '',
				'transport' => 'refresh',
				'sanitize_callback' => array( 'WPS_Head_Output_Guard', 'filter_analytics' ),
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_google_analytics_code',
				array(
					'label'       => esc_html__( 'Google Analytics', 'wpst' ),
					'section'     => 'wpst_scripts_section',
					'settings'    => 'google_analytics_code',
					'type'        => 'textarea',
					'description' => __( 'Paste here your Google Analytics tracking code.', 'wpst' ),
				)
			)
		);

		// Meta verification
		$wp_customize->add_setting(
			'meta_verification_code',
			array(
				'default'   => '',
				'transport' => 'refresh',
				'sanitize_callback' => array( 'WPS_Head_Output_Guard', 'filter_verification' ),
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_meta_verification_code',
				array(
					'label'       => esc_html__( 'Meta Verification', 'wpst' ),
					'section'     => 'wpst_scripts_section',
					'settings'    => 'meta_verification_code',
					'type'        => 'textarea',
					'description' => __( 'Paste here meta codes for domain verification.', 'wpst' ),
				)
			)
		);

		// Other scripts
		$wp_customize->add_setting(
			'other_script_codes',
			array(
				'default'   => '',
				'transport' => 'refresh',
				'sanitize_callback' => array( 'WPS_Head_Output_Guard', 'filter_footer_scripts' ),
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_other_script_codes',
				array(
					'label'       => esc_html__( 'Other scripts', 'wpst' ),
					'section'     => 'wpst_scripts_section',
					'settings'    => 'other_script_codes',
					'type'        => 'textarea',
					'description' => __( 'Paste here your other scripts (eg. popunder script)', 'wpst' ),
				)
			)
		);
	}
}
