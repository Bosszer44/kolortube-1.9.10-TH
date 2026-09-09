<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Ads_Panel {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		/*************************************/
		/****** NEW SECTION ADVERTISING */
		/*************************************/
		$wp_customize->add_panel(
			'wpst_ads',
			array(
				'capability'     => 'edit_theme_options',
				'priority'       => 50,
				'theme_supports' => '',
				'title'          => __( 'Advertising', 'wpst' ),
				'description'    => __( 'Settings to add ads into your site', 'wpst' ),
			)
		);
	}
}
