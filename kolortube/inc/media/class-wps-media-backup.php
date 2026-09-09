<?php
/**
 * Resumable featured-image and media-mapping backup/rollback.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Media_Backup {
	const LATEST_OPTION = 'wps_latest_media_backup';

	/** Create or continue a media snapshot batch. */
	public static function backup_batch( array $payload ) {
		$payload = wp_parse_args(
			$payload,
			array(
				'cursor'     => 0,
				'batch_size' => 100,
				'path'       => '',
				'filename'   => '',
				'records'    => 0,
			)
		);
		$payload['batch_size'] = min( 250, max( 1, absint( $payload['batch_size'] ) ) );

		if ( '' === $payload['path'] ) {
			$directory = WPS_Private_Storage::directory( 'media-backups' );
			if ( is_wp_error( $directory ) ) {
				return $directory;
			}
			$payload['filename'] = 'media-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 12, false, false ) . '.ndjson';
			$payload['path']     = trailingslashit( $directory ) . $payload['filename'];
			$manifest = array(
				'type'              => 'manifest',
				'framework_version' => defined( 'WPS_FRAMEWORK_VERSION' ) ? WPS_FRAMEWORK_VERSION : '',
				'site_url'          => home_url( '/' ),
				'created_at'        => gmdate( 'c' ),
				'fields'            => array( '_thumbnail_id', '_av_auto_media', 'thumb', '_wps_smart_cover_url' ),
			);
			if ( ! WPS_Private_Storage::atomic_write( $payload['path'], wp_json_encode( $manifest ) . "\n" ) ) {
				return new WP_Error( 'wps_media_backup_create_failed', __( 'Could not create the protected media backup file.', 'wpst' ) );
			}
		}

		if ( ! WPS_Private_Storage::is_safe_path( $payload['path'] ) || ! is_writable( $payload['path'] ) ) {
			return new WP_Error( 'wps_media_backup_unwritable', __( 'The media backup file is not writable.', 'wpst' ) );
		}

		$ids  = self::post_ids_after( $payload['cursor'], $payload['batch_size'] );
		$rows = '';
		foreach ( $ids as $post_id ) {
			$record = array(
				'type'    => 'post',
				'post_id' => $post_id,
				'meta'    => array(
					'_thumbnail_id'         => self::meta_record( $post_id, '_thumbnail_id' ),
					'_av_auto_media'        => self::meta_record( $post_id, '_av_auto_media' ),
					'thumb'                 => self::meta_record( $post_id, 'thumb' ),
					'_wps_smart_cover_url' => self::meta_record( $post_id, '_wps_smart_cover_url' ),
				),
			);
			$rows .= wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
			$payload['cursor']  = max( absint( $payload['cursor'] ), $post_id );
			$payload['records'] = absint( $payload['records'] ) + 1;
		}

		if ( '' !== $rows && false === file_put_contents( $payload['path'], $rows, FILE_APPEND | LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error( 'wps_media_backup_write_failed', __( 'Could not append to the media backup file.', 'wpst' ) );
		}

		$done = count( $ids ) < $payload['batch_size'];
		if ( $done ) {
			$latest = array(
				'created_at' => current_time( 'mysql', true ),
				'created_iso'=> gmdate( 'c' ),
				'path'       => $payload['path'],
				'filename'   => $payload['filename'],
				'records'    => absint( $payload['records'] ),
				'size'       => file_exists( $payload['path'] ) ? absint( filesize( $payload['path'] ) ) : 0,
			);
			update_option( self::LATEST_OPTION, $latest, false );
		}

		return array(
			'payload'   => $payload,
			'processed' => count( $ids ),
			'total'     => self::published_count(),
			'done'      => $done,
		);
	}

	/** Restore a protected NDJSON media snapshot in bounded batches. */
	public static function rollback_batch( array $payload ) {
		$latest  = self::latest();
		$payload = wp_parse_args(
			$payload,
			array(
				'path'       => isset( $latest['path'] ) ? $latest['path'] : '',
				'offset'     => 0,
				'batch_size' => 100,
				'restored'   => 0,
				'skipped'    => 0,
			)
		);
		$path = (string) $payload['path'];
		if ( '' === $path || ! WPS_Private_Storage::is_safe_path( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'wps_media_backup_missing', __( 'No readable protected media backup is available.', 'wpst' ) );
		}

		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'wps_media_backup_open_failed', __( 'Could not open the media backup.', 'wpst' ) );
		}
		$offset = max( 0, absint( $payload['offset'] ) );
		if ( $offset > 0 ) {
			fseek( $handle, $offset );
		}
		$processed  = 0;
		$batch_size = min( 250, max( 1, absint( $payload['batch_size'] ) ) );

		while ( $processed < $batch_size && ! feof( $handle ) ) {
			$line = fgets( $handle );
			if ( false === $line ) {
				break;
			}
			$payload['offset'] = ftell( $handle );
			$record = json_decode( trim( $line ), true );
			if ( ! is_array( $record ) || 'post' !== ( $record['type'] ?? '' ) ) {
				continue;
			}
			$post_id = absint( $record['post_id'] ?? 0 );
			if ( ! $post_id || ! get_post( $post_id ) ) {
				$payload['skipped']++;
				$processed++;
				continue;
			}
			foreach ( array( '_thumbnail_id', '_av_auto_media', 'thumb', '_wps_smart_cover_url' ) as $key ) {
				self::restore_meta_record( $post_id, $key, $record['meta'][ $key ] ?? array() );
			}
			clean_post_cache( $post_id );
			$payload['restored']++;
			$processed++;
		}
		$done = feof( $handle );
		fclose( $handle );

		return array(
			'payload'   => $payload,
			'processed' => $processed,
			'total'     => absint( $latest['records'] ?? 0 ),
			'done'      => $done,
		);
	}

	/** Return latest valid snapshot metadata. */
	public static function latest() {
		$value = get_option( self::LATEST_OPTION, array() );
		if ( ! is_array( $value ) || empty( $value['path'] ) || ! WPS_Private_Storage::is_safe_path( $value['path'] ) || ! is_readable( $value['path'] ) ) {
			return array();
		}
		$value['size'] = file_exists( $value['path'] ) ? absint( filesize( $value['path'] ) ) : 0;
		return $value;
	}

	/** Determine whether a restorable snapshot exists. */
	public static function has_backup() {
		return ! empty( self::latest() );
	}

	/** Capture whether a meta key existed and its scalar value. */
	private static function meta_record( $post_id, $key ) {
		return array(
			'exists' => metadata_exists( 'post', $post_id, $key ),
			'value'  => get_post_meta( $post_id, $key, true ),
		);
	}

	/** Restore one recorded metadata value. */
	private static function restore_meta_record( $post_id, $key, $record ) {
		$exists = is_array( $record ) && ! empty( $record['exists'] );
		if ( ! $exists ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		$value = is_array( $record ) && array_key_exists( 'value', $record ) ? $record['value'] : '';
		if ( in_array( $key, array( '_thumbnail_id', '_av_auto_media' ), true ) ) {
			$value = absint( $value );
		} else {
			$value = is_scalar( $value ) ? (string) $value : '';
		}
		update_post_meta( $post_id, $key, $value );
	}

	/** Stable post cursor query. */
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

	/** Published post count. */
	private static function published_count() {
		$count = wp_count_posts( 'post' );
		return isset( $count->publish ) ? absint( $count->publish ) : 0;
	}
}
