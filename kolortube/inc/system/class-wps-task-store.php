<?php
/**
 * Persistent task and media-index storage.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Task_Store {
	/** @var bool|null */
	private static $tables_ready = null;

	/** @var bool */
	private static $stale_recovered = false;

	/** Return the queue table name. */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wps_tasks';
	}

	/** Return the media index table name. */
	public static function media_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wps_media_index';
	}

	/** Reset the per-request schema cache after dbDelta. */
	public static function reset_schema_cache() {
		self::$tables_ready = null;
	}

	/** Determine whether both framework tables exist. */
	public static function is_ready( $attempt_install = false ) {
		if ( true === self::$tables_ready ) {
			return true;
		}

		if ( $attempt_install && class_exists( 'WPS_Installer' ) && ( is_admin() || wp_doing_cron() ) ) {
			WPS_Installer::maybe_install();
		}

		global $wpdb;
		$tasks = self::table_name();
		$media = self::media_table_name();
		$found_tasks = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tasks ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$found_media = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $media ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		self::$tables_ready = ( $tasks === $found_tasks && $media === $found_media );
		return self::$tables_ready;
	}

	/** Add a queue task, deduplicating active jobs when a unique key is supplied. */
	public static function enqueue( $type, array $payload = array(), array $args = array() ) {
		global $wpdb;

		if ( ! self::is_ready( true ) ) {
			return new WP_Error( 'wps_queue_unavailable', __( 'The framework task database is not available yet.', 'wpst' ) );
		}

		$type = sanitize_key( $type );
		if ( ! class_exists( 'WPS_Task_Runner' ) || ! in_array( $type, WPS_Task_Runner::allowed_types(), true ) ) {
			return new WP_Error( 'wps_invalid_task', __( 'Unknown framework task type.', 'wpst' ) );
		}

		$defaults = array(
			'priority'     => 10,
			'max_attempts' => 3,
			'unique_key'   => '',
			'delay'        => 0,
			'total'        => 0,
		);
		$args   = wp_parse_args( $args, $defaults );
		$unique = substr( sanitize_text_field( (string) $args['unique_key'] ), 0, 191 );
		$table  = self::table_name();

		if ( '' !== $unique ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE unique_key = %s AND status IN ('pending','running') ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$unique
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $existing ) {
				return absint( $existing );
			}
		}

		$now       = current_time( 'mysql', true );
		$available = gmdate( 'Y-m-d H:i:s', time() + max( 0, absint( $args['delay'] ) ) );
		$inserted  = $wpdb->insert(
			$table,
			array(
				'type'         => $type,
				'payload'      => wp_json_encode( $payload ),
				'status'       => 'pending',
				'priority'     => absint( $args['priority'] ),
				'progress'     => 0,
				'total'        => absint( $args['total'] ),
				'attempts'     => 0,
				'max_attempts' => max( 1, absint( $args['max_attempts'] ) ),
				'message'      => '',
				'unique_key'   => $unique,
				'available_at' => $available,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( false === $inserted ) {
			return new WP_Error( 'wps_queue_insert_failed', __( 'Could not add task to the queue.', 'wpst' ) );
		}

		$task_id = absint( $wpdb->insert_id );
		WPS_Task_Runner::kick();
		do_action( 'wps_task_enqueued', $task_id, $type, $payload );
		return $task_id;
	}

	/** Atomically claim the next available task. */
	public static function claim_next() {
		if ( ! self::is_ready( true ) ) {
			return null;
		}
		if ( ! self::$stale_recovered ) {
			self::recover_stale();
			self::$stale_recovered = true;
		}

		global $wpdb;
		$table = self::table_name();
		$now   = current_time( 'mysql', true );

		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE status = 'pending' AND available_at <= %s ORDER BY priority DESC, id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$now
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( ! $id ) {
				return null;
			}

			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = 'running', locked_at = %s, updated_at = %s WHERE id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$now,
					$now,
					$id
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( 1 === (int) $updated ) {
				return self::get( $id );
			}
		}
		return null;
	}

	/** Return abandoned running jobs to pending state. */
	public static function recover_stale( $age = 10 * MINUTE_IN_SECONDS ) {
		if ( ! self::is_ready() ) {
			return 0;
		}
		global $wpdb;
		$table  = self::table_name();
		$before = gmdate( 'Y-m-d H:i:s', time() - max( MINUTE_IN_SECONDS, absint( $age ) ) );
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'pending', locked_at = NULL, available_at = %s, updated_at = %s, message = %s WHERE status = 'running' AND locked_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql', true ),
				current_time( 'mysql', true ),
				__( 'Recovered after an interrupted worker.', 'wpst' ),
				$before
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/** Fetch a task by ID. */
	public static function get( $task_id ) {
		if ( ! self::is_ready() ) {
			return null;
		}
		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $task_id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $row ? self::normalize_task( $row ) : null;
	}

	/** Return recent tasks for the dashboard. */
	public static function recent( $limit = 20 ) {
		if ( ! self::is_ready( true ) ) {
			return array();
		}
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", min( 100, max( 1, absint( $limit ) ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return array_map( array( __CLASS__, 'normalize_task' ), is_array( $rows ) ? $rows : array() );
	}

	/** Queue status totals. */
	public static function counts() {
		$out = array( 'pending' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0, 'cancelled' => 0 );
		if ( ! self::is_ready( true ) ) {
			return $out;
		}
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS amount FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['status'] ] ) ) {
				$out[ $row['status'] ] = absint( $row['amount'] );
			}
		}
		return $out;
	}

	/** Aggregate active/recent progress without limiting calculation to the UI list. */
	public static function progress_summary() {
		$out = array( 'total' => 0, 'done' => 0, 'percent' => 0 );
		if ( ! self::is_ready( true ) ) {
			return $out;
		}
		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row(
			"SELECT SUM(CASE WHEN total > 0 THEN total ELSE 1 END) AS total_units, SUM(CASE WHEN status = 'completed' THEN CASE WHEN total > 0 THEN total ELSE 1 END ELSE LEAST(progress, CASE WHEN total > 0 THEN total ELSE 1 END) END) AS done_units FROM {$table} WHERE status IN ('pending','running','completed') AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			ARRAY_A
		);
		$out['total']   = absint( $row['total_units'] ?? 0 );
		$out['done']    = absint( $row['done_units'] ?? 0 );
		$out['percent'] = $out['total'] ? min( 100, round( ( $out['done'] / $out['total'] ) * 100 ) ) : 0;
		return $out;
	}

	/** Requeue a partially completed task. */
	public static function release( $task_id, array $payload, $progress, $total, $message = '', $delay = 1 ) {
		return self::update(
			$task_id,
			array(
				'payload'      => wp_json_encode( $payload ),
				'status'       => 'pending',
				'progress'     => absint( $progress ),
				'total'        => absint( $total ),
				'message'      => sanitize_text_field( $message ),
				'available_at' => gmdate( 'Y-m-d H:i:s', time() + max( 0, absint( $delay ) ) ),
				'locked_at'    => null,
				'updated_at'   => current_time( 'mysql', true ),
			)
		);
	}

	/** Complete a task. */
	public static function complete( $task_id, $progress = 0, $total = 0, $message = '' ) {
		$task = self::get( $task_id );
		$now  = current_time( 'mysql', true );
		$done = self::update(
			$task_id,
			array(
				'status'       => 'completed',
				'progress'     => absint( $progress ),
				'total'        => absint( $total ),
				'message'      => sanitize_text_field( $message ),
				'locked_at'    => null,
				'updated_at'   => $now,
				'completed_at' => $now,
			)
		);
		if ( false !== $done ) {
			if ( ! is_array( $task ) ) {
				$task = array( 'id' => absint( $task_id ), 'type' => '' );
			}
			do_action( 'wps_task_completed', $task, sanitize_text_field( $message ) );
		}
		return $done;
	}

	/** Mark a task failed or return it to the queue for retry. */
	public static function fail_or_retry( array $task, $message ) {
		$attempts = absint( $task['attempts'] ) + 1;
		if ( $attempts < absint( $task['max_attempts'] ) ) {
			return self::update(
				$task['id'],
				array(
					'payload'      => wp_json_encode( $task['payload'] ),
					'status'       => 'pending',
					'attempts'     => $attempts,
					'message'      => sanitize_text_field( $message ),
					'available_at' => gmdate( 'Y-m-d H:i:s', time() + min( 300, 15 * $attempts ) ),
					'locked_at'    => null,
					'updated_at'   => current_time( 'mysql', true ),
				)
			);
		}
		$failed = self::update(
			$task['id'],
			array(
				'status'     => 'failed',
				'attempts'   => $attempts,
				'message'    => sanitize_text_field( $message ),
				'locked_at'  => null,
				'updated_at' => current_time( 'mysql', true ),
			)
		);
		if ( false !== $failed ) {
			do_action( 'wps_task_terminal_failure', $task, sanitize_text_field( $message ) );
		}
		return $failed;
	}

	/** Cancel pending/running tasks. */
	public static function cancel_all() {
		if ( ! self::is_ready( true ) ) {
			return 0;
		}
		global $wpdb;
		$table = self::table_name();
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'cancelled', locked_at = NULL, updated_at = %s WHERE status IN ('pending','running')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql', true )
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/** Remove old terminal task history. */
	public static function cleanup( $days = 14 ) {
		if ( ! self::is_ready( true ) ) {
			return 0;
		}
		global $wpdb;
		$table  = self::table_name();
		$before = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, absint( $days ) ) * DAY_IN_SECONDS ) );
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN ('completed','cancelled','failed') AND updated_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$before
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/** Update the media scan index for one post. */
	public static function update_media_index( $post_id, $status, $attachment_id = 0, $cover_url = '', $source = '' ) {
		if ( ! self::is_ready( false ) ) {
			return false;
		}
		global $wpdb;
		return $wpdb->replace(
			self::media_table_name(),
			array(
				'post_id'       => absint( $post_id ),
				'cover_status'  => sanitize_key( $status ),
				'attachment_id' => absint( $attachment_id ),
				'cover_url'     => esc_url_raw( $cover_url ),
				'source'        => sanitize_key( $source ),
				'checked_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/** Get missing-cover rows. */
	public static function missing_covers( $limit = 50 ) {
		if ( ! self::is_ready( true ) ) {
			return array();
		}
		global $wpdb;
		$table = self::media_table_name();
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, checked_at FROM {$table} WHERE cover_status = 'missing' ORDER BY checked_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				min( 200, max( 1, absint( $limit ) ) )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/** Count indexed media statuses. */
	public static function media_counts() {
		$out = array( 'found' => 0, 'missing' => 0, 'unknown' => 0 );
		if ( ! self::is_ready( true ) ) {
			return $out;
		}
		global $wpdb;
		$table = self::media_table_name();
		$rows  = $wpdb->get_results( "SELECT cover_status, COUNT(*) amount FROM {$table} GROUP BY cover_status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['cover_status'] ] ) ) {
				$out[ $row['cover_status'] ] = absint( $row['amount'] );
			}
		}
		return $out;
	}

	/** Low-level update helper. */
	private static function update( $task_id, array $data ) {
		if ( ! self::is_ready() ) {
			return false;
		}
		global $wpdb;
		$formats = array();
		foreach ( $data as $value ) {
			$formats[] = is_int( $value ) ? '%d' : '%s';
		}
		return $wpdb->update( self::table_name(), $data, array( 'id' => absint( $task_id ) ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/** Normalize database values. */
	private static function normalize_task( array $row ) {
		$row['id']           = absint( $row['id'] );
		$row['priority']     = absint( $row['priority'] );
		$row['progress']     = absint( $row['progress'] );
		$row['total']        = absint( $row['total'] );
		$row['attempts']     = absint( $row['attempts'] );
		$row['max_attempts'] = absint( $row['max_attempts'] );
		$decoded             = json_decode( (string) $row['payload'], true );
		$row['payload']      = is_array( $decoded ) ? $decoded : array();
		return $row;
	}
}
