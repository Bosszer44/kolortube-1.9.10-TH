<?php
/**
 * Central registry of every Theme Color setting.
 *
 * Single source of truth shared by:
 * - The Customizer registration layer (class-wps-customizer-colors.php).
 * - The frontend CSS variable output (customizer-output.php).
 * - The persisted palette record written to wp_options on every save.
 *
 * Keeping one registry guarantees the value shown in the Customizer, the value
 * stored in theme_mods (WordPress meta storage) and the value rendered on the
 * frontend are always exactly the same color.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wps_get_color_registry' ) ) {
	/**
	 * Full Theme Color registry.
	 *
	 * Every entry: default (registered default hex, '' = auto-derived),
	 * group, label and whether the control follows Main Color until the
	 * user customizes it explicitly.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function wps_get_color_registry() {
		return array(
			// ===== Base / Website =====
			'main_color'                      => array( 'default' => '#26adfe', 'group' => 'base',    'master' => false, 'label' => '【หลักของเว็บ】 Main Color — สีหลักคุมโทนทั้งเว็บ' ),
			'wps_site_background_color'       => array( 'default' => '#010012', 'group' => 'base',    'master' => false, 'label' => '【พื้นฐาน】 Site Background — สีพื้นหลังทั้งเว็บไซต์' ),
			'link_color'                      => array( 'default' => '#26adfe', 'group' => 'base',    'master' => true,  'label' => '【พื้นฐาน】 Link Color — สีลิงก์ทั่วเว็บไซต์' ),
			'body_background_color'           => array( 'default' => '#010012', 'group' => 'base',    'master' => false, 'label' => '【เดิม】 Body Background — ระบบเดิม (เชื่อมกับ Site Background)' ),
			'wps_soft_surface_color'          => array( 'default' => '#08071b', 'group' => 'base',    'master' => false, 'label' => '【พื้นฐาน】 Soft Surface — สีพื้นผิวรอง/กล่องเบา' ),
			'wps_soft_border_color'           => array( 'default' => '#26adfe', 'group' => 'base',    'master' => false, 'label' => '【พื้นฐาน】 Soft Border — สีขอบทั่วไปแบบอ่อน' ),

			// ===== Header / Menu / Dropdown =====
			'wps_menu_background_color'       => array( 'default' => '#020016', 'group' => 'menu',    'master' => false, 'label' => '【เมนู】 Menu / Header Background — สีพื้นหลังหัวเว็บและเมนู' ),
			'wps_menu_hover_background'       => array( 'default' => '#20104d', 'group' => 'menu',    'master' => true,  'label' => '【เมนู】 Menu Hover Background — สีพื้นหลังเมนูตอนชี้' ),
			'wps_menu_active_bg'              => array( 'default' => '#7b18ff', 'group' => 'menu',    'master' => true,  'label' => '【เมนู】 Menu Active Background — สีพื้นหลังเมนูที่เลือก' ),
			'wps_dropdown_background'         => array( 'default' => '#07051a', 'group' => 'menu',    'master' => false, 'label' => '【เมนู】 Dropdown Background — สีพื้นหลังดรอปดาวน์' ),
			'wps_dropdown_border'             => array( 'default' => '#26adfe', 'group' => 'menu',    'master' => true,  'label' => '【เมนู】 Dropdown Border — สีขอบดรอปดาวน์' ),
			'wps_dropdown_hover_background'   => array( 'default' => '#24104d', 'group' => 'menu',    'master' => false, 'label' => '【เมนู】 Dropdown Hover Background — สีพื้นหลังรายการตอนชี้' ),

			// ===== Buttons =====
			'wps_button_start_color'          => array( 'default' => '#26adfe', 'group' => 'button',  'master' => true,  'label' => '【ปุ่ม】 Gradient Start — สีเริ่มต้นไล่เฉดปุ่ม' ),
			'wps_button_middle_color'         => array( 'default' => '#7b18ff', 'group' => 'button',  'master' => true,  'label' => '【ปุ่ม】 Gradient Middle — สีตรงกลางไล่เฉดปุ่ม' ),
			'wps_button_end_color'            => array( 'default' => '#c00bff', 'group' => 'button',  'master' => true,  'label' => '【ปุ่ม】 Gradient End — สีปลายไล่เฉดปุ่ม' ),
			'wps_button_border_color'         => array( 'default' => '#26adfe', 'group' => 'button',  'master' => true,  'label' => '【ปุ่ม】 Button Border — สีขอบปุ่ม' ),
			'wps_button_hover_border_color'   => array( 'default' => '#c00bff', 'group' => 'button',  'master' => true,  'label' => '【ปุ่ม】 Button Hover Border — สีขอบปุ่มตอนชี้' ),
			'wps_button_shadow_color'         => array( 'default' => '#26adfe', 'group' => 'button',  'master' => true,  'label' => '【ปุ่ม】 Button Shadow — สีเงาปุ่มปกติ' ),
			'wps_button_hover_shadow_color'   => array( 'default' => '#c00bff', 'group' => 'button',  'master' => true,  'label' => '【ปุ่ม】 Button Hover Shadow — สีเงาปุ่มตอนชี้' ),
			// ===== Links / Labels =====
			'wps_label_border_color'          => array( 'default' => '#26adfe', 'group' => 'label',   'master' => true,  'label' => '【ป้าย】 Label Border — สีขอบป้าย' ),
			'wps_label_shadow_color'          => array( 'default' => '#7b18ff', 'group' => 'label',   'master' => true,  'label' => '【ป้าย】 Label Shadow — สีเงาป้าย' ),

			// ===== Video / Post Cards =====
			'wps_card_surface_color'          => array( 'default' => '#08071b', 'group' => 'card',    'master' => false, 'label' => '【การ์ดวิดีโอ】 Card Background — สีพื้นหลังการ์ดวิดีโอ' ),
			'wps_card_border_color'           => array( 'default' => '#24154f', 'group' => 'card',    'master' => false, 'label' => '【การ์ดวิดีโอ】 Card Border — สีขอบการ์ด' ),
			'wps_card_hover_border_color'     => array( 'default' => '#26adfe', 'group' => 'card',    'master' => true,  'label' => '【การ์ดวิดีโอ】 Card Hover Border — สีขอบการ์ดตอนชี้' ),
			'wps_card_shadow_color'           => array( 'default' => '#000000', 'group' => 'card',    'master' => false, 'label' => '【การ์ดวิดีโอ】 Card Shadow — สีเงาการ์ดปกติ' ),
			'wps_card_hover_shadow_color'     => array( 'default' => '#7b18ff', 'group' => 'card',    'master' => true,  'label' => '【การ์ดวิดีโอ】 Card Hover Shadow — สีเงาการ์ดตอนชี้' ),

			// ===== Taxonomy: Category / Studio / Actor / Tag =====
			'wps_taxonomy_surface'            => array( 'default' => '#100a25', 'group' => 'taxonomy','master' => false, 'label' => '【หมวดหมู่】 Taxonomy Background — สีพื้นหลัง Category/Studio/Actor/Tag' ),
			'wps_taxonomy_border_color'       => array( 'default' => '#26adfe', 'group' => 'taxonomy','master' => true,  'label' => '【หมวดหมู่】 Taxonomy Border — สีขอบ Capsule/หมวดหมู่' ),
			'wps_taxonomy_hover_border_color' => array( 'default' => '#c00bff', 'group' => 'taxonomy','master' => true,  'label' => '【หมวดหมู่】 Taxonomy Hover Border — สีขอบตอนชี้' ),
			'wps_taxonomy_hover_background'   => array( 'default' => '#24104d', 'group' => 'taxonomy','master' => false, 'label' => '【หมวดหมู่】 Taxonomy Hover Background — สีพื้นหลังตอนชี้' ),
			'wps_taxonomy_shadow_color'       => array( 'default' => '#000000', 'group' => 'taxonomy','master' => false, 'label' => '【หมวดหมู่】 Taxonomy Shadow — สีเงา Capsule/การ์ด' ),
			'wps_taxonomy_hover_shadow_color' => array( 'default' => '#7b18ff', 'group' => 'taxonomy','master' => true,  'label' => '【หมวดหมู่】 Taxonomy Hover Shadow — สีเงาตอนชี้' ),

			// ===== Home / Featured / Tag Cards =====
			'wps_featured_card_bg'            => array( 'default' => '#08071b', 'group' => 'home',    'master' => false, 'label' => '【หน้าแรก】 Featured Card Background — พื้นหลังการ์ดแนะนำ' ),
			'wps_featured_card_border_color'  => array( 'default' => '#24154f', 'group' => 'home',    'master' => false, 'label' => '【หน้าแรก】 Featured Card Border — ขอบการ์ดแนะนำ' ),
			'wps_featured_card_shadow_color'  => array( 'default' => '#000000', 'group' => 'home',    'master' => false, 'label' => '【หน้าแรก】 Featured Card Shadow — เงาการ์ดแนะนำ' ),
			'wps_featured_card_hover_color'   => array( 'default' => '#7b18ff', 'group' => 'home',    'master' => true,  'label' => '【หน้าแรก】 Featured Card Hover Shadow — เงาตอนชี้' ),
			'wps_tag_card_bg'                 => array( 'default' => '#08071b', 'group' => 'home',    'master' => false, 'label' => '【หน้าแรก】 Tag/Studio/Actor Card Background — พื้นหลังการ์ด Tag/Studio/Actor' ),
			'wps_tag_card_border_color'       => array( 'default' => '#24154f', 'group' => 'home',    'master' => false, 'label' => '【หน้าแรก】 Tag/Studio/Actor Card Border — ขอบการ์ด' ),
			'wps_tag_card_shadow_color'       => array( 'default' => '#000000', 'group' => 'home',    'master' => false, 'label' => '【หน้าแรก】 Tag/Studio/Actor Card Shadow — สีเงาการ์ด' ),
			'wps_tag_card_hover_color'        => array( 'default' => '#7b18ff', 'group' => 'home',    'master' => true,  'label' => '【หน้าแรก】 Tag/Studio/Actor Card Hover Shadow — เงาตอนชี้' ),
			// ===== View All / Form / Search =====
			'wps_view_all_background_color'   => array( 'default' => '#7b18ff', 'group' => 'form',    'master' => true,  'label' => '【ปุ่มเพิ่มเติม】 View All Background — สีพื้นหลังปุ่มดูทั้งหมด' ),
			'wps_view_all_shadow_color'       => array( 'default' => '#7b18ff', 'group' => 'form',    'master' => true,  'label' => '【ปุ่มเพิ่มเติม】 View All Shadow — สีเงาปุ่มดูทั้งหมด' ),
			'wps_input_background_color'      => array( 'default' => '#07051a', 'group' => 'form',    'master' => false, 'label' => '【ค้นหา/ฟอร์ม】 Input Background — สีพื้นหลังช่องค้นหา/กรอกข้อมูล' ),
			'wps_input_border_color'          => array( 'default' => '#24154f', 'group' => 'form',    'master' => false, 'label' => '【ค้นหา/ฟอร์ม】 Input Border — สีขอบช่อง' ),
			'wps_input_focus_border_color'    => array( 'default' => '#26adfe', 'group' => 'form',    'master' => true,  'label' => '【ค้นหา/ฟอร์ม】 Input Focus Border — สีขอบช่องเมื่อเลือก' ),

			// ===== Single Video / Poster =====
			'wps_single_poster_bg_color'      => array( 'default' => '#000000', 'group' => 'single',  'master' => false, 'label' => '【วิดีโอเดี่ยว】 Poster Background — สีพื้นหลังโปสเตอร์' ),
			'wps_single_poster_border_color'  => array( 'default' => '#24154f', 'group' => 'single',  'master' => false, 'label' => '【วิดีโอเดี่ยว】 Poster Border — สีขอบโปสเตอร์' ),
			'wps_single_poster_shadow_color'  => array( 'default' => '#000000', 'group' => 'single',  'master' => false, 'label' => '【วิดีโอเดี่ยว】 Poster Shadow — สีเงาโปสเตอร์' ),

			// ===== Footer =====
			'wps_footer_background_color'     => array( 'default' => '',        'group' => 'footer',  'master' => false, 'label' => '【ท้ายเว็บ】 Footer Background — สีพื้นหลังท้ายเว็บ (เว้นว่าง = สร้างอัตโนมัติให้ตัวหนังสืออ่านชัด)' ),

			// ===== System Status =====
			'wps_success_color'               => array( 'default' => '#22c55e', 'group' => 'status',  'master' => false, 'label' => '【สถานะ】 Success — สีสถานะสำเร็จ' ),
			'wps_warning_color'               => array( 'default' => '#f59e0b', 'group' => 'status',  'master' => false, 'label' => '【สถานะ】 Warning — สีสถานะแจ้งเตือน' ),
			'wps_danger_color'                => array( 'default' => '#ef4444', 'group' => 'status',  'master' => false, 'label' => '【สถานะ】 Danger — สีสถานะผิดพลาด/ลบ' ),
			'wps_info_color'                  => array( 'default' => '#26adfe', 'group' => 'status',  'master' => true,  'label' => '【สถานะ】 Info — สีสถานะข้อมูล' ),

			// ===== Pink Palette (KolorTube 1.9.10) =====
                        // Pink gradient for Category/Tag/Actor/Studio labels + capsule buttons;
                        // darker pink on hover, extra dark pink on the active Like button, white text.
                        'wps_pink_label_gradient_start'   => array( 'default' => '#ff6b9d', 'group' => 'pink',   'master' => false, 'label' => 'ชมพู – ไล่เฉดป้าย/ปุ่มแคปซูล เริ่มต้น (#ff6b9d)' ),
                        'wps_pink_label_gradient_end'     => array( 'default' => '#ff4f81', 'group' => 'pink',   'master' => false, 'label' => 'ชมพู – ไล่เฉดป้าย/ปุ่มแคปซูล สิ้นสุด (#ff4f81)' ),
                        'wps_pink_label_hover_start'      => array( 'default' => '#ff4f81', 'group' => 'pink',   'master' => false, 'label' => 'ชมพู – ไล่เฉดตอนชี้เมาส์ เริ่มต้น (#ff4f81)' ),
                        'wps_pink_label_hover_end'        => array( 'default' => '#e91e63', 'group' => 'pink',   'master' => false, 'label' => 'ชมพู – ไล่เฉดตอนชี้เมาส์ สิ้นสุด (#e91e63)' ),
                        'wps_pink_like_active_start'      => array( 'default' => '#e91e63', 'group' => 'pink',   'master' => false, 'label' => 'ชมพู – ปุ่มถูกใจ (กดแล้ว) เริ่มต้น (#e91e63)' ),
                        'wps_pink_like_active_end'        => array( 'default' => '#c2185b', 'group' => 'pink',   'master' => false, 'label' => 'ชมพู – ปุ่มถูกใจ (กดแล้ว) สิ้นสุด (#c2185b)' ),
		);
	}
}

if ( ! function_exists( 'wps_get_color_setting' ) ) {
	/**
	 * Read one Theme Color exactly as stored in WordPress meta (theme_mod)
	 * and validate it as a hex color. Invalid/empty stored values fall back
	 * to the registered default so the frontend can never render a broken
	 * color. Returns '' only when the setting is auto-derived (footer).
	 *
	 * @param string      $setting_id Setting ID from the registry.
	 * @param string|null $fallback   Optional fallback default.
	 * @return string Valid hex color or '' for auto-derived settings.
	 */
	function wps_get_color_setting( $setting_id, $fallback = null ) {
		$registry = wps_get_color_registry();
		$meta     = isset( $registry[ $setting_id ] ) ? $registry[ $setting_id ] : array();

		$default = null !== $fallback ? $fallback : ( isset( $meta['default'] ) ? $meta['default'] : '' );
		$stored  = get_theme_mod( $setting_id, $default );

		$value = function_exists( 'wps_sanitize_color' ) ? wps_sanitize_color( $stored ) : sanitize_hex_color( $stored );
		if ( '' === $value && '' !== $default ) {
			$value = sanitize_hex_color( $default );
		}

		return $value ? $value : '';
	}
}

if ( ! function_exists( 'wps_register_color_setting' ) ) {
	/**
	 * Register (or repair) one color setting as a real theme_mod setting so
	 * every Customizer save is persisted into WordPress meta storage.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @param string               $setting_id   Setting ID.
	 * @param string               $default      Registered default color.
	 * @return WP_Customize_Setting|null
	 */
	function wps_register_color_setting( $wp_customize, $setting_id, $default ) {
		$setting = $wp_customize->get_setting( $setting_id );
		if ( ! $setting ) {
			$wp_customize->add_setting(
				$setting_id,
				array(
					'type'              => 'theme_mod',
					'default'           => $default,
					'transport'         => 'refresh',
					'sanitize_callback' => 'wps_sanitize_color',
				)
			);
			$setting = $wp_customize->get_setting( $setting_id );
		} else {
			$setting->type              = 'theme_mod';
			$setting->sanitize_callback = 'wps_sanitize_color';
		}
		return $setting;
	}
}
