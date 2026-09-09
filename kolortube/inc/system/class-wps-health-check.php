<?php
/** Framework health checks with short-lived caching. */
defined( 'ABSPATH' ) || exit;

final class WPS_Health_Check {
	/** Return health tests. */
	public static function get( $refresh = false ) {
		if ( ! $refresh ) {
			$cached = get_transient( 'wps_health_check' );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		global $wpdb;
		$upload       = wp_upload_dir();
		$task_ready   = class_exists( 'WPS_Task_Store' ) && WPS_Task_Store::is_ready( true );
		$litespeed    = class_exists( 'WPS_LiteSpeed' ) ? WPS_LiteSpeed::status() : array();
		$conflicts    = isset( $litespeed['conflicts'] ) && is_array( $litespeed['conflicts'] ) ? $litespeed['conflicts'] : array();
		$player_api   = class_exists( 'WPS_Player' ) ? WPS_Player::api_status() : array( 'enabled' => false, 'configured' => false, 'circuit_open' => false );
		$event_table  = ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY && class_exists( 'WPMB_Event_Log' ) ) ? WPMB_Event_Log::table_name() : ( class_exists( 'WPS_Event_Log' ) ? WPS_Event_Log::table_name() : $wpdb->prefix . 'wps_events' );
		$search_table = class_exists( 'WPS_Search_Index' ) ? WPS_Search_Index::table_name() : $wpdb->prefix . 'wps_search_index';
		$live_table   = ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY && class_exists( 'WPMB_Live_Analytics' ) ) ? WPMB_Live_Analytics::table_name() : ( class_exists( 'WPS_Live_Analytics' ) ? WPS_Live_Analytics::table_name() : $wpdb->prefix . 'wps_live_sessions' );
		$seo_conflict = class_exists( 'WPS_SEO' ) && method_exists( 'WPS_SEO', 'has_seo_plugin' ) ? WPS_SEO::has_seo_plugin() : false;
		$wpmb_owns_worker = defined( 'WPMB_REPAIR_ENGINE_READY' ) && WPMB_REPAIR_ENGINE_READY
			&& defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY
			&& defined( 'WPMB_FULL_BACKUP_READY' ) && WPMB_FULL_BACKUP_READY;
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$https_ready = 0 === strpos( home_url(), 'https://' ) || in_array( $site_host, array( 'localhost', '127.0.0.1', '::1' ), true );

