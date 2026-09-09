<?php
/**
 * Guarded database dry-scan and resumable safe cleanup.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Cleanup {
	const REPORT_OPTION = 'wps_cleanup_report';

	/** Count records covered by the conservative cleanup policy. */
	public static function dry_scan() {
		global $wpdb;
		$before = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		$report = array(
			'generated_at'       => current_time( 'mysql', true ),
			'trash_posts'        => absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_modified_gmt < %s", $before ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			'revisions'          => absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_modified_gmt < %s", $before ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			'spam_comments'      => absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash') AND comment_date_gmt < %s", $before ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			'expired_transients' => self::count_expired_transients(),
			'orphan_postmeta'    => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			'duplicate_postmeta' => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} a INNER JOIN {$wpdb->postmeta} b ON a.post_id=b.post_id AND a.meta_key=b.meta_key AND a.meta_value=b.meta_value AND a.meta_id>b.meta_id" ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);
		$report['total'] = array_sum( array_intersect_key( $report, array_flip( array( 'trash_posts', 'revisions', 'spam_comments', 'expired_transients', 'orphan_postmeta', 'duplicate_postmeta' ) ) ) );
		update_option( self::REPORT_OPTION, $report, false );
		return $report;
	}

	/** Process one cleanup stage batch. */
	public static function process_batch( array $payload ) {
		$stages  = array( 'trash_posts', 'revisions', 'spam_comments', 'expired_transients', 'orphan_postmeta', 'duplicate_postmeta' );
		$payload = wp_parse_args(
			$payload,
			array(
				'stage'   => 0,
				'deleted' => array(),
			)
		);
		$stage_index = min( count( $stages ), max( 0, absint( $payload['stage'] ) ) );
		if ( $stage_index >= count( $stages ) ) {
			return array( 'payload' => $payload, 'processed' => 0, 'done' => true, 'message' => __( 'Safe cleanup completed.', 'wpst' ) );
		}
		$stage  = $stages[ $stage_index ];
		$result = call_user_func( array( __CLASS__, 'cleanup_' . $stage ) );
		$amount = absint( $result['deleted'] ?? 0 );
		$payload['deleted'][ $stage ] = absint( $payload['deleted'][ $stage ] ?? 0 ) + $amount;
		if ( ! empty( $result['done'] ) ) {
			$payload['stage'] = $stage_index + 1;
		}
		$done = absint( $payload['stage'] ) >= count( $stages );
		if ( $done ) {
			update_option(
				self::REPORT_OPTION,
				array_merge(
					self::report(),
					array(
						'last_cleanup_at' => current_time( 'mysql', true ),
						'last_deleted'    => $payload['deleted'],
					)
				),
				false
			);
		}
		return array(
			'payload'   => $payload,
			'processed' => max( 1, $amount ),
			'done'      => $done,
			'message'   => sprintf( __( '%1$s: removed %2$d.', 'wpst' ), ucwords( str_replace( '_', ' ', $stage ) ), $amount ),
		);
	}

	/** Latest cleanup report. */
	public static function report() {
		$report = get_option( self::REPORT_OPTION, array() );
		return is_array( $report ) ? $report : array();
	}

	/** Permanently remove old trash posts through WordPress APIs. */
	private static function cleanup_trash_posts() {
		global $wpdb;
		$before = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status='trash' AND post_modified_gmt < %s ORDER BY ID ASC LIMIT 25", $before ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$deleted++;
			}
		}
		return array( 'deleted' => $deleted, 'done' => count( $ids ) < 25 );
	}

	/** Permanently remove old revisions. */
	private static function cleanup_revisions() {
		global $wpdb;
		$before = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='revision' AND post_modified_gmt < %s ORDER BY ID ASC LIMIT 50", $before ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$deleted++;
			}
		}
		return array( 'deleted' => $deleted, 'done' => count( $ids ) < 50 );
	}

	/** Permanently remove old spam/trash comments. */
	private static function cleanup_spam_comments() {
		global $wpdb;
		$before = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash') AND comment_date_gmt < %s ORDER BY comment_ID ASC LIMIT 100", $before ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_comment( $id, true ) ) {
				$deleted++;
			}
		}
		return array( 'deleted' => $deleted, 'done' => count( $ids ) < 100 );
	}

	/** Delete expired single-site transients and timeout pairs. */
	private static function cleanup_expired_transients() {
		global $wpdb;
		$prefix = $wpdb->esc_like( '_transient_timeout_' ) . '%';
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d ORDER BY option_id ASC LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$prefix,
				time()
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = 0;
		foreach ( (array) $rows as $row ) {
			$timeout_name = (string) $row['option_name'];
			$value_name   = str_replace( '_transient_timeout_', '_transient_', $timeout_name );
			$deleted     += delete_option( $timeout_name ) ? 1 : 0;
			delete_option( $value_name );
		}
		return array( 'deleted' => $deleted, 'done' => count( $rows ) < 100 );
	}

	/** Delete orphan postmeta rows in bounded ID lists. */
	private static function cleanup_orphan_postmeta() {
		global $wpdb;
		$ids = array_map( 'absint', (array) $wpdb->get_col( "SELECT pm.meta_id FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.ID IS NULL ORDER BY pm.meta_id ASC LIMIT 500" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = self::delete_meta_ids( $ids );
		return array( 'deleted' => $deleted, 'done' => count( $ids ) < 500 );
	}

	/** Delete exact duplicate postmeta rows, keeping the oldest meta_id. */
	private static function cleanup_duplicate_postmeta() {
		global $wpdb;
		$ids = array_map( 'absint', (array) $wpdb->get_col( "SELECT a.meta_id FROM {$wpdb->postmeta} a INNER JOIN {$wpdb->postmeta} b ON a.post_id=b.post_id AND a.meta_key=b.meta_key AND a.meta_value=b.meta_value AND a.meta_id>b.meta_id ORDER BY a.meta_id ASC LIMIT 500" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = self::delete_meta_ids( $ids );
		return array( 'deleted' => $deleted, 'done' => count( $ids ) < 500 );
	}

	/** Delete a bounded list of postmeta IDs. */
	private static function delete_meta_ids( array $ids ) {
		if ( ! $ids ) {
			return 0;
		}
		global $wpdb;
		$ids = array_slice( array_unique( array_filter( array_map( 'absint', $ids ) ) ), 0, 500 );
		if ( ! $ids ) {
			return 0;
		}
		return absint( $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN (" . implode( ',', $ids ) . ')' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/** Count expired transient timeout rows. */
	private static function count_expired_transients() {
		global $wpdb;
		$prefix = $wpdb->esc_like( '_transient_timeout_' ) . '%';
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $prefix, time() ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
