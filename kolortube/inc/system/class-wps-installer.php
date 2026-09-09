<?php
/**
 * Framework database installer and upgrade coordinator.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Installer {
	const DB_VERSION = '4.1.0';
	const DB_OPTION  = 'wps_db_version';

	/** @var bool */
	private static $installing = false;

	/** Register lightweight upgrade hooks. */
	public static function register() {
		add_action( 'after_switch_theme', array( __CLASS__, 'install' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ), 1 );
		add_action( 'init', array( __CLASS__, 'ensure_maintenance_schedule' ), 21 );
	}

	/** Install or upgrade framework tables. */
	public static function install() {
		if ( self::$installing ) {
			return false;
		}
		self::$installing = true;

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tasks_table     = WPS_Task_Store::table_name();
		$media_table     = WPS_Task_Store::media_table_name();
		$events_table    = class_exists( 'WPS_Event_Log' ) ? WPS_Event_Log::table_name() : $wpdb->prefix . 'wps_events';
		$search_table    = class_exists( 'WPS_Search_Index' ) ? WPS_Search_Index::table_name() : $wpdb->prefix . 'wps_search_index';
		$live_table      = class_exists( 'WPS_Live_Analytics' ) ? WPS_Live_Analytics::table_name() : $wpdb->prefix . 'wps_live_sessions';

		$sql_tasks = "CREATE TABLE {$tasks_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(64) NOT NULL,
			payload longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			priority smallint(5) unsigned NOT NULL DEFAULT 10,
			progress bigint(20) unsigned NOT NULL DEFAULT 0,
			total bigint(20) unsigned NOT NULL DEFAULT 0,
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			max_attempts smallint(5) unsigned NOT NULL DEFAULT 3,
			message text NULL,
			unique_key varchar(191) NOT NULL DEFAULT '',
			available_at datetime NOT NULL,
			locked_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			completed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status_available (status,available_at),
			KEY type_status (type,status),
			KEY unique_key_status (unique_key,status),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$sql_media = "CREATE TABLE {$media_table} (
			post_id bigint(20) unsigned NOT NULL,
			cover_status varchar(20) NOT NULL DEFAULT 'unknown',
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			cover_url text NULL,
			source varchar(32) NOT NULL DEFAULT '',
			checked_at datetime NOT NULL,
			PRIMARY KEY  (post_id),
			KEY cover_status (cover_status),
			KEY attachment_id (attachment_id)
		) {$charset_collate};";

		$sql_events = "CREATE TABLE {$events_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL DEFAULT 'info',
			code varchar(100) NOT NULL DEFAULT '',
			message text NULL,
			context longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY level_created (level,created_at),
			KEY code_created (code,created_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_search = "CREATE TABLE {$search_table} (
			post_id bigint(20) unsigned NOT NULL,
			post_title varchar(500) NOT NULL DEFAULT '',
			post_name varchar(200) NOT NULL DEFAULT '',
			code varchar(64) NOT NULL DEFAULT '',
			actress text NULL,
			studio text NULL,
			taxonomy_text longtext NULL,
			searchable longtext NULL,
			video_url text NULL,
			video_hash char(64) NOT NULL DEFAULT '',
			title_hash char(64) NOT NULL DEFAULT '',
			content_hash char(64) NOT NULL DEFAULT '',
			updated_at datetime NOT NULL,
			PRIMARY KEY  (post_id),
			KEY code (code),
			KEY post_name (post_name),
			KEY video_hash (video_hash),
			KEY title_hash (title_hash),
			KEY content_hash (content_hash),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$sql_live = "CREATE TABLE {$live_table} (
			visitor_hash char(64) NOT NULL,
			last_seen datetime NOT NULL,
			is_bot tinyint(1) unsigned NOT NULL DEFAULT 0,
			source varchar(191) NOT NULL DEFAULT '',
			page_path varchar(500) NOT NULL DEFAULT '',
			user_agent_hash char(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (visitor_hash),
			KEY last_seen (last_seen),
			KEY bot_seen (is_bot,last_seen),
			KEY source_seen (source,last_seen)
		) {$charset_collate};";

		dbDelta( $sql_tasks );
		dbDelta( $sql_media );
		dbDelta( $sql_events );
		dbDelta( $sql_search );
		dbDelta( $sql_live );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
		WPS_Task_Store::reset_schema_cache();
		if ( class_exists( 'WPS_Task_Runner' ) ) {
			WPS_Task_Runner::ensure_schedule();
		}
		self::ensure_maintenance_schedule();
		self::$installing = false;
		return true;
	}


	/** Ensure bounded daily framework maintenance is scheduled. */
	public static function ensure_maintenance_schedule() {
		if ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) {
			if ( wp_next_scheduled( 'wps_daily_maintenance' ) ) { wp_clear_scheduled_hook( 'wps_daily_maintenance' ); }
			return;
		}
		if ( ! wp_next_scheduled( 'wps_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wps_daily_maintenance' );
		}
	}

	/** Run dbDelta only when the schema version or physical tables require it. */
	public static function maybe_install() {
		if ( self::$installing ) {
			return;
		}
		$version_matches = self::DB_VERSION === (string) get_option( self::DB_OPTION, '' );
		if ( ! $version_matches || ! WPS_Task_Store::is_ready( false ) ) {
			self::install();
		}
	}
}