		$tests = array(
			array( 'label' => __( 'PHP 8.0 or newer', 'wpst' ), 'ok' => version_compare( PHP_VERSION, '8.0', '>=' ), 'detail' => PHP_VERSION ),
			array( 'label' => __( 'WordPress 6.0 or newer', 'wpst' ), 'ok' => version_compare( get_bloginfo( 'version' ), '6.0', '>=' ), 'detail' => get_bloginfo( 'version' ) ),
			array( 'label' => __( 'Uploads directory writable', 'wpst' ), 'ok' => empty( $upload['error'] ) && wp_is_writable( $upload['basedir'] ), 'detail' => empty( $upload['error'] ) ? $upload['basedir'] : $upload['error'] ),
			array( 'label' => __( 'Background worker scheduled', 'wpst' ), 'ok' => $wpmb_owns_worker || (bool) wp_next_scheduled( WPS_Task_Runner::CRON_HOOK ), 'detail' => $wpmb_owns_worker ? __( 'Owned by WP MY BOSS', 'wpst' ) : __( 'WP-Cron queue worker', 'wpst' ) ),
			array( 'label' => __( 'Daily maintenance scheduled', 'wpst' ), 'ok' => (bool) wp_next_scheduled( ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) ? 'wpmb_daily_maintenance' : 'wps_daily_maintenance' ), 'detail' => ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) ? __( 'Owned by WP MY BOSS', 'wpst' ) : __( 'Theme fallback maintenance', 'wpst' ) ),
			array( 'label' => __( 'Pretty permalinks enabled', 'wpst' ), 'ok' => '' !== (string) get_option( 'permalink_structure' ), 'detail' => (string) get_option( 'permalink_structure' ) ),
			array( 'label' => __( 'WP-Script Core available', 'wpst' ), 'ok' => function_exists( 'WPSCORE' ), 'detail' => function_exists( 'WPSCORE' ) ? __( 'Loaded', 'wpst' ) : __( 'Required for legacy theme features', 'wpst' ) ),
			array( 'label' => __( 'Task database available', 'wpst' ), 'ok' => $task_ready, 'detail' => class_exists( 'WPS_Task_Store' ) ? WPS_Task_Store::table_name() : __( 'Task module unavailable', 'wpst' ) ),
			array( 'label' => __( 'Media index available', 'wpst' ), 'ok' => $task_ready, 'detail' => class_exists( 'WPS_Task_Store' ) ? WPS_Task_Store::media_table_name() : __( 'Media module unavailable', 'wpst' ) ),
			array( 'label' => __( 'Event log database available', 'wpst' ), 'ok' => self::table_exists( $event_table ), 'detail' => $event_table ),
			array( 'label' => __( 'Search index database available', 'wpst' ), 'ok' => self::table_exists( $search_table ), 'detail' => $search_table ),
			array( 'label' => __( 'Live analytics database available', 'wpst' ), 'ok' => self::table_exists( $live_table ), 'detail' => $live_table ),
			array( 'label' => __( 'HTTPS site URL', 'wpst' ), 'ok' => $https_ready, 'detail' => home_url() ),
			array( 'label' => __( 'Image validation available', 'wpst' ), 'ok' => function_exists( 'wp_getimagesize' ) || function_exists( 'getimagesize' ), 'detail' => __( 'MIME, dimensions and file-size checks', 'wpst' ) ),
			array(
				'label'  => __( 'LiteSpeed Cache available', 'wpst' ),
				'ok'     => ! empty( $litespeed['active'] ),
				'detail' => ! empty( $litespeed['active'] ) ? sprintf( __( 'Version %s', 'wpst' ), isset( $litespeed['version'] ) ? $litespeed['version'] : __( 'detected', 'wpst' ) ) : __( 'Install and activate LiteSpeed Cache', 'wpst' ),
			),
			array(
				'label'  => __( 'LiteSpeed is the primary optimizer', 'wpst' ),
				'ok'     => ! empty( $litespeed['primary'] ),
				'detail' => ! empty( $litespeed['primary'] ) ? __( 'Framework cache, lazy-load, WebP and CDN overlap is suppressed', 'wpst' ) : __( 'Enable LiteSpeed primary mode in the Customizer', 'wpst' ),
			),
			array(
				'label'  => __( 'No overlapping optimization plugins', 'wpst' ),
				'ok'     => empty( $conflicts ),
				'detail' => empty( $conflicts ) ? __( 'No conflict detected', 'wpst' ) : implode( ', ', $conflicts ),
			),
			array(
				'label'  => __( 'SEO output conflict protection', 'wpst' ),
				'ok'     => true,
				'detail' => $seo_conflict ? __( 'SEO plugin detected; framework canonical, Open Graph and schema output is suppressed', 'wpst' ) : __( 'Framework SEO output available', 'wpst' ),
			),
			array(
				'label'  => __( 'Optional Player API is safe', 'wpst' ),
				'ok'     => empty( $player_api['enabled'] ) || ( ! empty( $player_api['configured'] ) && empty( $player_api['circuit_open'] ) ),
				'detail' => empty( $player_api['enabled'] ) ? __( 'Disabled; original KolorTube player remains active', 'wpst' ) : ( empty( $player_api['configured'] ) ? __( 'Enabled without a valid endpoint', 'wpst' ) : ( ! empty( $player_api['circuit_open'] ) ? __( 'Temporarily paused after a remote failure', 'wpst' ) : __( 'Configured', 'wpst' ) ) ),
			),
		);

		set_transient( 'wps_health_check', $tests, 5 * MINUTE_IN_SECONDS );
		return $tests;
	}

	/** Force a health refresh. */
	public static function refresh() {
		delete_transient( 'wps_health_check' );
		return self::get( true );
	}

	/** Retained compatibility helper for physical framework-table checks. */
	private static function table_exists( $table ) {
		global $wpdb;
		if ( ! is_string( $table ) || '' === $table ) {
			return false;
		}
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $table === $found;
	}
}
