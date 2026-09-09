<?php
/**
 * Non-destructive resumable WebP derivative generator.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_WebP_Converter {
	const REPORT_OPTION = 'wps_webp_report';

	/** Convert one safe attachment batch while retaining originals. */
	public static function process_batch( $limit = 25, $cursor = 0 ) {
		$limit = min( 50, max( 1, absint( $limit ) ) );
		$ids   = self::attachment_ids_after( $cursor, $limit );
		$out   = array(
			'processed'   => 0,
			'converted'   => 0,
			'skipped'     => 0,
			'failed'      => 0,
			'reasons'     => array( 'already_webp' => 0, 'derivative_exists' => 0, 'unsupported' => 0, 'missing_file' => 0, 'editor_unavailable' => 0 ),
			'next_cursor' => absint( $cursor ),
			'done'        => false,
			'total'       => self::candidate_count(),
		);

		foreach ( $ids as $attachment_id ) {
			$out['processed']++;
			$out['next_cursor'] = max( $out['next_cursor'], $attachment_id );
			$result = self::convert_attachment( $attachment_id );
			if ( is_wp_error( $result ) ) {
				$code = $result->get_error_code();
				if ( isset( $out['reasons'][ $code ] ) ) {
					$out['reasons'][ $code ]++;
					$out['skipped']++;
				} else {
					$out['failed']++;
				}
				continue;
			}
			if ( ! empty( $result['converted'] ) ) {
				$out['converted']++;
			} else {
				$out['skipped']++;
			}
		}
		$out['done'] = count( $ids ) < $limit;
		if ( $out['done'] ) {
			update_option( self::REPORT_OPTION, array_merge( $out, array( 'completed_at' => current_time( 'mysql', true ) ) ), false );
		}
		return $out;
	}

	/** Convert one JPEG/PNG attachment to a linked derivative. */
	public static function convert_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$mime          = get_post_mime_type( $attachment_id );
		if ( 'image/webp' === $mime ) {
			return new WP_Error( 'already_webp', __( 'Attachment is already WebP.', 'wpst' ) );
		}
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			return new WP_Error( 'unsupported', __( 'Only JPEG and PNG attachments are eligible.', 'wpst' ) );
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_readable( $file ) ) {
			return new WP_Error( 'missing_file', __( 'The original attachment file is unavailable.', 'wpst' ) );
		}
		$destination = preg_replace( '/\.(?:jpe?g|png)$/i', '.webp', $file );
		if ( ! $destination || $destination === $file ) {
			return new WP_Error( 'unsupported', __( 'Could not determine a WebP destination.', 'wpst' ) );
		}
		if ( is_file( $destination ) && filesize( $destination ) > 0 ) {
			self::store_derivative_meta( $attachment_id, $destination );
			return new WP_Error( 'derivative_exists', __( 'A WebP derivative already exists.', 'wpst' ) );
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return new WP_Error( 'editor_unavailable', $editor->get_error_message() );
		}
		$saved = $editor->save( $destination, 'image/webp' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_file( $saved['path'] ) ) {
			return is_wp_error( $saved ) ? $saved : new WP_Error( 'webp_save_failed', __( 'The image editor did not create a WebP file.', 'wpst' ) );
		}
		self::store_derivative_meta( $attachment_id, $saved['path'] );
		return array( 'converted' => true, 'path' => $saved['path'], 'url' => self::path_to_url( $saved['path'] ) );
	}

	/** Latest converter report. */
	public static function report() {
		$report = get_option( self::REPORT_OPTION, array() );
		return is_array( $report ) ? $report : array();
	}

	/** Save a path and URL without replacing the original attachment. */
	private static function store_derivative_meta( $attachment_id, $path ) {
		update_post_meta( $attachment_id, '_wps_webp_file', wp_normalize_path( $path ) );
		update_post_meta( $attachment_id, '_wps_webp_url', self::path_to_url( $path ) );
	}

	/** Convert an uploads path to its public URL. */
	private static function path_to_url( $path ) {
		$upload = wp_upload_dir();
		$base   = trailingslashit( wp_normalize_path( $upload['basedir'] ) );
		$path   = wp_normalize_path( $path );
		if ( 0 !== strpos( $path, $base ) ) {
			return '';
		}
		$relative = ltrim( substr( $path, strlen( $base ) ), '/' );
		return trailingslashit( $upload['baseurl'] ) . str_replace( DIRECTORY_SEPARATOR, '/', $relative );
	}

	/** Eligible attachment IDs after cursor. */
	private static function attachment_ids_after( $cursor, $limit ) {
		global $wpdb;
		return array_map(
			'absint',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type IN ('image/jpeg','image/png','image/webp') AND ID > %d ORDER BY ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					absint( $cursor ),
					absint( $limit )
				)
			) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);
	}

	/** Count eligible attachments. */
	private static function candidate_count() {
		global $wpdb;
		return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type IN ('image/jpeg','image/png','image/webp')" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
