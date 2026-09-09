<?php
/** Optional LiteSpeed integration. The theme remains fully usable without LiteSpeed Cache. */
defined( 'ABSPATH' ) || exit;

final class WPS_LiteSpeed {
    const SNAPSHOT_OPTION = 'wps_pre_litespeed_snapshot';

    public static function is_active() {
        if ( defined( 'LSCWP_V' ) || defined( 'LSCWP_DIR' ) || class_exists( 'LiteSpeed\\Core' ) ) return true;
        if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) ) {
            $file = ABSPATH . 'wp-admin/includes/plugin.php';
            if ( is_file( $file ) ) require_once $file;
        }
        return function_exists( 'is_plugin_active' ) && is_plugin_active( 'litespeed-cache/litespeed-cache.php' );
    }

    public static function is_primary() {
        return self::is_active() && (bool) get_theme_mod( 'wps_litespeed_primary', false );
    }

    public static function manages( $feature ) {
        if ( ! self::is_primary() ) return false;
        return in_array( sanitize_key( $feature ), array( 'cache','page_cache','object_cache','lazy_load','assets','images','cdn','database' ), true );
    }

    public static function status() {
        $active = self::is_active();
        $version = defined( 'LSCWP_V' ) ? (string) LSCWP_V : ( $active ? __( 'Detected', 'wpst' ) : __( 'Unavailable', 'wpst' ) );
        $server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'Unavailable', 'wpst' );
        $conflicts = array();
        if ( $active && ! self::is_primary() ) $conflicts[] = __( 'LiteSpeed active in framework fallback mode', 'wpst' );
        return array(
            'active' => $active,
            'primary' => self::is_primary(),
            'version' => $version,
            'server' => $server,
            'object_cache' => function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache(),
            'conflicts' => $conflicts,
        );
    }

    public static function purge_all() {
        WPS_Cache_Manager::clear_framework_cache();
        if ( ! self::is_active() ) return false;
        do_action( 'litespeed_purge_all' );
        do_action( 'litespeed_purge_all_object' );
        return true;
    }

    public static function purge_object_cache() {
        if ( ! self::is_active() ) return false;
        do_action( 'litespeed_purge_all_object' );
        if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
        return true;
    }

    public static function apply_safe_defaults() {
        if ( ! self::is_active() ) return false;
        if ( false === get_option( self::SNAPSHOT_OPTION, false ) ) {
            update_option( self::SNAPSHOT_OPTION, array(
                'wps_litespeed_primary' => get_theme_mod( 'wps_litespeed_primary', false ),
                'wps_lazy_load' => get_theme_mod( 'wps_lazy_load', true ),
                'wps_cdn_enabled' => get_theme_mod( 'wps_cdn_enabled', false ),
                'wps_debug_mode' => get_theme_mod( 'wps_debug_mode', false ),
            ), false );
        }
        set_theme_mod( 'wps_litespeed_primary', true );
        set_theme_mod( 'wps_lazy_load', false );
        set_theme_mod( 'wps_cdn_enabled', false );
        set_theme_mod( 'wps_debug_mode', false );
        return true;
    }

    public static function restore_fallback_settings() {
        $snapshot = get_option( self::SNAPSHOT_OPTION, false );
        if ( ! is_array( $snapshot ) ) return false;
        foreach ( $snapshot as $key => $value ) set_theme_mod( $key, $value );
        delete_option( self::SNAPSHOT_OPTION );
        WPS_Cache_Manager::clear_framework_cache();
        return true;
    }
}
