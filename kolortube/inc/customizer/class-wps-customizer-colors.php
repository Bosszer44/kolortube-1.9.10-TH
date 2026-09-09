<?php
/**
 * Modular Customizer registration for the single site-wide color system.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Colors {
	/**
	 * Register all Theme Colors controls.
	 *
	 * Existing setting keys are preserved for compatibility. New controls are
	 * semantic: each one describes exactly which part of the frontend it owns.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		/*
		 * Every color comes from the central registry (color-registry.php).
		 * Registering each one as a real theme_mod setting guarantees that
		 * the Customizer value is actually saved into WordPress meta storage
		 * (option theme_mods_{stylesheet}) and read back with the exact same
		 * default on the frontend.
		 */
		$registry = wps_get_color_registry();

		foreach ( $registry as $setting_id => $meta ) {
			wps_register_color_setting( $wp_customize, $setting_id, $meta['default'] );

			$description = ! empty( $meta['master'] )
				? __( 'ค่าเริ่มต้นจะเชื่อมกับ Main Color เพื่อคุมโทนเดียวกัน และสามารถปรับแยกได้เมื่อต้องการ', 'wpst' )
				: __( 'สีนี้เป็นส่วนหนึ่งของระบบสีหลัก KolorTube และสามารถปรับแยกจาก Main Color ได้', 'wpst' );

			$control = $wp_customize->get_control( $setting_id );
			if ( ! $control ) {
				$control = $wp_customize->get_control( $setting_id . '_control' );
			}
			if ( ! $control && class_exists( 'WP_Customize_Color_Control' ) ) {
				$wp_customize->add_control(
					new WP_Customize_Color_Control(
						$wp_customize,
						$setting_id . '_control',
						array(
							'label'       => $meta['label'],
							'description' => $description,
							'section'     => 'wps_theme_colors',
							'settings'    => $setting_id,
						)
					)
				);
			} elseif ( $control ) {
				$control->label   = $meta['label'];
				$control->section = 'wps_theme_colors';
			}
		}

		/* Text colors are automatic; remove any legacy controls from older layers. */
		$auto_text_setting_ids = array(
			'wps_ui_text_color', 'wps_ui_muted_color', 'wps_menu_text_color', 'wps_menu_hover_text_color', 'wps_menu_active_text',
			'wps_dropdown_text', 'wps_dropdown_hover_text', 'wps_button_text_color', 'wps_label_text_color',
			'wps_card_text_color', 'wps_card_muted_color', 'wps_taxonomy_text', 'wps_taxonomy_muted', 'wps_taxonomy_hover_text',
			'wps_featured_card_text', 'wps_tag_card_text', 'wps_view_all_text_color', 'wps_input_text_color', 'wps_input_placeholder_color',
		);
		foreach ( $auto_text_setting_ids as $auto_text_setting_id ) {
			foreach ( array( $auto_text_setting_id, $auto_text_setting_id . '_control' ) as $control_id ) {
				if ( $wp_customize->get_control( $control_id ) ) { $wp_customize->remove_control( $control_id ); }
			}
		}

		// Re-label legacy controls so the original theme system remains intact
		// without presenting duplicate/confusing color names.
		$legacy_labels = array(
			'wps_color_preset' => '【เดิม】 Color Preset — ระบบสีเดิมของธีม',
		);
		foreach ( $legacy_labels as $setting_id => $label ) {
			$control = $wp_customize->get_control( $setting_id );
			if ( ! $control ) {
				$control = $wp_customize->get_control( $setting_id . '_control' );
			}
			if ( $control ) {
				$control->label   = $label;
				$control->section = 'wps_theme_colors';
			}
		}
	}
}
