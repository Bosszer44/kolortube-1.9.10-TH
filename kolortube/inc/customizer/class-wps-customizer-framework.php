<?php
/**
 * KolorTube Customizer sections.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Customizer_Framework {
	/**
	 * Return the complete framework setting schema.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_setting_schema() {
		return array(
			'wps_dashboard_enabled'          => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_redirect_attachment_pages'  => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_disable_emojis'             => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_disable_xmlrpc'             => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_litespeed_primary'          => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_litespeed_purge_on_save'    => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_lazy_load'                  => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_webp_support'               => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_heartbeat_mode'             => array( 'default' => 'default', 'sanitize_callback' => 'wps_sanitize_select' ),
			'wps_basic_hardening'            => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_protect_admin'              => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_disable_rest_users'         => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_disable_comments'           => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_disable_feeds'              => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_cdn_enabled'                => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_cdn_url'                    => array( 'default' => '', 'sanitize_callback' => 'wps_sanitize_url' ),
			'wps_enable_open_graph'          => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_enable_canonical'           => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_enable_schema'              => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_enable_breadcrumbs'         => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_robots_mode'                => array( 'default' => 'default', 'sanitize_callback' => 'wps_sanitize_select' ),
			'wps_studio_archive_title'       => array( 'default' => '', 'sanitize_callback' => 'wps_sanitize_text' ),
			'wps_actress_archive_title'      => array( 'default' => '', 'sanitize_callback' => 'wps_sanitize_text' ),
			'wps_backup_include_menus'       => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_tools_enabled'              => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_debug_mode'                 => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_worker_enabled'              => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_player_api_enabled'          => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_player_api_url'              => array( 'default' => '', 'sanitize_callback' => 'wps_sanitize_url' ),
			'wps_player_api_token'            => array( 'default' => '', 'sanitize_callback' => 'wps_sanitize_text' ),
			'wps_smart_cover_enabled'         => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_force_smart_cover'           => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_cover_recursive_lookup'      => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_register_local_covers'       => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_auto_rename_uploads'         => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_auto_assign_featured'        => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_auto_rules_enabled'          => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_self_healing_enabled'        => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_external_index_enabled'      => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_external_index_url'          => array( 'default' => '', 'sanitize_callback' => 'wps_sanitize_url' ),
			'wps_external_index_token'        => array( 'default' => '', 'sanitize_callback' => 'wps_sanitize_text' ),
			'wps_live_analytics_enabled'      => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_bot_shield_enabled'          => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_fake_googlebot_blocking'     => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_taxonomy_robots_enabled'     => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_developer_advanced_mode'     => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_auto_full_backup'            => array( 'default' => false, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
			'wps_backup_include_theme'        => array( 'default' => true, 'sanitize_callback' => 'wps_sanitize_checkbox' ),
		);
	}

	/**
	 * Register required sections and controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ) {
		$wp_customize->add_panel(
			'wps_framework_panel',
			array(
				'title'       => __( 'KolorTube', 'wpst' ),
				'description' => __( 'Modular theme framework settings. Existing theme option keys remain compatible.', 'wpst' ),
				'priority'    => 5,
			)
		);

		self::add_section( $wp_customize, 'wps_dashboard', __( 'Dashboard', 'wpst' ), 10 );
		self::add_section( $wp_customize, 'wps_video', __( 'Video', 'wpst' ), 20 );
		self::add_section( $wp_customize, 'wps_player', __( 'Player', 'wpst' ), 30 );
		self::add_section( $wp_customize, 'wps_theme_colors', __( 'Theme Colors', 'wpst' ), 40 );
		self::add_section( $wp_customize, 'wps_layout', __( 'Layout', 'wpst' ), 50 );
		self::add_section( $wp_customize, 'wps_redirect', __( 'Redirect', 'wpst' ), 60 );
		self::add_section( $wp_customize, 'wps_performance', __( 'Performance', 'wpst' ), 70 );
		self::add_section( $wp_customize, 'wps_security', __( 'Security', 'wpst' ), 80 );
		self::add_section( $wp_customize, 'wps_cleanup', __( 'Cleanup', 'wpst' ), 90 );
		self::add_section( $wp_customize, 'wps_cdn', __( 'CDN', 'wpst' ), 100 );
		self::add_section( $wp_customize, 'wps_ads', __( 'Ads', 'wpst' ), 110 );
		self::add_section( $wp_customize, 'wps_seo', __( 'SEO', 'wpst' ), 120 );
		self::add_section( $wp_customize, 'wps_homepage', __( 'Homepage', 'wpst' ), 130 );
		self::add_section( $wp_customize, 'wps_studio', __( 'Studio', 'wpst' ), 140 );
		self::add_section( $wp_customize, 'wps_actress', __( 'Actress', 'wpst' ), 150 );
		self::add_section( $wp_customize, 'wps_developer', __( 'Developer', 'wpst' ), 160 );
		self::add_section( $wp_customize, 'wps_backup', __( 'Backup', 'wpst' ), 170 );
		self::add_section( $wp_customize, 'wps_tools', __( 'Tools', 'wpst' ), 180 );

		self::register_new_settings( $wp_customize );
		self::relocate_legacy_controls( $wp_customize );
		self::remove_empty_legacy_containers( $wp_customize );
	}

	/**
	 * Add a section to the framework panel.
	 */
	private static function add_section( $wp_customize, $id, $title, $priority ) {
		$wp_customize->add_section(
			$id,
			array(
				'title'      => $title,
				'panel'      => 'wps_framework_panel',
				'priority'   => $priority,
				'capability' => 'edit_theme_options',
			)
		);
	}

	/**
	 * Register framework-specific settings.
	 */
	private static function register_new_settings( $wp_customize ) {
		foreach ( self::get_setting_schema() as $id => $args ) {
			if ( ! $wp_customize->get_setting( $id ) ) {
				$wp_customize->add_setting(
					$id,
					array(
						'type'              => 'theme_mod',
						'default'           => $args['default'],
						'transport'         => 'refresh',
						'sanitize_callback' => $args['sanitize_callback'],
					)
				);
			}
		}

		self::checkbox( $wp_customize, 'wps_dashboard_enabled', 'wps_dashboard', __( 'Enable Theme Dashboard', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_worker_enabled', 'wps_dashboard', __( 'Enable background task worker', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_player_api_enabled', 'wps_player', __( 'Enable optional legacy Player API adapter', 'wpst' ) );
		self::text( $wp_customize, 'wps_player_api_url', 'wps_player', __( 'Player API endpoint URL', 'wpst' ), 'url' );
		self::password( $wp_customize, 'wps_player_api_token', 'wps_player', __( 'Player API token (excluded from JSON export)', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_smart_cover_enabled', 'wps_video', __( 'Enable smart cover fallback', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_force_smart_cover', 'wps_video', __( 'Force smart cover markup even when a featured image exists', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_cover_recursive_lookup', 'wps_video', __( 'Search year/month upload folders for exact cover filenames', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_register_local_covers', 'wps_video', __( 'Register matched local cover files in the Media Library', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_auto_rename_uploads', 'wps_video', __( 'Prefix newly uploaded image filenames', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_auto_assign_featured', 'wps_video', __( 'Auto-assign newly matched uploads as featured images', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_auto_rules_enabled', 'wps_video', __( 'Queue smart cover matching when a post is saved', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_self_healing_enabled', 'wps_tools', __( 'Run a daily self-healing cover pass', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_live_analytics_enabled', 'wps_dashboard', __( 'Enable first-party real-time visitor estimates', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_external_index_enabled', 'wps_tools', __( 'Enable optional external search-index synchronization', 'wpst' ) );
		self::text( $wp_customize, 'wps_external_index_url', 'wps_tools', __( 'External search-index endpoint URL', 'wpst' ), 'url' );
		self::password( $wp_customize, 'wps_external_index_token', 'wps_tools', __( 'External index token (excluded from JSON export)', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_debug_mode', 'wps_developer', __( 'Enable framework debug logging (requires WP_DEBUG)', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_redirect_attachment_pages', 'wps_redirect', __( 'Redirect attachment pages', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_disable_emojis', 'wps_performance', __( 'Disable WordPress emoji assets', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_disable_xmlrpc', 'wps_performance', __( 'Disable XML-RPC', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_litespeed_primary', 'wps_performance', __( 'Use LiteSpeed Cache as the primary optimizer', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_litespeed_purge_on_save', 'wps_performance', __( 'Purge LiteSpeed after Customizer changes', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_lazy_load', 'wps_performance', __( 'Framework native lazy-load fallback (ignored while LiteSpeed is primary)', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_webp_support', 'wps_performance', __( 'Allow WebP uploads', 'wpst' ) );
		self::select(
			$wp_customize,
			'wps_heartbeat_mode',
			'wps_performance',
			__( 'Heartbeat control', 'wpst' ),
			array(
				'default'          => __( 'WordPress default', 'wpst' ),
				'reduce'           => __( 'Reduce to 60 seconds', 'wpst' ),
				'disable_frontend' => __( 'Disable on front end', 'wpst' ),
				'disable'          => __( 'Disable everywhere except post editor', 'wpst' ),
			)
		);

		self::checkbox( $wp_customize, 'wps_basic_hardening', 'wps_security', __( 'Enable basic hardening', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_protect_admin', 'wps_security', __( 'Enable wp-admin protection hooks', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_disable_rest_users', 'wps_security', __( 'Disable public REST user endpoints', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_bot_shield_enabled', 'wps_security', __( 'Enable verified bot rate limiting and comment shield', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_fake_googlebot_blocking', 'wps_security', __( 'Block clients claiming Googlebot without verified DNS', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_disable_comments', 'wps_cleanup', __( 'Disable comments sitewide', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_disable_feeds', 'wps_cleanup', __( 'Disable RSS/Atom feeds', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_cdn_enabled', 'wps_cdn', __( 'Enable legacy CDN rewriting fallback (ignored while LiteSpeed is primary)', 'wpst' ) );
		self::text( $wp_customize, 'wps_cdn_url', 'wps_cdn', __( 'CDN base URL', 'wpst' ), 'url' );

		self::checkbox( $wp_customize, 'wps_enable_open_graph', 'wps_seo', __( 'Enable Open Graph metadata', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_enable_canonical', 'wps_seo', __( 'Enable canonical URLs', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_enable_schema', 'wps_seo', __( 'Enable JSON-LD schema', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_enable_breadcrumbs', 'wps_seo', __( 'Enable breadcrumb helper and shortcode', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_taxonomy_robots_enabled', 'wps_seo', __( 'Enable selective taxonomy robots rules', 'wpst' ) );
		self::select(
			$wp_customize,
			'wps_robots_mode',
			'wps_seo',
			__( 'Robots control', 'wpst' ),
			array(
				'default' => __( 'WordPress default', 'wpst' ),
				'index'   => __( 'Force index, follow', 'wpst' ),
				'noindex' => __( 'Force noindex, follow', 'wpst' ),
			)
		);

		self::text( $wp_customize, 'wps_studio_archive_title', 'wps_studio', __( 'Studio archive title override', 'wpst' ) );
		self::text( $wp_customize, 'wps_actress_archive_title', 'wps_actress', __( 'Actress archive title override', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_backup_include_menus', 'wps_backup', __( 'Include menu locations in exports', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_auto_full_backup', 'wps_backup', __( 'Enable automatic daily full backup', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_backup_include_theme', 'wps_backup', __( 'Include the current theme in full backups', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_developer_advanced_mode', 'wps_developer', __( 'Enable guarded developer advanced mode', 'wpst' ) );
		self::checkbox( $wp_customize, 'wps_tools_enabled', 'wps_tools', __( 'Enable dashboard tools', 'wpst' ) );

		// Keep the real footer site-info/copyright setting visible in the modular Layout panel.
		// The legacy Copyright section is intentionally removed later, so ensure its control survives there.
		if ( $wp_customize->get_setting( 'copyright_content' ) && ! $wp_customize->get_control( 'wps_footer_copyright_control' ) ) {
			$wp_customize->add_control(
				'wps_footer_copyright_control',
				array(
					'label'       => __( 'Footer Copyright / site-info', 'wpst' ),
					'description' => __( 'ข้อความที่แสดงใน site-info ด้านล่างเว็บไซต์', 'wpst' ),
					'section'     => 'wps_layout',
					'settings'    => 'copyright_content',
					'type'        => 'textarea',
				)
			);
		}
	}

	/**
	 * Move existing KolorTube controls into the modular panel.
	 *
	 * The original setting and control objects are reused, so values, custom
	 * control classes and all legacy front-end behavior remain unchanged.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	private static function relocate_legacy_controls( $wp_customize ) {
		$exact = array(
			'enable_video_preview'       => 'wps_video',
			'enable_thumbs_rotation'     => 'wps_video',
			'enable_video_tracking_link' => 'wps_player',
			'main_color'                 => 'wps_theme_colors',
			'link_color'                 => 'wps_theme_colors',
			'body_background_color'      => 'wps_theme_colors',
			'wps_color_preset'           => 'wps_theme_colors',
			'wps_site_background_color'  => 'wps_theme_colors',
			'wps_menu_background_color'  => 'wps_theme_colors',
			'wps_menu_active_bg'         => 'wps_theme_colors',
			'wps_button_start_color'     => 'wps_theme_colors',
			'wps_button_middle_color'    => 'wps_theme_colors',
			'wps_button_end_color'       => 'wps_theme_colors',
			'wps_soft_surface_color'     => 'wps_theme_colors',
			'wps_soft_border_color'      => 'wps_theme_colors',
			'wps_taxonomy_surface'       => 'wps_theme_colors',
			'wps_featured_card_bg'       => 'wps_theme_colors',
			'wps_tag_card_bg'            => 'wps_theme_colors',
			'sidebar_position'           => 'wps_layout',
			'mobile_columns'             => 'wps_layout',
			'wpst_container_type'        => 'wps_layout',
			'wpst_posts_index_style'     => 'wps_layout',
			'wpst_sidebar_position'      => 'wps_layout',
			'copyright_content'          => 'wps_layout',
			'google_analytics_code'      => 'wps_developer',
			'meta_verification_code'     => 'wps_developer',
			'other_script_codes'         => 'wps_developer',
		);

		foreach ( $wp_customize->controls() as $control ) {
			$setting_ids = self::control_setting_ids( $control );
			foreach ( $setting_ids as $setting_id ) {
				$section = isset( $exact[ $setting_id ] ) ? $exact[ $setting_id ] : self::section_for_legacy_setting( $setting_id );
				if ( $section ) {
					$control->section = $section;
					break;
				}
			}
		}
	}


	/**
	 * Remove legacy containers after their original controls have been moved.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	private static function remove_empty_legacy_containers( $wp_customize ) {
		$sections = array(
			'wpst_general', 'wpst_colors', 'wpst_theme_layout_options', 'wpst_mobile',
			'wpst_ads_home', 'wpst_ads_single_video_page', 'wpst_ads_actor_page',
			'wpst_ads_category_page', 'wpst_ads_tag_page', 'wpst_ads_search_result_page',
			'wpst_seo_home', 'wpst_seo_video', 'wpst_seo_video_cat', 'wpst_seo_video_tag',
			'wpst_seo_search', 'wpst_copyright', 'wpst_scripts_section',
		);
		foreach ( $sections as $section ) {
			$wp_customize->remove_section( $section );
		}
		if ( method_exists( $wp_customize, 'remove_panel' ) ) {
			$wp_customize->remove_panel( 'wpst_ads' );
			$wp_customize->remove_panel( 'wpst_seo' );
		}
	}

	/**
	 * Return setting IDs used by a Customizer control.
	 *
	 * @param WP_Customize_Control $control Control instance.
	 * @return array<int,string>
	 */
	private static function control_setting_ids( $control ) {
		$ids      = array();
		$settings = isset( $control->settings ) ? $control->settings : array();

		if ( is_string( $settings ) ) {
			$ids[] = $settings;
		} elseif ( is_object( $settings ) && isset( $settings->id ) ) {
			$ids[] = (string) $settings->id;
		} elseif ( is_array( $settings ) ) {
			foreach ( $settings as $key => $setting ) {
				if ( is_object( $setting ) && isset( $setting->id ) ) {
					$ids[] = (string) $setting->id;
				} elseif ( is_string( $setting ) ) {
					$ids[] = $setting;
				} elseif ( is_string( $key ) ) {
					$ids[] = $key;
				}
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Resolve a modular section from a legacy setting prefix.
	 *
	 * @param string $setting_id Setting ID.
	 * @return string
	 */
	private static function section_for_legacy_setting( $setting_id ) {
		if ( 0 === strpos( $setting_id, 'ads_' ) ) {
			return 'wps_ads';
		}
		if ( 0 === strpos( $setting_id, 'seo_home_' ) ) {
			return 'wps_homepage';
		}
		if ( 0 === strpos( $setting_id, 'seo_' ) ) {
			return 'wps_seo';
		}
		if ( false !== strpos( $setting_id, 'video' ) || false !== strpos( $setting_id, 'thumb' ) ) {
			return 'wps_video';
		}
		return '';
	}

	private static function checkbox( $wp_customize, $id, $section, $label ) {
		$wp_customize->add_control( $id . '_control', array( 'label' => $label, 'section' => $section, 'settings' => $id, 'type' => 'checkbox' ) );
	}

	/** Add a password control without exposing the saved value in markup elsewhere. */
	private static function password( $wp_customize, $id, $section, $label ) {
		$wp_customize->add_control(
			$id,
			array(
				'label'   => $label,
				'section' => $section,
				'type'    => 'password',
			)
		);
	}

	private static function text( $wp_customize, $id, $section, $label, $type = 'text' ) {
		$wp_customize->add_control( $id . '_control', array( 'label' => $label, 'section' => $section, 'settings' => $id, 'type' => $type ) );
	}

	private static function select( $wp_customize, $id, $section, $label, $choices ) {
		$wp_customize->add_control( $id . '_control', array( 'label' => $label, 'section' => $section, 'settings' => $id, 'type' => 'select', 'choices' => $choices ) );
	}


	/**
	 * Version-3 compatibility entry point. Existing controls are relocated,
	 * never duplicated.
	 *
	 * @deprecated 4.1.0 Legacy private method retained for reflection-based customizations.
	 */
	private static function register_compatibility_controls( $wp_customize ) {
		self::relocate_legacy_controls( $wp_customize );
	}

	/** Ensure a legacy mirror setting has a sanitizer without overwriting its value. */
	private static function ensure_mirror_setting( $wp_customize, $id, $default, $sanitize_callback ) {
		$setting = $wp_customize->get_setting( $id );
		if ( ! $setting ) {
			$wp_customize->add_setting( $id, array( 'type' => 'theme_mod', 'default' => $default, 'transport' => 'refresh', 'sanitize_callback' => $sanitize_callback ) );
			return;
		}
		$setting->sanitize_callback = $sanitize_callback;
	}

	/** Retained version-3 select mirror helper. */
	private static function mirror_select( $wp_customize, $id, $section, $label, $choices, $default ) {
		self::ensure_mirror_setting( $wp_customize, $id, $default, 'wps_sanitize_select' );
		$control_id = 'wps_mirror_' . $id;
		if ( ! $wp_customize->get_control( $control_id ) ) {
			$wp_customize->add_control( $control_id, array( 'label' => $label, 'section' => $section, 'settings' => $id, 'type' => 'select', 'choices' => $choices ) );
		}
	}

	/** Retained version-3 color mirror helper. */
	private static function mirror_color( $wp_customize, $id, $section, $label, $default ) {
		self::ensure_mirror_setting( $wp_customize, $id, $default, 'wps_sanitize_color' );
		$control_id = 'wps_mirror_' . $id;
		if ( ! $wp_customize->get_control( $control_id ) && class_exists( 'WP_Customize_Color_Control' ) ) {
			$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $control_id, array( 'label' => $label, 'section' => $section, 'settings' => $id ) ) );
		}
	}

	/** Retained version-3 text mirror helper. */
	private static function mirror_text( $wp_customize, $id, $section, $label, $default ) {
		self::ensure_mirror_setting( $wp_customize, $id, $default, 'wps_sanitize_text' );
		$control_id = 'wps_mirror_' . $id;
		if ( ! $wp_customize->get_control( $control_id ) ) {
			$wp_customize->add_control( $control_id, array( 'label' => $label, 'section' => $section, 'settings' => $id, 'type' => 'text' ) );
		}
	}

	/** Retained version-3 textarea mirror helper. */
	private static function mirror_textarea( $wp_customize, $id, $section, $label, $default ) {
		self::ensure_mirror_setting( $wp_customize, $id, $default, 'wps_sanitize_html' );
		$control_id = 'wps_mirror_' . $id;
		if ( ! $wp_customize->get_control( $control_id ) ) {
			$wp_customize->add_control( $control_id, array( 'label' => $label, 'section' => $section, 'settings' => $id, 'type' => 'textarea' ) );
		}
	}

}
