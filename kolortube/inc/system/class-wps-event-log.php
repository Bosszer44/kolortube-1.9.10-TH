<?php
/**
 * Persistent framework event log.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Event_Log {
	/** Return the event table name. */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wps_events';
	}

	/** Register queue lifecycle listeners and bounded cleanup. */
	public static function register() {
		if ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) { return; }
		add_action( 'wps_task_enqueued', array( __CLASS__, 'task_enqueued' ), 10, 3 );
		add_action( 'wps_task_completed', array( __CLASS__, 'task_completed' ), 10, 2 );
		add_action( 'wps_task_terminal_failure', array( __CLASS__, 'task_failed' ), 10, 2 );
		add_action( 'wps_daily_maintenance', array( __CLASS__, 'scheduled_cleanup' ) );
	}

	/** Record a queue insertion. */
	public static function task_enqueued( $task_id, $type, $payload = array() ) {
		self::log(
			'info',
			'task_enqueued',
			sprintf( 'Task #%1$d (%2$s) was queued.', absint( $task_id ), sanitize_key( $type ) ),
			array( 'task_id' => absint( $task_id ), 'type' => sanitize_key( $type ) )
		);
	}

	/** Record a completed queue task. */
	public static function task_completed( $task, $message ) {
		self::log(
			'success',
			'task_completed',
			(string) $message,
			array(
				'task_id' => isset( $task['id'] ) ? absint( $task['id'] ) : 0,
				'type'    => isset( $task['type'] ) ? sanitize_key( $task['type'] ) : '',
			)
		);
	}

	/** Record a terminal queue failure. */
	public static function task_failed( $task, $message ) {
		self::log(
			'error',
			'task_failed',
			(string) $message,
			array(
				'task_id' => isset( $task['id'] ) ? absint( $task['id'] ) : 0,
				'type'    => isset( $task['type'] ) ? sanitize_key( $task['type'] ) : '',
			)
		);
	}

	/**
	 * Store a sanitized event.
	 *
	 * @param string $level   info|success|warning|error.
	 * @param string $code    Stable machine-readable code.
	 * @param string $message Human-readable message.
	 * @param array  $context Optional non-secret context.
	 * @return bool
	 */
	public static function log( $level, $code, $message, array $context = array() ) {
		if ( ! class_exists( 'WPS_Installer' ) ) {
			return false;
		}

		global $wpdb;
		$table = self::table_name();
		if ( ! self::table_exists( $table ) ) {
			return false;
		}

		$allowed_levels = array( 'info', 'success', 'warning', 'error' );
		$level          = sanitize_key( $level );
		if ( ! in_array( $level, $allowed_levels, true ) ) {
			$level = 'info';
		}

		$context = self::sanitize_context( $context );
		$result  = $wpdb->insert(
			$table,
			array(
				'level'      => $level,
				'code'       => substr( sanitize_key( $code ), 0, 100 ),
				'message'    => substr( sanitize_text_field( (string) $message ), 0, 1000 ),
				'context'    => $context ? wp_json_encode( $context ) : '',
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return false !== $result;
	}

	/** Return recent events for the dashboard. */
	public static function recent( $limit = 30 ) {
		global $wpdb;
		$table = self::table_name();
		if ( ! self::table_exists( $table ) ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, level, code, message, context, created_at FROM {$table} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				min( 100, max( 1, absint( $limit ) ) )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $rows as &$row ) {
			$row['id']      = absint( $row['id'] );
			$row['context'] = json_decode( (string) $row['context'], true );
			if ( ! is_array( $row['context'] ) ) {
				$row['context'] = array();
			}
		}
		unset( $row );
		return (array) $rows;
	}

	/** Delete old event rows. */
	public static function cleanup( $days = 30 ) {
		global $wpdb;
		$table = self::table_name();
		if ( ! self::table_exists( $table ) ) {
			return 0;
		}
		$before = gmdate( 'Y-m-d H:i:s', time() - max( DAY_IN_SECONDS, absint( $days ) * DAY_IN_SECONDS ) );
		return absint(
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$before
				)
			) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);
	}

	/** Daily cleanup callback. */
	public static function scheduled_cleanup() {
		self::cleanup( 30 );
	}

	/** Check one framework-owned table. */
	private static function table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $table === $found;
	}

	/** Remove secrets and deeply sanitize small diagnostic context. */
	private static function sanitize_context( array $context ) {
		$blocked = array( 'token', 'password', 'secret', 'authorization', 'service_account', 'private_key' );
		$out     = array();
		foreach ( array_slice( $context, 0, 30, true ) as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' === $key || in_array( $key, $blocked, true ) || preg_match( '/token|secret|password|key/i', $key ) ) {
				continue;
			}
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_scalar( $value ) ) {
				$out[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 500 );
			}
		}
		return $out;
	}
}
