<?php
/** Lightweight cache coordinator. Safe when no page-cache plugin is installed. */
defined( 'ABSPATH' ) || exit;

final class WPS_Cache_Manager {
    public static function status() {
        if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
            return __( 'Persistent object cache active', 'wpst' );
        }
        return __( 'WordPress transient fallback', 'wpst' );
    }

    public static function clear_framework_cache() {
        global $wpdb;
        foreach ( array( 'wps_system_status', 'wps_performance_snapshot', 'wps_health_check' ) as $key ) {
            delete_transient( $key );
        }
        // Remove only WPS-owned transients. Never flush unrelated site/plugin data.
        if ( isset( $wpdb->options ) ) {
            $like_a = $wpdb->esc_like( '_transient_wps_' ) . '%';
            $like_b = $wpdb->esc_like( '_transient_timeout_wps_' ) . '%';
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like_a, $like_b ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        }
        if ( function_exists( 'wp_cache_flush_group' ) ) wp_cache_flush_group( 'wps' );
        do_action( 'wps_framework_cache_cleared' );
        return true;
    }

    public static function clear_all() {
        self::clear_framework_cache();
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
        do_action( 'wps_cache_cleared' );
        return true;
    }
}
