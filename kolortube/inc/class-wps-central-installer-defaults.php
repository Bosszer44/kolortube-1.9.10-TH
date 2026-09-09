<?php
/**
 * Generic fresh-install defaults for the central KolorTube package.
 * Existing theme mods/options are preserved.
 */
defined( 'ABSPATH' ) || exit;

final class WPS_Central_Installer_Defaults {
    const MARKER = 'wps_central_defaults_version';
    const VERSION = '1.1.2';

    public static function init() {
        add_action( 'after_switch_theme', array( __CLASS__, 'apply' ), 20 );
        add_action( 'after_setup_theme', array( __CLASS__, 'maybe_apply_once' ), 3 );
    }

    public static function maybe_apply_once() {
        if ( get_option( self::MARKER, '' ) !== self::VERSION ) {
            self::apply();
        }
    }

    private static function set_mod_if_missing( $key, $value ) {
        if ( null === get_theme_mod( $key, null ) ) {
            set_theme_mod( $key, $value );
        }
    }

    private static function merge_missing_option( $name, array $defaults ) {
        $stored = get_option( $name, null );
        if ( ! is_array( $stored ) ) {
            update_option( $name, $defaults, false );
            return;
        }
        $merged = array_replace_recursive( $defaults, $stored );
        if ( $merged !== $stored ) {
            update_option( $name, $merged, false );
        }
    }

    public static function apply() {
        $previous_version = get_option( self::MARKER, '' );
        $mods = array(
            'wps_tools_enabled'                    => true,
            'wps_dashboard_enabled'                => true,
            'wps_smart_cover_enabled'              => true,
            'mobile_columns'                        => '2',
            'video_listing_general_show_duration'   => 'yes',
            'video_listing_general_show_title'      => 'yes',
            'enable_video_preview'                  => 'yes',
            'enable_thumbs_rotation'                => 'yes',
            'wps_lazy_load'                        => true,
            'wps_settings_snapshot_enabled'         => true,
            'wps_settings_restore_after_update'     => true,
        );
        foreach ( $mods as $key => $value ) {
            self::set_mod_if_missing( $key, $value );
        }

        self::merge_missing_option( 'wps_professional_settings', array(
            'ui_language' => 'th',
            'frontend_language' => 'th',
            'show_language_switcher' => 0,
            'search_enabled' => 1,
            'rest_api_enabled' => 1,
            'feed_redirect_enabled' => 1,
            'video_download_deterrence' => 1,
            'asset_hardening' => 1,
            // Fresh installs contain no banner media or markup. Existing sites
            // keep their saved setting when this package is updated.
            'ads_master_enabled' => 0,
            'home_sections_enabled' => 1,
            'home_show_featured' => 1,
            'home_show_seo' => 1,
            'home_show_studios' => 1,
            'home_show_actors' => 1,
            'home_show_tags' => 1,
            'home_columns_desktop' => 5,
            'home_columns_tablet' => 3,
            'home_columns_mobile' => 2,
            'home_rows' => 2,
            'home_featured_slider' => 1,
            'home_studio_slider' => 0,
            'home_actor_slider' => 0,
            'home_tag_slider' => 1,
            'home_slider_autoplay' => 1,
            'home_featured_autoplay' => 1,
            'home_slider_interval' => 3000,
            'home_section_order' => 'featured,latest_videos,studio,actors,gallery_tags,seo',
        ) );

        self::merge_missing_option( 'wps_theme_finalizer_settings', array(
            'lock_theme_settings' => 1,
            'auto_snapshot_settings' => 1,
            'player_show_poster_overlay' => 1,
            'player_hide_duration' => 1,
            'player_hide_views' => 1,
            'player_hide_likes'         => 0,
            'player_hide_like_percent' => 1,
            'player_hide_rating_bar' => 1,
        ) );

        // Version 1.1.1 accidentally disabled the like button in the shipped
        // defaults. Restore it for sites that received that package default.
        if ( '1.1.1' === $previous_version ) {
            $finalizer = get_option( 'wps_theme_finalizer_settings', array() );
            if ( is_array( $finalizer ) && isset( $finalizer['player_hide_likes'] ) && 1 === (int) $finalizer['player_hide_likes'] ) {
                $finalizer['player_hide_likes'] = 0;
                update_option( 'wps_theme_finalizer_settings', $finalizer, false );
            }
        }

        update_option( self::MARKER, self::VERSION, false );
        update_option( 'wps_central_package_ready', 1, false );
    }
}

WPS_Central_Installer_Defaults::init();
