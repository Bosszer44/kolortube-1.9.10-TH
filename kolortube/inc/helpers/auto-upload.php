<?php
/**
 * Automatic upload naming and attachment-to-post matching.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Auto_Upload {
	/** Register upload hooks. */
	public static function register() {
		if ( defined( 'WPMB_REPAIR_ENGINE_READY' ) && WPMB_REPAIR_ENGINE_READY ) {
			return;
		}
		if ( get_theme_mod( 'wps_auto_rename_uploads', false ) ) {
			add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'rename_upload' ) );
		}
		add_action( 'add_attachment', array( __CLASS__, 'attachment_added' ) );
	}

	/** Keep original filename intact — no prefix added. */
	public static function rename_upload( $file ) {
		if ( empty( $file['name'] ) ) {
			return $file;
		}
		$file['name'] = sanitize_file_name( $file['name'] );
		return $file;
	}

	/** Process a newly-created image attachment. */
	public static function attachment_added( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}
		$post_id = self::match_attachment_to_post( $attachment_id );
		if ( ! $post_id ) {
			return;
		}

		$mapping_changed = (bool) update_post_meta( $post_id, '_av_auto_media', $attachment_id );
		$assigned        = false;
		if ( get_theme_mod( 'wps_auto_assign_featured', false ) && ! has_post_thumbnail( $post_id ) ) {
			$assigned = (bool) set_post_thumbnail( $post_id, $attachment_id );
		}
		self::update_index( $post_id, $attachment_id, 'auto_upload' );
		if ( $mapping_changed || $assigned ) {
			do_action( 'wps_media_updated', $post_id, $attachment_id, 'auto_upload' );
		}
	}

	/**
	 * Match an attachment using its parent, an explicit AV Post-ID prefix,
	 * an all-numeric basename, or an exact post slug.
	 *
	 * Arbitrary numeric tokens are intentionally not treated as Post IDs because
	 * video codes such as FNS-218 would otherwise attach to post 218 by mistake.
	 */
	public static function match_attachment_to_post( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return 0;
		}
		if ( $attachment->post_parent && 'post' === get_post_type( $attachment->post_parent ) ) {
			return absint( $attachment->post_parent );
		}

		$path = get_attached_file( $attachment_id );
		$stem = sanitize_title( pathinfo( (string) $path, PATHINFO_FILENAME ) );
		if ( '' === $stem ) {
			return 0;
		}

		if ( preg_match( '/^av-(\d+)-/', $stem, $matches ) && 'post' === get_post_type( absint( $matches[1] ) ) ) {
			return absint( $matches[1] );
		}
		if ( ctype_digit( $stem ) && 'post' === get_post_type( absint( $stem ) ) ) {
			return absint( $stem );
		}

		$slug_candidates = array( $stem );
		if ( preg_match( '/^av-(?:\d+|[a-z0-9]{6,12})-(.+)$/', $stem, $matches ) && ! empty( $matches[1] ) ) {
			$slug_candidates[] = sanitize_title( $matches[1] );
		}

		foreach ( array_unique( $slug_candidates ) as $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'post' );
			if ( $post ) {
				return absint( $post->ID );
			}
		}
		return 0;
	}

	/** Scan and match one stable attachment batch. */
	public static function scan_batch( $limit = 100, $cursor = 0 ) {
		global $wpdb;
		$limit = min( 250, max( 1, absint( $limit ) ) );
		$ids   = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%' AND ID > %d ORDER BY ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $cursor ),
				$limit
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$out = array(
			'processed'   => 0,
			'matched'     => 0,
			'assigned'    => 0,
			'next_cursor' => absint( $cursor ),
			'done'        => false,
			'total'       => self::image_attachment_count(),
		);

		foreach ( $ids as $attachment_id ) {
			$attachment_id     = absint( $attachment_id );
			$out['processed']++;
			$out['next_cursor'] = max( $out['next_cursor'], $attachment_id );
			$post_id = self::match_attachment_to_post( $attachment_id );
			if ( ! $post_id ) {
				continue;
			}

			$out['matched']++;
			$mapping_changed = (bool) update_post_meta( $post_id, '_av_auto_media', $attachment_id );
			$assigned        = false;
			if ( ! has_post_thumbnail( $post_id ) && apply_filters( 'wps_media_scan_assign_featured', true, $post_id, $attachment_id ) ) {
				if ( set_post_thumbnail( $post_id, $attachment_id ) ) {
					$out['assigned']++;
					$assigned = true;
				}
			}
			self::update_index( $post_id, $attachment_id, 'media_scan' );
			if ( $mapping_changed || $assigned ) {
				do_action( 'wps_media_updated', $post_id, $attachment_id, 'media_scan' );
			}
		}
		$out['done'] = count( $ids ) < $limit;
		return $out;
	}

	/** Count image attachments. */
	private static function image_attachment_count() {
		global $wpdb;
		return absint( $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/** Extract the parent post ID from upload requests safely. */
	private static function request_post_id() {
		foreach ( array( 'post_id', 'post_ID' ) as $key ) {
			if ( isset( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( wp_unslash( $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
					return $post_id;
				}
			}
		}
		return 0;
	}

	/** Update the optional media index safely. */
	private static function update_index( $post_id, $attachment_id, $source ) {
		if ( class_exists( 'WPS_Task_Store' ) ) {
			WPS_Task_Store::update_media_index( $post_id, 'found', $attachment_id, wp_get_attachment_url( $attachment_id ), $source );
		}
	}
}

if ( ! function_exists( 'av_auto_match_attachment' ) ) {
	function av_auto_match_attachment( $attachment_id ) {
		$post_id = WPS_Auto_Upload::match_attachment_to_post( $attachment_id );
		if ( $post_id ) {
			update_post_meta( $post_id, '_av_auto_media', absint( $attachment_id ) );
		}
		return $post_id;
	}
}
if ( ! function_exists( 'av_auto_media_scan' ) ) {
	function av_auto_media_scan( $limit = 100, $cursor = 0 ) {
		return WPS_Auto_Upload::scan_batch( $limit, $cursor );
	}
}
