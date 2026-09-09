<?php
/**
 * Modular Customizer registration.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Ads_Single {
	/**
	 * Register controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		/**
		 * SINGLE VIDEO PAGE ADS
		 */
		$wp_customize->add_section(
			'wpst_ads_single_video_page',
			array(
				'priority'       => 50,
				'capability'     => 'edit_theme_options',
				'theme_supports' => '',
				'title'          => __( 'Single Video Page', 'wpst' ),
				'description'    => __( 'Add your own ads on single video pages.', 'wpst' ),
				'panel'          => 'wpst_ads',
			)
		);

		// Single video page Ad in player 1
		$wp_customize->add_setting(
			'ads_single_video_page_in_player_1',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_single_video_page_in_player_1',
				array(
					'label'    => esc_html__( 'In player ad zone 1', 'wpst' ),
					'section'  => 'wpst_ads_single_video_page',
					'settings' => 'ads_single_video_page_in_player_1',
					'type'     => 'textarea',
				)
			)
		);

		// Single video page Ad in player 2
		$wp_customize->add_setting(
			'ads_single_video_page_in_player_2',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_single_video_page_in_player_2',
				array(
					'label'    => esc_html__( 'In player ad zone 2', 'wpst' ),
					'section'  => 'wpst_ads_single_video_page',
					'settings' => 'ads_single_video_page_in_player_2',
					'type'     => 'textarea',
				)
			)
		);

		// Single video page Ad under player
		$wp_customize->add_setting(
			'ads_single_video_page_under_player',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_single_video_page_under_player',
				array(
					'label'    => esc_html__( 'Under player ad zone', 'wpst' ),
					'section'  => 'wpst_ads_single_video_page',
					'settings' => 'ads_single_video_page_under_player',
					'type'     => 'textarea',
				)
			)
		);

		// Single video page Ad beside player 1
		$wp_customize->add_setting(
			'ads_single_video_page_beside_player_1',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_single_video_page_beside_player_1',
				array(
					'label'    => esc_html__( 'Beside player ad zone 1', 'wpst' ),
					'section'  => 'wpst_ads_single_video_page',
					'settings' => 'ads_single_video_page_beside_player_1',
					'type'     => 'textarea',
				)
			)
		);

		// Single video page Ad beside player 2
		$wp_customize->add_setting(
			'ads_single_video_page_beside_player_2',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_single_video_page_beside_player_2',
				array(
					'label'    => esc_html__( 'Beside player ad zone 2', 'wpst' ),
					'section'  => 'wpst_ads_single_video_page',
					'settings' => 'ads_single_video_page_beside_player_2',
					'type'     => 'textarea',
				)
			)
		);

		// Single video page Ad before related videos
		$wp_customize->add_setting(
			'ads_single_video_page_before_related_videos',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_single_video_page_before_related_videos',
				array(
					'label'    => esc_html__( 'Before related videos ad zone', 'wpst' ),
					'section'  => 'wpst_ads_single_video_page',
					'settings' => 'ads_single_video_page_before_related_videos',
					'type'     => 'textarea',
				)
			)
		);

		// Home Ad inside videos list
		$wp_customize->add_setting(
			'ads_home_inside_related_videos_list',
			array(
				'default'   => '',
				'transport' => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Control(
				$wp_customize,
				'wpst_ads_home_inside_related_videos_list',
				array(
					'label'    => esc_html__( 'Ad zone inside related videos list', 'wpst' ),
					'section'  => 'wpst_ads_single_video_page',
					'settings' => 'ads_home_inside_related_videos_list',
					'type'     => 'textarea',
				)
			)
		);

		/**
		 * ACTORS PAGE ADS
		 */
	}
}
