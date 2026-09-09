<?php
/**
 * Conservative duplicate-post scanner and trash workflow.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Duplicates {
	const REPORT_OPTION = 'wps_duplicate_report';

	/** Index a post batch, then compile exact duplicate groups at completion. */
	public static function scan_batch( $limit = 100, $cursor = 0 ) {
		$result = WPS_Search_Index::process_batch( $limit, $cursor );
		if ( ! empty( $result['done'] ) ) {
			self::build_report();
		}
		return $result;
	}

	/** Build a report from normalized video/title/content hashes. */
	public static function build_report() {
		if ( ! WPS_Search_Index::table_exists() ) {
			return new WP_Error( 'wps_index_unavailable', __( 'The local search index is unavailable.', 'wpst' ) );
		}
		global $wpdb;
		$table  = WPS_Search_Index::table_name();
		$hashes = $wpdb->get_col( "SELECT video_hash FROM {$table} WHERE video_hash <> '' GROUP BY video_hash HAVING COUNT(*) > 1 ORDER BY MIN(post_id) ASC LIMIT 500" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$groups = array();
		$exact  = array();

		foreach ( (array) $hashes as $video_hash ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, post_title, video_url, title_hash, content_hash FROM {$table} WHERE video_hash = %s ORDER BY post_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$video_hash
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = array_values(
				array_filter(
					(array) $rows,
					static function ( $row ) {
						$post = get_post( absint( $row['post_id'] ?? 0 ) );
						return $post && 'post' === $post->post_type && 'publish' === $post->post_status;
					}
				)
			);
			if ( count( $rows ) < 2 ) {
				continue;
			}
			$canonical = $rows[0];
			$duplicates = array();
			foreach ( array_slice( $rows, 1 ) as $row ) {
				$is_exact = ( ! empty( $canonical['title_hash'] ) && hash_equals( (string) $canonical['title_hash'], (string) $row['title_hash'] ) )
					|| ( ! empty( $canonical['content_hash'] ) && hash_equals( (string) $canonical['content_hash'], (string) $row['content_hash'] ) );
				$duplicates[] = array(
					'post_id' => absint( $row['post_id'] ),
					'title'   => sanitize_text_field( $row['post_title'] ),
					'exact'   => $is_exact,
				);
				if ( $is_exact ) {
					$exact[] = absint( $row['post_id'] );
				}
			}
			$groups[] = array(
				'canonical_id'    => absint( $canonical['post_id'] ),
				'canonical_title' => sanitize_text_field( $canonical['post_title'] ),
				'video_url'       => esc_url_raw( $canonical['video_url'] ),
				'duplicates'      => $duplicates,
			);
		}

		$report = array(
			'generated_at'     => current_time( 'mysql', true ),
			'groups'           => $groups,
			'exact_duplicates' => array_values( array_unique( array_filter( array_map( 'absint', $exact ) ) ) ),
			'exact_count'      => count( array_unique( $exact ) ),
			'group_count'      => count( $groups ),
		);
		update_option( self::REPORT_OPTION, $report, false );
		return $report;
	}

	/** Move only exact duplicates from the latest report to Trash. */
	public static function trash_batch( array $payload ) {
		$report  = self::report();
		$ids     = isset( $report['exact_duplicates'] ) && is_array( $report['exact_duplicates'] ) ? array_values( array_map( 'absint', $report['exact_duplicates'] ) ) : array();
		$payload = wp_parse_args( $payload, array( 'offset' => 0, 'trashed' => 0, 'skipped' => 0, 'batch_size' => 25 ) );
		$offset  = absint( $payload['offset'] );
		$batch   = array_slice( $ids, $offset, min( 50, max( 1, absint( $payload['batch_size'] ) ) ) );

		foreach ( $batch as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
				$payload['skipped']++;
				continue;
			}
			if ( wp_trash_post( $post_id ) ) {
				$payload['trashed']++;
			} else {
				$payload['skipped']++;
			}
		}
		$payload['offset'] = $offset + count( $batch );
		$done = $payload['offset'] >= count( $ids );
		return array(
			'payload'   => $payload,
			'processed' => count( $batch ),
			'total'     => count( $ids ),
			'done'      => $done,
		);
	}

	/** Latest duplicate report. */
	public static function report() {
		$report = get_option( self::REPORT_OPTION, array() );
		return is_array( $report ) ? $report : array();
	}
}
