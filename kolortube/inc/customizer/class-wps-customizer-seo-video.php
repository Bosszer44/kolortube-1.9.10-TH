<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_SEO_Video {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		$wp_customize->add_section(
			'wpst_seo_video',
			array(
				'priority'       => 60,
				'capability'     => 'edit_theme_options',
				'theme_supports' => '',
				'title'          => __( 'Single video pages', 'wpst' ),
				'description'    => __( 'Rewrite content on video pages to improve SEO.', 'wpst' ) . wpst_add_variables(
					array(
						'%%video_title%%' => __( 'display video title.', 'wpst' ),
					)
				),
				'panel'          => 'wpst_seo',
			)
		);

		// Video SEO Title
		$wp_customize->add_setting(
			'seo_video_title',
			array(
				'default'   => '%%video_title%%',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_video_title',
				array(
					'label'    => __( 'Video Title', 'wpst' ),
					'section'  => 'wpst_seo_video',
					'settings' => 'seo_video_title',
					'type'     => 'text',
				)
			)
		);
		// Video SEO tracking button
		$wp_customize->add_setting(
			'seo_video_tracking_button',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_video_tracking_button',
				array(
					'label'    => __( 'Video Tracking Button', 'wpst' ),
					'section'  => 'wpst_seo_video',
					'settings' => 'seo_video_tracking_button',
					'type'     => 'text',
				)
			)
		);

		// Video SEO related videos title.
		$wp_customize->add_setting(
			'seo_related_videos_title',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_related_videos_title',
				array(
					'label'    => __( 'Related Videos Title', 'wpst' ),
					'section'  => 'wpst_seo_video',
					'settings' => 'seo_related_videos_title',
					'type'     => 'text',
				)
			)
		);

		// Video SEO related videos button label.
		$wp_customize->add_setting(
			'seo_more_related_videos_button',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_seo_more_related_videos_button',
				array(
					'label'    => __( 'More related videos button label', 'wpst' ),
					'section'  => 'wpst_seo_video',
					'settings' => 'seo_more_related_videos_button',
					'type'     => 'text',
				)
			)
		);
		/**
		 * CAT
		 */
	}
}
