<?php
/**
 * Resumable video metadata and optional Player API synchronization.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Video_Metadata {
	const DURATION_META = '_wps_video_duration';
	const API_CACHE_META = '_wps_player_api_cache';

	/** Scan a bounded post batch for duration metadata. */
	public static function duration_batch( $limit = 50, $cursor = 0 ) {
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$ids   = self::post_ids_after( $cursor, $limit );
		$out   = array(
			'processed'   => 0,
			'detected'    => 0,
			'unchanged'   => 0,
			'unavailable' => 0,
			'next_cursor' => absint( $cursor ),
			'done'        => false,
			'total'       => self::published_count(),
		);

		foreach ( $ids as $post_id ) {
			$out['processed']++;
			$out['next_cursor'] = max( $out['next_cursor'], $post_id );
			$current = self::existing_duration( $post_id );
			$found   = self::detect_duration( $post_id );
			if ( '' === $found ) {
				$out['unavailable']++;
				continue;
			}
			if ( $current === $found ) {
				$out['unchanged']++;
				continue;
			}
			update_post_meta( $post_id, self::DURATION_META, $found );
			$out['detected']++;
		}
		$out['done'] = count( $ids ) < $limit;
		return $out;
	}

	/** Synchronize one post per batch with the optional Player API. */
	public static function player_api_batch( $limit = 1, $cursor = 0 ) {
		$status = class_exists( 'WPS_Player' ) ? WPS_Player::api_status() : array();
		if ( empty( $status['enabled'] ) || empty( $status['configured'] ) ) {
			return new WP_Error( 'wps_player_api_unconfigured', __( 'Player API is disabled or has no valid endpoint. The original player remains active.', 'wpst' ) );
		}
		if ( ! empty( $status['circuit_open'] ) ) {
			return new WP_Error( 'wps_player_api_circuit', __( 'Player API circuit breaker is open. Wait before retrying.', 'wpst' ) );
		}

		$limit = min( 5, max( 1, absint( $limit ) ) );
		$ids   = self::post_ids_after( $cursor, $limit );
		$out   = array(
			'processed'   => 0,
			'updated'     => 0,
			'unavailable' => 0,
			'next_cursor' => absint( $cursor ),
			'done'        => false,
			'total'       => self::published_count(),
		);

		foreach ( $ids as $post_id ) {
			$out['processed']++;
			$out['next_cursor'] = max( $out['next_cursor'], $post_id );
			$source = class_exists( 'WPS_Search_Index' ) ? WPS_Search_Index::video_source_url( $post_id ) : '';
			if ( '' === $source ) {
				$out['unavailable']++;
				continue;
			}
			$response = WPS_Player::legacy_api_request_detailed( $source );
			if ( is_wp_error( $response ) ) {
				$out['unavailable']++;
				if ( WPS_Player::api_should_abort_batch( $response ) ) {
					return $response;
				}
				continue;
			}
			update_post_meta( $post_id, self::API_CACHE_META, $response );
			$duration = self::duration_from_api( $response );
			if ( '' !== $duration ) {
				update_post_meta( $post_id, self::DURATION_META, $duration );
			}
			$out['updated']++;
		}
		$out['done'] = count( $ids ) < $limit;
		return $out;
	}

	/** Test a single post without mutating the original player output. */
	public static function test_player_api( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
			return new WP_Error( 'wps_invalid_post', __( 'Enter a valid published Post ID.', 'wpst' ) );
		}
		$source = class_exists( 'WPS_Search_Index' ) ? WPS_Search_Index::video_source_url( $post_id ) : '';
		if ( '' === $source ) {
			return new WP_Error( 'wps_video_source_missing', __( 'No video source URL was found for this post.', 'wpst' ) );
		}
		$response = WPS_Player::legacy_api_request_detailed( $source );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array(
			'post_id'  => $post_id,
			'source'   => $source,
			'duration' => self::duration_from_api( $response ),
			'response' => $response,
		);
	}

	/** Return the framework duration, preserving legacy values first. */
	public static function display_duration( $post_id ) {
		$duration = self::existing_duration( $post_id );
		return '' !== $duration ? $duration : self::detect_duration( $post_id );
	}

	/** Detect duration from legacy meta, API cache, attachment metadata or local files. */
	public static function detect_duration( $post_id ) {
		$legacy = self::legacy_duration( $post_id );
		if ( '' !== $legacy ) {
			return $legacy;
		}
		$cache = get_post_meta( $post_id, self::API_CACHE_META, true );
		if ( is_array( $cache ) ) {
			$duration = self::duration_from_api( $cache );
			if ( '' !== $duration ) {
				return $duration;
			}
		}
		$attachments = get_children(
			array(
				'post_parent'    => absint( $post_id ),
				'post_type'      => 'attachment',
				'post_mime_type' => 'video',
				'numberposts'    => 5,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( (array) $attachments as $attachment_id ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			$duration = self::duration_from_attachment_metadata( $metadata );
			if ( '' !== $duration ) {
				return $duration;
			}
			$file = get_attached_file( $attachment_id );
			if ( $file && is_readable( $file ) && function_exists( 'wp_read_video_metadata' ) ) {
				$read = wp_read_video_metadata( $file );
				$duration = self::duration_from_attachment_metadata( $read );
				if ( '' !== $duration ) {
					return $duration;
				}
			}
		}
		return '';
	}

	/** Existing framework or legacy duration. */
	private static function existing_duration( $post_id ) {
		$framework = self::normalize_duration( get_post_meta( $post_id, self::DURATION_META, true ) );
		return '' !== $framework ? $framework : self::legacy_duration( $post_id );
	}

	/** Read common legacy duration fields without overwriting them. */
	private static function legacy_duration( $post_id ) {
		foreach ( array( 'duration', 'video_duration', 'wpst_video_duration', '_video_duration', 'length' ) as $key ) {
			$value = self::normalize_duration( get_post_meta( $post_id, $key, true ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	/** Extract duration from a remote API response. */
	private static function duration_from_api( array $response ) {
		$candidates = array(
			$response['duration'] ?? '',
			$response['length'] ?? '',
			$response['data']['duration'] ?? '',
			$response['video']['duration'] ?? '',
		);
		foreach ( $candidates as $candidate ) {
			$duration = self::normalize_duration( $candidate );
			if ( '' !== $duration ) {
				return $duration;
			}
		}
		return '';
	}

	/** Extract duration from WordPress media metadata. */
	private static function duration_from_attachment_metadata( $metadata ) {
		if ( ! is_array( $metadata ) ) {
			return '';
		}
		foreach ( array( 'length_formatted', 'length', 'duration' ) as $key ) {
			if ( isset( $metadata[ $key ] ) ) {
				$duration = self::normalize_duration( $metadata[ $key ] );
				if ( '' !== $duration ) {
					return $duration;
				}
			}
		}
		return '';
	}

	/** Normalize seconds or hh:mm:ss text. */
	private static function normalize_duration( $value ) {
		if ( is_numeric( $value ) ) {
			$seconds = absint( $value );
			if ( $seconds <= 0 ) {
				return '';
			}
			$hours = floor( $seconds / HOUR_IN_SECONDS );
			$mins  = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
			$secs  = $seconds % MINUTE_IN_SECONDS;
			return $hours > 0 ? sprintf( '%d:%02d:%02d', $hours, $mins, $secs ) : sprintf( '%02d:%02d', $mins, $secs );
		}
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( preg_match( '/^(?:\d{1,3}:)?[0-5]?\d:[0-5]\d$/', $value ) ) {
			return $value;
		}
		return '';
	}

	/** Cursor query. */
	private static function post_ids_after( $cursor, $limit ) {
		global $wpdb;
		return array_map(
			'absint',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					absint( $cursor ),
					absint( $limit )
				)
			) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);
	}

	/** Published count. */
	private static function published_count() {
		$count = wp_count_posts( 'post' );
		return isset( $count->publish ) ? absint( $count->publish ) : 0;
	}
}
