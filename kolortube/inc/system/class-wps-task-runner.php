<?php
/**
 * Background task queue processor and AJAX bridge.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Task_Runner {
	const CRON_HOOK   = 'wps_process_task_queue';
	const LOCK_OPTION = 'wps_task_worker_lock';
	const AJAX_NONCE  = 'wps_control_center';

	/** Register cron and authenticated AJAX handlers. */
	public static function register() {
		if ( defined( 'WPMB_REPAIR_ENGINE_READY' ) && WPMB_REPAIR_ENGINE_READY
			&& defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY
			&& defined( 'WPMB_FULL_BACKUP_READY' ) && WPMB_FULL_BACKUP_READY ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			return;
		}
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ), 20 );
		add_action( 'wp_ajax_wps_enqueue_task', array( __CLASS__, 'ajax_enqueue_task' ) );
		add_action( 'wp_ajax_wps_run_queue', array( __CLASS__, 'ajax_run_queue' ) );
		add_action( 'wp_ajax_wps_queue_status', array( __CLASS__, 'ajax_queue_status' ) );
		add_action( 'wp_ajax_wps_cancel_queue', array( __CLASS__, 'ajax_cancel_queue' ) );
		add_action( 'wp_ajax_wps_cleanup_queue', array( __CLASS__, 'ajax_cleanup_queue' ) );
	}

	/** Allowed public task types, including all version-3 systems. */
	public static function allowed_types() {
		$types = array(
			'cover_dry_run', 'bulk_cover', 'missing_scan', 'auto_media', 'media_backup', 'media_rollback',
			'rebuild', 'self_heal', 'search_match', 'rebuild_index', 'video_metadata', 'player_api_scan',
			'player_api_test', 'webp_convert', 'cleanup_scan', 'cleanup_run', 'duplicates_scan',
			'duplicates_trash', 'full_backup', 'cache_clear', 'flush_rewrite', 'health_check',
		);
		if ( defined( 'WPMB_FULL_BACKUP_READY' ) && WPMB_FULL_BACKUP_READY ) {
			$types = array_values( array_diff( $types, array( 'full_backup' ) ) );
		}
		if ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) {
			$types = array_values( array_diff( $types, array( 'cleanup_scan', 'cleanup_run', 'health_check' ) ) );
		}
		if ( defined( 'WPMB_REPAIR_ENGINE_READY' ) && WPMB_REPAIR_ENGINE_READY ) {
			$types = array_values( array_diff( $types, self::repair_owned_types() ) );
		}
		return $types;
	}

	/** Repair task ownership moved to WP MY BOSS while its engine is active. */
	private static function repair_owned_types() {
		return array(
			'cover_dry_run', 'bulk_cover', 'missing_scan', 'auto_media',
			'media_backup', 'media_rollback', 'rebuild', 'self_heal',
			'search_match', 'rebuild_index', 'duplicates_scan',
			'duplicates_trash', 'video_metadata', 'player_api_scan',
			'player_api_test', 'webp_convert', 'cache_clear', 'flush_rewrite',
		);
	}

	/** Add a one-minute queue schedule. */
	public static function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['wps_every_minute'] ) ) {
			$schedules['wps_every_minute'] = array( 'interval' => MINUTE_IN_SECONDS, 'display' => __( 'Every minute (AV Framework)', 'wpst' ) );
		}
		return $schedules;
	}

	/** Ensure the recurring worker is present. */
	public static function ensure_schedule() {
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( defined( 'WPMB_REPAIR_ENGINE_READY' ) && WPMB_REPAIR_ENGINE_READY
			&& defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY
			&& defined( 'WPMB_FULL_BACKUP_READY' ) && WPMB_FULL_BACKUP_READY ) {
			if ( $next ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
			return;
		}
		/* WP MY BOSS filters migrated repair types, while Theme keeps only
		 * runtime-specific fallback jobs that have not moved. */
		if ( get_theme_mod( 'wps_worker_enabled', true ) ) {
			if ( ! $next ) {
				wp_schedule_event( time() + 30, 'wps_every_minute', self::CRON_HOOK );
			}
		} elseif ( $next ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/** Prompt WP-Cron to process newly queued work soon. */
	public static function kick() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}

	/** Process a bounded number of task batches. */
	public static function process_queue( $limit = 3, $time_limit = 18 ) {
		if ( ! class_exists( 'WPS_Task_Store' ) || ! WPS_Task_Store::is_ready( true ) ) {
			return array( 'processed' => 0, 'locked' => false, 'unavailable' => true );
		}
		$limit      = min( 10, max( 1, absint( $limit ) ) );
		$time_limit = min( 45, max( 5, absint( $time_limit ) ) );
		$token      = self::acquire_lock();
		if ( ! $token ) {
			return array( 'processed' => 0, 'locked' => true );
		}
		$started = microtime( true );
		$count   = 0;
		try {
			while ( $count < $limit && ( microtime( true ) - $started ) < $time_limit ) {
				$task = WPS_Task_Store::claim_next();
				if ( ! $task ) {
					break;
				}
				self::process_task( $task );
				$count++;
			}
		} finally {
			self::release_lock( $token );
		}
		return array( 'processed' => $count, 'locked' => false );
	}

	/** Dispatch one task batch. */
	private static function process_task( array $task ) {
		if ( defined( 'WPMB_REPAIR_ENGINE_READY' ) && WPMB_REPAIR_ENGINE_READY && in_array( $task['type'] ?? '', self::repair_owned_types(), true ) ) {
			WPS_Task_Store::complete( $task['id'], max( 1, absint( $task['progress'] ?? 0 ) ), max( 1, absint( $task['total'] ?? 0 ) ), __( 'Repair ownership moved to WP MY BOSS; this legacy task was closed safely.', 'wpst' ) );
			return;
		}
		try {
			switch ( $task['type'] ) {
				case 'cover_dry_run': self::process_cover_dry_run( $task ); break;
				case 'bulk_cover': self::process_cover_batch( $task, false ); break;
				case 'self_heal': self::process_cover_batch( $task, true ); break;
				case 'missing_scan': self::process_missing_scan( $task ); break;
				case 'auto_media': self::process_auto_media( $task ); break;
				case 'media_backup': self::process_media_backup( $task ); break;
				case 'media_rollback': self::process_media_rollback( $task ); break;
				case 'rebuild': self::process_rebuild( $task ); break;
				case 'search_match': self::process_search_match( $task ); break;
				case 'rebuild_index': self::process_search_index( $task, false ); break;
				case 'duplicates_scan': self::process_search_index( $task, true ); break;
				case 'duplicates_trash': self::process_duplicates_trash( $task ); break;
				case 'video_metadata': self::process_video_metadata( $task ); break;
				case 'player_api_scan': self::process_player_api_scan( $task ); break;
				case 'player_api_test': self::process_player_api_test( $task ); break;
				case 'webp_convert': self::process_webp( $task ); break;
				case 'cleanup_scan': self::process_cleanup_scan( $task ); break;
				case 'cleanup_run': self::process_cleanup_run( $task ); break;
				case 'full_backup': self::process_full_backup( $task ); break;
				case 'cache_clear':
					WPS_Cache_Manager::clear_all();
					WPS_Task_Store::complete( $task['id'], 1, 1, __( 'LiteSpeed and framework cache cleared.', 'wpst' ) );
					break;
				case 'flush_rewrite':
					flush_rewrite_rules( false );
					WPS_Task_Store::complete( $task['id'], 1, 1, __( 'Rewrite rules flushed.', 'wpst' ) );
					break;
				case 'health_check':
					if ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) {
						if ( class_exists( 'WPMB_Health_Check' ) ) { WPMB_Health_Check::instance()->tests( true ); }
						WPS_Task_Store::complete( $task['id'], 1, 1, __( 'Health Check ownership moved to WP MY BOSS; this legacy task was closed safely.', 'wpst' ) );
						break;
					}
					WPS_Health_Check::refresh();
					WPS_Task_Store::complete( $task['id'], 1, 1, __( 'Health check refreshed.', 'wpst' ) );
					break;
				default: throw new RuntimeException( 'Unsupported task type.' );
			}
		} catch ( Throwable $error ) {
			WPS_Task_Store::fail_or_retry( $task, $error->getMessage() );
			do_action( 'wps_task_failed', $task, $error );
		}
	}

	/** Cover dry-run batch. */
	private static function process_cover_dry_run( array $task ) {
		$p = wp_parse_args( $task['payload'], array( 'cursor' => 0, 'batch_size' => 100, 'would_assign' => 0, 'fallback_only' => 0, 'missing' => 0 ) );
		$r = WPS_Bulk_Cover_Generator::dry_run_batch( $p['batch_size'], $p['cursor'] );
		foreach ( array( 'would_assign', 'fallback_only', 'missing' ) as $key ) {
			$p[ $key ] = absint( $p[ $key ] ) + absint( $r[ $key ] );
		}
		$p['cursor'] = $r['next_cursor'];
		$message = sprintf( __( 'Would assign %1$d; fallback only %2$d; missing %3$d.', 'wpst' ), $p['would_assign'], $p['fallback_only'], $p['missing'] );
		if ( $r['done'] ) {
			update_option( 'wps_cover_dry_run', array_merge( $p, array( 'completed_at' => current_time( 'mysql', true ), 'processed' => absint( $task['progress'] ) + $r['processed'] ) ), false );
			WPS_Task_Store::complete( $task['id'], absint( $task['progress'] ) + $r['processed'], $r['total'], __( 'Cover dry run completed.', 'wpst' ) . ' ' . $message );
		} else {
			WPS_Task_Store::release( $task['id'], $p, absint( $task['progress'] ) + $r['processed'], $r['total'], $message, 1 );
		}
	}

	/** Cover generation or self-healing batch. */
	private static function process_cover_batch( array $task, $self_heal ) {
		$p = wp_parse_args( $task['payload'], array( 'cursor' => 0, 'batch_size' => 50, 'success' => 0, 'skipped' => 0, 'missing' => 0 ) );
		$r = WPS_Bulk_Cover_Generator::process_batch( $p['batch_size'], $p['cursor'] );
		foreach ( array( 'success', 'skipped', 'missing' ) as $key ) { $p[ $key ] = absint( $p[ $key ] ) + absint( $r[ $key ] ); }
		$p['cursor'] = $r['next_cursor'];
		$progress = absint( $task['progress'] ) + $r['processed'];
		$message  = sprintf( __( 'Matched %1$d; skipped %2$d; missing %3$d.', 'wpst' ), $p['success'], $p['skipped'], $p['missing'] );
		if ( $r['done'] ) {
			WPS_Task_Store::complete( $task['id'], $progress, $r['total'], ( $self_heal ? __( 'Self-healing pass completed.', 'wpst' ) : __( 'Bulk cover generation completed.', 'wpst' ) ) . ' ' . $message );
		} else {
			WPS_Task_Store::release( $task['id'], $p, $progress, $r['total'], $message, 1 );
		}
	}

	/** Missing-cover scan batch. */
	private static function process_missing_scan( array $task ) {
		$p = wp_parse_args( $task['payload'], array( 'cursor' => 0, 'batch_size' => 100, 'found' => 0, 'missing' => 0 ) );
		$r = WPS_Bulk_Cover_Generator::scan_missing_batch( $p['batch_size'], $p['cursor'] );
		$p['cursor'] = $r['next_cursor']; $p['found'] += $r['found']; $p['missing'] += $r['missing'];
		$progress = absint( $task['progress'] ) + $r['processed'];
		$message  = sprintf( __( 'Found %1$d; missing %2$d.', 'wpst' ), $p['found'], $p['missing'] );
		if ( $r['done'] ) WPS_Task_Store::complete( $task['id'], $progress, $r['total'], __( 'Missing-cover scan completed.', 'wpst' ) . ' ' . $message );
		else WPS_Task_Store::release( $task['id'], $p, $progress, $r['total'], $message, 1 );
	}

	/** Auto-media scan batch. */
	private static function process_auto_media( array $task ) {
		$p = wp_parse_args( $task['payload'], array( 'cursor' => 0, 'batch_size' => 100, 'matched' => 0, 'assigned' => 0 ) );
		$r = WPS_Auto_Upload::scan_batch( $p['batch_size'], $p['cursor'] );
		$p['cursor'] = $r['next_cursor']; $p['matched'] += $r['matched']; $p['assigned'] += $r['assigned'];
		$progress = absint( $task['progress'] ) + $r['processed'];
		$message  = sprintf( __( 'Matched %1$d; assigned %2$d.', 'wpst' ), $p['matched'], $p['assigned'] );
		if ( $r['done'] ) WPS_Task_Store::complete( $task['id'], $progress, $r['total'], __( 'Auto-media scan completed.', 'wpst' ) . ' ' . $message );
		else WPS_Task_Store::release( $task['id'], $p, $progress, $r['total'], $message, 1 );
	}

	/** Media snapshot batch. */
	private static function process_media_backup( array $task ) {
		$r = WPS_Media_Backup::backup_batch( $task['payload'] );
		self::finish_resumable_result( $task, $r, __( 'Media backup completed.', 'wpst' ), __( 'Creating protected media backup.', 'wpst' ) );
	}

	/** Media rollback batch. */
	private static function process_media_rollback( array $task ) {
		$r = WPS_Media_Backup::rollback_batch( $task['payload'] );
		$message = is_wp_error( $r ) ? '' : sprintf( __( 'Restored %1$d; skipped %2$d.', 'wpst' ), absint( $r['payload']['restored'] ?? 0 ), absint( $r['payload']['skipped'] ?? 0 ) );
		self::finish_resumable_result( $task, $r, __( 'Media rollback completed.', 'wpst' ) . ' ' . $message, $message );
	}

	/** Rebuild: first create a rollback point, then enqueue independent child jobs. */
	private static function process_rebuild( array $task ) {
		$p = wp_parse_args( $task['payload'], array( 'stage' => 'backup', 'backup' => array() ) );
		if ( 'backup' === $p['stage'] ) {
			$r = WPS_Media_Backup::backup_batch( is_array( $p['backup'] ) ? $p['backup'] : array() );
			if ( is_wp_error( $r ) ) throw new RuntimeException( $r->get_error_message() );
			$p['backup'] = $r['payload'];
			if ( ! $r['done'] ) {
				WPS_Task_Store::release( $task['id'], $p, absint( $task['progress'] ) + $r['processed'], $r['total'] + 1, __( 'Creating rollback point before rebuild.', 'wpst' ), 1 );
				return;
			}
			$p['stage'] = 'queue';
		}
		WPS_Task_Store::enqueue( 'auto_media', array( 'cursor' => 0, 'batch_size' => 100 ), array( 'unique_key' => 'auto-media-full', 'priority' => 30 ) );
		WPS_Task_Store::enqueue( 'bulk_cover', array( 'cursor' => 0, 'batch_size' => 50 ), array( 'unique_key' => 'bulk-cover-full', 'priority' => 20 ) );
		WPS_Task_Store::enqueue( 'missing_scan', array( 'cursor' => 0, 'batch_size' => 100 ), array( 'unique_key' => 'missing-scan-full', 'priority' => 10 ) );
		WPS_Task_Store::complete( $task['id'], max( 1, absint( $task['progress'] ) + 1 ), max( 1, absint( $task['total'] ) ), __( 'Rollback point created and rebuild jobs added to the queue.', 'wpst' ) );
	}

	/** Match one post. */
	private static function process_search_match( array $task ) {
		$post_id = absint( $task['payload']['post_id'] ?? 0 );
		if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) throw new RuntimeException( __( 'The requested post was not found.', 'wpst' ) );
		$matched = WPS_Bulk_Cover_Generator::set_featured_from_local( $post_id );
		WPS_Task_Store::complete( $task['id'], 1, 1, $matched ? __( 'Cover matched and assigned.', 'wpst' ) : __( 'No assignable local cover was found.', 'wpst' ) );
	}

	/** Rebuild local search index or duplicate report. */
	private static function process_search_index( array $task, $duplicates ) {
		$p = wp_parse_args( $task['payload'], array( 'cursor' => 0, 'batch_size' => 100, 'indexed' => 0, 'failed' => 0, 'external_sent' => 0, 'external_failed' => 0 ) );
		$r = $duplicates ? WPS_Duplicates::scan_batch( $p['batch_size'], $p['cursor'] ) : WPS_Search_Index::process_batch( $p['batch_size'], $p['cursor'] );
		$p['cursor'] = $r['next_cursor']; $p['indexed'] += $r['indexed']; $p['failed'] += $r['failed'];
		$p['external_sent'] += absint( $r['external']['sent'] ?? 0 ); $p['external_failed'] += absint( $r['external']['failed'] ?? 0 );
		$progress = absint( $task['progress'] ) + $r['processed'];
		$message  = sprintf( __( 'Indexed %1$d; failed %2$d.', 'wpst' ), $p['indexed'], $p['failed'] );
		if ( $r['done'] ) {
			if ( $duplicates ) {
				$report = WPS_Duplicates::report();
				$message = sprintf( __( 'Duplicate scan completed. %d exact duplicate posts found.', 'wpst' ), absint( $report['exact_count'] ?? 0 ) );
			} else {
				$message = __( 'Search index rebuild completed.', 'wpst' ) . ' ' . $message;
			}
			WPS_Task_Store::complete( $task['id'], $progress, $r['total'], $message );
		} else WPS_Task_Store::release( $task['id'], $p, $progress, $r['total'], $message, 1 );
	}

	/** Trash exact duplicates from latest report. */
	private static function process_duplicates_trash( array $task ) {
		$r = WPS_Duplicates::trash_batch( $task['payload'] );
		$message = sprintf( __( 'Trashed %1$d; skipped %2$d.', 'wpst' ), absint( $r['payload']['trashed'] ?? 0 ), absint( $r['payload']['skipped'] ?? 0 ) );
		self::finish_resumable_result( $task, $r, __( 'Exact duplicate cleanup completed.', 'wpst' ) . ' ' . $message, $message );
	}

	/** Video duration scan. */
	private static function process_video_metadata( array $task ) {
		$p = wp_parse_args( $task['payload'], array( 'cursor' => 0, 'batch_size' => 50, 'detected' => 0, 'unchanged' => 0, 'unavailable' => 0 ) );
		$r = WPS_Video_Metadata::duration_batch( $p['batch_size'], $p['cursor'] );
		$p['cursor'] = $r['next_cursor'];
		foreach ( array( 'detected', 'unchanged', 'unavailable' ) as $key ) $p[ $key ] += $r[ $key ];
		$progress = absint( $task['progress'] ) + $r['processed'];
		$message = sprintf( __( 'Detected %1$d; unchanged %2$d; unavailable %3$d.', 'wpst' ), $p['detected'], $p['unchanged'], $p['unavailable'] );
		if ( $r['done'] ) WPS_Task_Store::complete( $task['id'], $progress, $r['total'], __( 'Video metadata scan completed.', 'wpst' ) . ' ' . $message );
		else WPS_Task_Store::release( $task['id'], $p, $progress, $r['total'], $message, 1 );
	}

	/** Optional Player API scan, one small batch at a time. */
	private static function process_player_api_scan( array $task ) {
		$p = wp_parse_args( $task['payload'], array( 'cursor' => 0, 'batch_size' => 1, 'updated' => 0, 'unavailable' => 0 ) );
		$r = WPS_Video_Metadata::player_api_batch( $p['batch_size'], $p['cursor'] );
		if ( is_wp_error( $r ) ) {
			WPS_Task_Store::complete( $task['id'], absint( $task['progress'] ), max( 1, absint( $task['total'] ) ), $r->get_error_message() );
			return;
		}
		$p['cursor'] = $r['next_cursor']; $p['updated'] += $r['updated']; $p['unavailable'] += $r['unavailable'];
		$progress = absint( $task['progress'] ) + $r['processed'];
		$message = sprintf( __( 'Updated %1$d; unavailable %2$d.', 'wpst' ), $p['updated'], $p['unavailable'] );
		if ( $r['done'] ) WPS_Task_Store::complete( $task['id'], $progress, $r['total'], __( 'Player API synchronization completed.', 'wpst' ) . ' ' . $message );
		else WPS_Task_Store::release( $task['id'], $p, $progress, $r['total'], $message, 2 );
	}

	/** Test Player API for one post. */
	private static function process_player_api_test( array $task ) {
		$result = WPS_Video_Metadata::test_player_api( absint( $task['payload']['post_id'] ?? 0 ) );
		if ( is_wp_error( $result ) ) {
			WPS_Task_Store::complete( $task['id'], 1, 1, $result->get_error_message() );
			return;
		}
		WPS_Task_Store::complete( $task['id'], 1, 1, sprintf( __( 'Player API test succeeded for post #%1$d. Duration: %2$s', 'wpst' ), $result['post_id'], $result['duration'] ? $result['duration'] : __( 'not provided', 'wpst' ) ) );
	}

	/** WebP derivative conversion. */
	private static function process_webp( array $task ) {
		$p = wp_parse_args( $task['payload'], array( 'cursor' => 0, 'batch_size' => 25, 'converted' => 0, 'skipped' => 0, 'failed' => 0, 'reasons' => array() ) );
		$r = WPS_WebP_Converter::process_batch( $p['batch_size'], $p['cursor'] );
		$p['cursor'] = $r['next_cursor'];
		foreach ( array( 'converted', 'skipped', 'failed' ) as $key ) $p[ $key ] += $r[ $key ];
		foreach ( $r['reasons'] as $key => $amount ) $p['reasons'][ $key ] = absint( $p['reasons'][ $key ] ?? 0 ) + absint( $amount );
		$progress = absint( $task['progress'] ) + $r['processed'];
		$message = sprintf( __( 'Converted %1$d; skipped %2$d; failed %3$d.', 'wpst' ), $p['converted'], $p['skipped'], $p['failed'] );
		if ( $r['done'] ) {
			update_option( WPS_WebP_Converter::REPORT_OPTION, array_merge( $p, array( 'completed_at' => current_time( 'mysql', true ) ) ), false );
			WPS_Task_Store::complete( $task['id'], $progress, $r['total'], __( 'WebP conversion completed.', 'wpst' ) . ' ' . $message );
		} else WPS_Task_Store::release( $task['id'], $p, $progress, $r['total'], $message, 1 );
	}

	/** Cleanup dry scan. */
	private static function process_cleanup_scan( array $task ) {
		if ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) {
			WPS_Task_Store::complete( $task['id'], 1, 1, __( 'Database Cleanup ownership moved to WP MY BOSS; this legacy task was closed safely.', 'wpst' ) );
			return;
		}
		$report = WPS_Cleanup::dry_scan();
		WPS_Task_Store::complete( $task['id'], 1, 1, sprintf( __( 'Database cleanup scan completed. %d records are eligible.', 'wpst' ), absint( $report['total'] ?? 0 ) ) );
	}

	/** Safe cleanup batch. */
	private static function process_cleanup_run( array $task ) {
		if ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) {
			WPS_Task_Store::complete( $task['id'], 1, 1, __( 'Database Cleanup ownership moved to WP MY BOSS; this legacy task was closed safely.', 'wpst' ) );
			return;
		}
		$r = WPS_Cleanup::process_batch( $task['payload'] );
		self::finish_resumable_result( $task, $r, __( 'Safe database cleanup completed.', 'wpst' ), $r['message'] ?? __( 'Cleaning database safely.', 'wpst' ) );
	}

	/** Full SQL/theme backup batch. */
	private static function process_full_backup( array $task ) {
		if ( defined( 'WPMB_FULL_BACKUP_READY' ) && WPMB_FULL_BACKUP_READY ) {
			WPS_Task_Store::complete( $task['id'], 1, 1, __( 'Full Backup ownership moved to WP MY BOSS; this legacy task was closed safely.', 'wpst' ) );
			return;
		}
		$r = WPS_Full_Backup::process_batch( $task['payload'] );
		self::finish_resumable_result( $task, $r, __( 'Full backup completed.', 'wpst' ), is_wp_error( $r ) ? '' : ( $r['message'] ?? __( 'Creating full backup.', 'wpst' ) ) );
	}

	/** Generic continuation helper for result payload APIs. */
	private static function finish_resumable_result( array $task, $result, $complete_message, $working_message ) {
		if ( is_wp_error( $result ) ) throw new RuntimeException( $result->get_error_message() );
		$progress = absint( $task['progress'] ) + absint( $result['processed'] ?? 0 );
		$total    = max( absint( $result['total'] ?? 0 ), $progress, 1 );
		if ( ! empty( $result['done'] ) ) WPS_Task_Store::complete( $task['id'], $progress, $total, trim( $complete_message ) );
		else WPS_Task_Store::release( $task['id'], is_array( $result['payload'] ?? null ) ? $result['payload'] : $task['payload'], $progress, $total, sanitize_text_field( $working_message ), 1 );
	}

	/** Add a task from the authenticated control center. */
	public static function ajax_enqueue_task() {
		self::authorize_ajax();
		$type = isset( $_POST['task_type'] ) ? sanitize_key( wp_unslash( $_POST['task_type'] ) ) : '';
		if ( ! in_array( $type, self::allowed_types(), true ) ) wp_send_json_error( array( 'message' => __( 'Invalid task type.', 'wpst' ) ), 400 );
		$payload = self::initial_payload( $type );
		$args    = array( 'unique_key' => $type . '-manual' );
		if ( in_array( $type, array( 'search_match', 'player_api_test' ), true ) ) {
			$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
			if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) wp_send_json_error( array( 'message' => __( 'Enter a valid Post ID.', 'wpst' ) ), 400 );
			$payload = array( 'post_id' => $post_id );
			$args['unique_key'] = $type . '-' . $post_id;
		}
		$task_id = WPS_Task_Store::enqueue( $type, $payload, $args );
		if ( is_wp_error( $task_id ) ) wp_send_json_error( array( 'message' => $task_id->get_error_message() ), 500 );
		wp_send_json_success( array( 'message' => __( 'Task added to the queue.', 'wpst' ), 'task_id' => $task_id, 'status' => self::status_payload() ) );
	}

	/** Initial payload by task. */
	private static function initial_payload( $type ) {
		if ( in_array( $type, array( 'cover_dry_run', 'missing_scan', 'auto_media', 'rebuild_index', 'duplicates_scan' ), true ) ) return array( 'cursor' => 0, 'batch_size' => 100 );
		if ( in_array( $type, array( 'bulk_cover', 'self_heal', 'video_metadata' ), true ) ) return array( 'cursor' => 0, 'batch_size' => 50 );
		if ( 'player_api_scan' === $type ) return array( 'cursor' => 0, 'batch_size' => 1 );
		if ( 'webp_convert' === $type ) return array( 'cursor' => 0, 'batch_size' => 25, 'reasons' => array() );
		if ( 'media_backup' === $type ) return array( 'cursor' => 0, 'batch_size' => 100 );
		if ( 'media_rollback' === $type ) return array( 'offset' => 0, 'batch_size' => 100 );
		if ( 'rebuild' === $type ) return array( 'stage' => 'backup', 'backup' => array() );
		return array();
	}

	public static function ajax_run_queue() { self::authorize_ajax(); wp_send_json_success( array( 'worker' => self::process_queue( 2, 12 ), 'status' => self::status_payload() ) ); }
	public static function ajax_queue_status() { self::authorize_ajax(); wp_send_json_success( self::status_payload() ); }
	public static function ajax_cancel_queue() { self::authorize_ajax(); WPS_Task_Store::cancel_all(); wp_send_json_success( self::status_payload() ); }
	public static function ajax_cleanup_queue() { self::authorize_ajax(); $removed = WPS_Task_Store::cleanup( 7 ); wp_send_json_success( array( 'removed' => absint( $removed ), 'status' => self::status_payload() ) ); }

	/** Shared dashboard status. */
	public static function status_payload() {
		$tasks = WPS_Task_Store::recent( 50 );
		foreach ( $tasks as &$task ) {
			$task['percent'] = $task['total'] ? min( 100, round( ( $task['progress'] / $task['total'] ) * 100 ) ) : ( 'completed' === $task['status'] ? 100 : 0 );
			unset( $task['payload'] );
		}
		unset( $task );
		return array( 'counts' => WPS_Task_Store::counts(), 'media' => WPS_Task_Store::media_counts(), 'progress' => WPS_Task_Store::progress_summary(), 'tasks' => $tasks, 'cron' => (bool) wp_next_scheduled( self::CRON_HOOK ), 'schema' => WPS_Task_Store::is_ready( true ) );
	}

	private static function authorize_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'You are not allowed to use this tool.', 'wpst' ) ), 403 );
		check_ajax_referer( self::AJAX_NONCE, 'nonce' );
	}

	private static function acquire_lock() {
		$token = wp_generate_uuid4();
		$value = wp_json_encode( array( 'token' => $token, 'time' => time() ) );
		if ( add_option( self::LOCK_OPTION, $value, '', false ) ) return $token;
		$current = json_decode( (string) get_option( self::LOCK_OPTION, '' ), true );
		if ( ! is_array( $current ) || empty( $current['time'] ) || ( time() - absint( $current['time'] ) ) > 5 * MINUTE_IN_SECONDS ) {
			delete_option( self::LOCK_OPTION );
			if ( add_option( self::LOCK_OPTION, $value, '', false ) ) return $token;
		}
		return false;
	}

	private static function release_lock( $token ) {
		$current = json_decode( (string) get_option( self::LOCK_OPTION, '' ), true );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) delete_option( self::LOCK_OPTION );
	}
}

if ( ! function_exists( 'av_add_task' ) ) {
	/**
	 * Add a backward-compatible framework task.
	 *
	 * @param string $type Task type.
	 * @param array  $data Task payload.
	 * @return int|WP_Error
	 */
	function av_add_task( $type, $data = array() ) {
		return WPS_Task_Store::enqueue( $type, is_array( $data ) ? $data : array() );
	}
}

if ( ! function_exists( 'av_run_task_queue' ) ) {
	/**
	 * Run a limited number of queued tasks.
	 *
	 * @param int $limit Maximum tasks to process.
	 * @return array
	 */
	function av_run_task_queue( $limit = 5 ) {
		return WPS_Task_Runner::process_queue( $limit );
	}
}

if ( ! function_exists( 'av_get_task_progress' ) ) {
	/**
	 * Return aggregate queue progress.
	 *
	 * @return array
	 */
	function av_get_task_progress() {
		return class_exists( 'WPS_Task_Store' )
			? WPS_Task_Store::progress_summary()
			: array(
				'total'   => 0,
				'done'    => 0,
				'percent' => 0,
			);
	}
}
