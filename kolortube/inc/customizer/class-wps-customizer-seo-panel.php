<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_SEO_Panel {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		/*****************************/
		/****** NEW SECTION SEO */
		/*****************************/
		$wp_customize->add_panel(
			'wpst_seo',
			array(
				'priority'       => 60,
				'capability'     => 'edit_theme_options',
				'theme_supports' => '',
				'title'          => __( 'SEO', 'wpst' ),
				'description'    => __( 'Several settings pertaining my theme', 'wpst' ),
			)
		);

		/**
		 * HOME
		 */
	}
}
