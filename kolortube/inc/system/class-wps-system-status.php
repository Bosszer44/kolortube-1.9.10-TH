<?php
/** Cached system and framework status provider. */
defined( 'ABSPATH' ) || exit;

final class WPS_System_Status {
	/** Return a cached system overview. */
	public static function get( $refresh = false ) {
		if ( ! $refresh ) {
			$cached = get_transient( 'wps_system_status' );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		global $wpdb;
		$theme         = wp_get_theme();
		$litespeed     = class_exists( 'WPS_LiteSpeed' ) ? WPS_LiteSpeed::status() : array();
		$conflict_list = ! empty( $litespeed['conflicts'] ) && is_array( $litespeed['conflicts'] ) ? $litespeed['conflicts'] : array();
		$player_api    = class_exists( 'WPS_Player' ) ? WPS_Player::api_status() : array();
		$post_counts   = wp_count_posts( 'post' );
		$published     = isset( $post_counts->publish ) ? absint( $post_counts->publish ) : 0;
		$featured      = absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_thumbnail_id' WHERE p.post_type='post' AND p.post_status=%s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'publish'
				)
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachments   = wp_count_attachments();
		$media_total   = is_object( $attachments ) ? array_sum( array_map( 'absint', (array) $attachments ) ) : 0;
		$recent_tasks  = class_exists( 'WPS_Task_Store' ) ? WPS_Task_Store::recent( 1 ) : array();
		$last_task     = ! empty( $recent_tasks[0]['updated_at'] ) ? $recent_tasks[0]['updated_at'] : __( 'None', 'wpst' );
		$latest_backup = class_exists( 'WPS_Full_Backup' ) ? WPS_Full_Backup::latest() : array();
		$seo_conflict  = class_exists( 'WPS_SEO' ) && method_exists( 'WPS_SEO', 'has_seo_plugin' ) && WPS_SEO::has_seo_plugin();
		$conflict_count = count( $conflict_list ) + ( $seo_conflict ? 1 : 0 );

		$status = array(
			'php_version'        => PHP_VERSION,
			'wordpress_version'  => get_bloginfo( 'version' ),
			'mysql_version'      => $wpdb->db_version(),
			'theme_version'      => $theme->get( 'Version' ),
			'framework_version'  => defined( 'WPS_FRAMEWORK_VERSION' ) ? WPS_FRAMEWORK_VERSION : WPS_VERSION,
			'memory_limit'       => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : ini_get( 'memory_limit' ),
			'memory_usage'       => size_format( memory_get_usage( true ) ),
			'upload_limit'       => size_format( wp_max_upload_size() ),
			'cache_status'       => class_exists( 'WPS_Cache_Manager' ) ? WPS_Cache_Manager::status() : __( 'Unavailable', 'wpst' ),
			'litespeed_status'   => ! empty( $litespeed['primary'] ) ? __( 'Primary optimizer', 'wpst' ) : ( ! empty( $litespeed['active'] ) ? __( 'Active, framework fallback mode', 'wpst' ) : __( 'Not detected', 'wpst' ) ),
			'litespeed_version'  => isset( $litespeed['version'] ) ? $litespeed['version'] : __( 'Unavailable', 'wpst' ),
			'litespeed_server'   => isset( $litespeed['server'] ) ? $litespeed['server'] : __( 'Unavailable', 'wpst' ),
			'cache_conflicts'    => $conflict_list ? implode( ', ', $conflict_list ) : __( 'None', 'wpst' ),
			'conflict_notices'   => $conflict_count,
			'cdn_status'         => class_exists( 'WPS_CDN' ) ? WPS_CDN::status_label() : __( 'Unavailable', 'wpst' ),
			'ssl_status'         => is_ssl() && 0 === strpos( home_url(), 'https://' ) ? __( 'Active', 'wpst' ) : __( 'Not fully active', 'wpst' ),
			'cron_status'        => wp_next_scheduled( WPS_Task_Runner::CRON_HOOK ) ? __( 'Scheduled', 'wpst' ) : __( 'Not scheduled', 'wpst' ),
			'debug_status'       => get_theme_mod( 'wps_debug_mode', false ) ? __( 'Framework debug enabled', 'wpst' ) : __( 'Disabled', 'wpst' ),
			'wp_debug'           => defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'Enabled', 'wpst' ) : __( 'Disabled', 'wpst' ),
			'wp_script_core'     => function_exists( 'WPSCORE' ) ? __( 'Loaded', 'wpst' ) : __( 'Not loaded', 'wpst' ),
			'player_api'         => ! empty( $player_api['configured'] ) ? ( ! empty( $player_api['circuit_open'] ) ? __( 'Paused after remote failure', 'wpst' ) : __( 'Configured (optional)', 'wpst' ) ) : __( 'Disabled; legacy player fallback active', 'wpst' ),
			'published_posts'    => $published,
			'featured_posts'     => $featured,
			'without_featured'   => max( 0, $published - $featured ),
			'media_attachments'  => absint( $media_total ),
			'indexed_posts'      => class_exists( 'WPS_Search_Index' ) ? WPS_Search_Index::count() : 0,
			'last_task'          => $last_task,
			'latest_backup'      => ! empty( $latest_backup['completed_at'] ) ? $latest_backup['completed_at'] : __( 'None', 'wpst' ),
		);

		set_transient( 'wps_system_status', $status, 5 * MINUTE_IN_SECONDS );
		return $status;
	}

	/** Small performance snapshot suitable for an admin card. */
	public static function performance() {
		$cached = get_transient( 'wps_performance_snapshot' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$snapshot = array(
			'database_queries' => isset( $wpdb->num_queries ) ? absint( $wpdb->num_queries ) : 0,
			'peak_memory'      => size_format( memory_get_peak_usage( true ) ),
			'object_cache'     => function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache(),
			'queue'            => class_exists( 'WPS_Task_Store' ) ? WPS_Task_Store::counts() : array(),
			'media'            => class_exists( 'WPS_Task_Store' ) ? WPS_Task_Store::media_counts() : array(),
		);
		set_transient( 'wps_performance_snapshot', $snapshot, MINUTE_IN_SECONDS );
		return $snapshot;
	}
}
