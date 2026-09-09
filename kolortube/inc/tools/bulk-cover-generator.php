<?php
/**
 * Bulk Cover Generator and local cover resolver.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Bulk_Cover_Generator {
	const EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'webp' );

	/** @var array<string,array<string,mixed>|false> */
	private static $request_cache = array();

	/** Locate a cover file by Post ID, slug, or safe basename. */
	public static function find_cover_file( $identifier ) {
		$identifier = sanitize_file_name( pathinfo( (string) $identifier, PATHINFO_FILENAME ) );
		if ( '' === $identifier ) {
			return false;
		}
		if ( array_key_exists( $identifier, self::$request_cache ) ) {
			return self::$request_cache[ $identifier ];
		}

		$cache_key = 'wps_cover_' . md5( $identifier );
		$cached    = wp_cache_get( $cache_key, 'wps_media' );
		if ( false !== $cached ) {
			$result = 'not-found' === $cached ? false : $cached;
			self::$request_cache[ $identifier ] = $result;
			return $result;
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
			self::$request_cache[ $identifier ] = false;
			return false;
		}

		// Cheap exact paths first.
		foreach ( self::exact_candidate_paths( $upload, $identifier ) as $candidate ) {
			if ( self::is_safe_upload_file( $candidate['path'], $upload['basedir'] ) ) {
				$result = self::candidate_result( $candidate['path'], $candidate['url'] );
				self::cache_result( $identifier, $cache_key, $result );
				return $result;
			}
		}

		// Prefer the Media Library index before recursive filesystem scanning.
		$attachment = self::find_attachment_by_basename( $identifier );
		if ( $attachment ) {
			self::cache_result( $identifier, $cache_key, $attachment );
			return $attachment;
		}

		if ( get_theme_mod( 'wps_cover_recursive_lookup', true ) ) {
			foreach ( self::recursive_candidate_paths( $upload, $identifier ) as $candidate ) {
				if ( self::is_safe_upload_file( $candidate['path'], $upload['basedir'] ) ) {
					$result = self::candidate_result( $candidate['path'], $candidate['url'] );
					self::cache_result( $identifier, $cache_key, $result );
					return $result;
				}
			}
		}

		self::$request_cache[ $identifier ] = false;
		wp_cache_set( $cache_key, 'not-found', 'wps_media', 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/** Resolve the best available cover while honoring KolorTube legacy metadata. */
	public static function resolve_cover( $post_id, $include_default = true ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return $include_default ? self::default_cover() : false;
		}

		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			$url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
			if ( $url ) {
				return array( 'url' => $url, 'attachment_id' => $thumbnail_id, 'source' => 'featured' );
			}
		}

		$mapped_id = absint( get_post_meta( $post_id, '_av_auto_media', true ) );
		if ( $mapped_id && wp_attachment_is_image( $mapped_id ) ) {
			$url = wp_get_attachment_image_url( $mapped_id, 'full' );
			if ( $url ) {
				return array( 'url' => $url, 'attachment_id' => $mapped_id, 'source' => 'mapped' );
			}
		}

		$legacy_url = class_exists( 'WPS_Compatibility' ) ? WPS_Compatibility::legacy_cover_url( $post_id ) : esc_url_raw( (string) get_post_meta( $post_id, 'thumb', true ) );
		if ( $legacy_url ) {
			return array( 'url' => $legacy_url, 'attachment_id' => 0, 'source' => 'legacy_thumb' );
		}

		$file = self::find_cover_file( $post_id );
		if ( $file ) {
			return array( 'url' => $file['url'], 'attachment_id' => absint( $file['attachment_id'] ), 'source' => 'post_id', 'path' => $file['path'] );
		}

		$post = get_post( $post_id );
		if ( $post && $post->post_name ) {
			$file = self::find_cover_file( $post->post_name );
			if ( $file ) {
				return array( 'url' => $file['url'], 'attachment_id' => absint( $file['attachment_id'] ), 'source' => 'slug', 'path' => $file['path'] );
			}
		}

		$cached_url = esc_url_raw( (string) get_post_meta( $post_id, '_wps_smart_cover_url', true ) );
		if ( $cached_url ) {
			return array( 'url' => $cached_url, 'attachment_id' => 0, 'source' => 'cached' );
		}

		return $include_default ? self::default_cover() : false;
	}

	/** Set a featured image from an ID/slug-matched local file. */
	public static function set_featured_from_local( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || has_post_thumbnail( $post_id ) ) {
			return false;
		}

		$cover = self::resolve_cover( $post_id, false );
		if ( ! $cover ) {
			self::update_index( $post_id, 'missing' );
			return false;
		}

		$attachment_id = absint( $cover['attachment_id'] );
		if ( ! $attachment_id && ! empty( $cover['path'] ) && get_theme_mod( 'wps_register_local_covers', true ) ) {
			$attachment_id = self::register_local_file( $cover['path'], $cover['url'], $post_id );
		}

		if ( $attachment_id && set_post_thumbnail( $post_id, $attachment_id ) ) {
			update_post_meta( $post_id, '_av_auto_media', $attachment_id );
			delete_post_meta( $post_id, '_wps_smart_cover_url' );
			self::update_index( $post_id, 'found', $attachment_id, wp_get_attachment_url( $attachment_id ), $cover['source'] );
			do_action( 'wps_media_updated', $post_id, $attachment_id, 'bulk_cover' );
			return true;
		}

		if ( ! empty( $cover['url'] ) ) {
			update_post_meta( $post_id, '_wps_smart_cover_url', esc_url_raw( $cover['url'] ) );
			self::update_index( $post_id, 'found', 0, $cover['url'], $cover['source'] );
		}
		return false;
	}

	/** Process one safe batch of posts. */
	public static function process_batch( $limit = 50, $cursor = 0 ) {
		$limit = min( 200, max( 1, absint( $limit ) ) );
		$ids   = self::post_ids_after( $cursor, $limit );
		$out   = array(
			'processed'   => 0,
			'success'     => 0,
			'skipped'     => 0,
			'missing'     => 0,
			'next_cursor' => absint( $cursor ),
			'done'        => false,
			'total'       => self::published_post_count(),
		);

		foreach ( $ids as $post_id ) {
			$out['processed']++;
			$out['next_cursor'] = max( $out['next_cursor'], absint( $post_id ) );
			if ( has_post_thumbnail( $post_id ) ) {
				$out['skipped']++;
				self::update_index( $post_id, 'found', get_post_thumbnail_id( $post_id ), get_the_post_thumbnail_url( $post_id, 'full' ), 'featured' );
				continue;
			}
			if ( self::set_featured_from_local( $post_id ) ) {
				$out['success']++;
				continue;
			}

			// A legacy/external cover is valid but cannot become a Media Library attachment.
			if ( self::resolve_cover( $post_id, false ) ) {
				$out['skipped']++;
			} else {
				$out['missing']++;
			}
		}

		$out['done'] = count( $ids ) < $limit;
		return $out;
	}


	/** Preview cover assignments without changing posts, attachments or metadata. */
	public static function dry_run_batch( $limit = 100, $cursor = 0 ) {
		$limit = min( 250, max( 1, absint( $limit ) ) );
		$ids   = self::post_ids_after( $cursor, $limit );
		$out   = array(
			'processed'     => 0,
			'would_assign'  => 0,
			'fallback_only' => 0,
			'missing'       => 0,
			'next_cursor'   => absint( $cursor ),
			'done'          => false,
			'total'         => self::published_post_count(),
		);
		foreach ( $ids as $post_id ) {
			$out['processed']++;
			$out['next_cursor'] = max( $out['next_cursor'], absint( $post_id ) );
			if ( has_post_thumbnail( $post_id ) ) {
				$out['fallback_only']++;
				continue;
			}
			$assignable = self::find_cover_file( $post_id );
			if ( ! $assignable ) {
				$post = get_post( $post_id );
				if ( $post && $post->post_name ) {
					$assignable = self::find_cover_file( $post->post_name );
				}
			}
			if ( $assignable && ( ! empty( $assignable['attachment_id'] ) || ( ! empty( $assignable['path'] ) && get_theme_mod( 'wps_register_local_covers', true ) ) ) ) {
				$out['would_assign']++;
			} elseif ( self::resolve_cover( $post_id, false ) ) {
				$out['fallback_only']++;
			} else {
				$out['missing']++;
			}
		}
		$out['done'] = count( $ids ) < $limit;
		return $out;
	}

	/** Process a legacy offset-based batch without changing the queue cursor API. */
	public static function process_offset_batch( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$limit  = min( 200, max( 1, absint( $limit ) ) );
		$offset = max( 0, absint( $offset ) );
		$cursor = 0;
		if ( $offset > 0 ) {
			$cursor = absint(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY ID ASC LIMIT 1 OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$offset - 1
					)
				) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			);
		}
		return self::process_batch( $limit, $cursor );
	}

	/** Scan one batch and update the missing-cover index without mutating thumbnails. */
	public static function scan_missing_batch( $limit = 100, $cursor = 0 ) {
		$limit = min( 250, max( 1, absint( $limit ) ) );
		$ids   = self::post_ids_after( $cursor, $limit );
		$out   = array(
			'processed'   => 0,
			'found'       => 0,
			'missing'     => 0,
			'next_cursor' => absint( $cursor ),
			'done'        => false,
			'total'       => self::published_post_count(),
		);

		foreach ( $ids as $post_id ) {
			$out['processed']++;
			$out['next_cursor'] = max( $out['next_cursor'], absint( $post_id ) );
			$cover = self::resolve_cover( $post_id, false );
			if ( $cover ) {
				$out['found']++;
				self::update_index( $post_id, 'found', absint( $cover['attachment_id'] ), $cover['url'], $cover['source'] );
			} else {
				$out['missing']++;
				self::update_index( $post_id, 'missing' );
			}
		}
		$out['done'] = count( $ids ) < $limit;
		return $out;
	}

	/** Default placeholder descriptor. */
	public static function default_cover() {
		return array(
			'url'           => WPS_URI . '/assets/images/no-image.jpg',
			'attachment_id' => 0,
			'source'        => 'default',
		);
	}

	/** Count published video posts. */
	public static function published_post_count() {
		$count = wp_count_posts( 'post' );
		return isset( $count->publish ) ? absint( $count->publish ) : 0;
	}

	/** Get post IDs after a stable cursor. */
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


	/**
	 * Legacy candidate helper retained for version-3 compatibility.
	 *
	 * @deprecated 4.1.0 Use exact_candidate_paths() and recursive_candidate_paths().
	 */
	private static function candidate_paths( array $upload, $identifier ) {
		return array_merge( self::exact_candidate_paths( $upload, $identifier ), self::recursive_candidate_paths( $upload, $identifier ) );
	}

	/** Build cheap exact candidates. */
	private static function exact_candidate_paths( array $upload, $identifier ) {
		$candidates = array();
		foreach ( self::EXTENSIONS as $extension ) {
			$filename     = $identifier . '.' . $extension;
			$candidates[] = array( 'path' => trailingslashit( $upload['basedir'] ) . $filename, 'url' => trailingslashit( $upload['baseurl'] ) . $filename );
			if ( ! empty( $upload['path'] ) && ! empty( $upload['url'] ) ) {
				$candidates[] = array( 'path' => trailingslashit( $upload['path'] ) . $filename, 'url' => trailingslashit( $upload['url'] ) . $filename );
			}
		}
		return $candidates;
	}

	/** Search standard year/month folders only, bounded to the first exact match. */
	private static function recursive_candidate_paths( array $upload, $identifier ) {
		$candidates = array();
		foreach ( self::EXTENSIONS as $extension ) {
			$filename = $identifier . '.' . $extension;
			$matches  = glob( trailingslashit( $upload['basedir'] ) . '[0-9][0-9][0-9][0-9]/[0-9][0-9]/' . $filename, GLOB_NOSORT );
			foreach ( is_array( $matches ) ? array_slice( $matches, 0, 1 ) : array() as $path ) {
				$relative     = ltrim( str_replace( wp_normalize_path( $upload['basedir'] ), '', wp_normalize_path( $path ) ), '/' );
				$candidates[] = array( 'path' => $path, 'url' => trailingslashit( $upload['baseurl'] ) . str_replace( DIRECTORY_SEPARATOR, '/', $relative ) );
			}
		}
		return $candidates;
	}

	/** Build a normalized candidate result. */
	private static function candidate_result( $path, $url ) {
		return array(
			'path'          => $path,
			'url'           => $url,
			'attachment_id' => self::attachment_id_for_file( $path, $url ),
		);
	}

	/** Cache one lookup for the current request and object-cache lifetime. */
	private static function cache_result( $identifier, $cache_key, array $result ) {
		self::$request_cache[ $identifier ] = $result;
		wp_cache_set( $cache_key, $result, 'wps_media', HOUR_IN_SECONDS );
	}

	/** Verify a candidate is an existing file below uploads. */
	private static function is_safe_upload_file( $path, $base_dir ) {
		$real_path = realpath( $path );
		$real_base = realpath( $base_dir );
		return $real_path && $real_base && is_file( $real_path ) && 0 === strpos( wp_normalize_path( $real_path ), trailingslashit( wp_normalize_path( $real_base ) ) );
	}

	/** Locate a Media Library attachment with an exact basename. */
	private static function find_attachment_by_basename( $identifier ) {
		global $wpdb;
		foreach ( self::EXTENSIONS as $extension ) {
			$filename = $identifier . '.' . $extension;
			$like     = '%/' . $wpdb->esc_like( $filename );
			$meta     = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND (meta_value = %s OR meta_value LIKE %s) ORDER BY post_id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$filename,
					$like
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $meta && wp_attachment_is_image( $meta['post_id'] ) ) {
				$path = get_attached_file( $meta['post_id'] );
				$url  = wp_get_attachment_url( $meta['post_id'] );
				if ( $path && $url && file_exists( $path ) ) {
					return array( 'path' => $path, 'url' => $url, 'attachment_id' => absint( $meta['post_id'] ) );
				}
			}
		}
		return false;
	}

	/** Resolve an attachment ID for an uploads file. */
	private static function attachment_id_for_file( $path, $url ) {
		$id = attachment_url_to_postid( $url );
		if ( $id ) {
			return absint( $id );
		}
		$upload   = wp_upload_dir();
		$relative = ltrim( str_replace( wp_normalize_path( $upload['basedir'] ), '', wp_normalize_path( $path ) ), '/' );
		global $wpdb;
		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s ORDER BY post_id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$relative
				)
			) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		);
	}

	/** Register an existing uploads file in the Media Library. */
	private static function register_local_file( $path, $url, $post_id ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) || ! self::is_safe_upload_file( $path, $upload['basedir'] ) ) {
			return 0;
		}

		$existing = self::attachment_id_for_file( $path, $url );
		if ( $existing ) {
			return $existing;
		}

		$filetype = wp_check_filetype( basename( $path ), null );
		if ( empty( $filetype['type'] ) || 0 !== strpos( $filetype['type'], 'image/' ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'guid'           => esc_url_raw( $url ),
				'post_mime_type' => $filetype['type'],
				'post_title'     => sanitize_text_field( get_the_title( $post_id ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_parent'    => absint( $post_id ),
			),
			$path,
			$post_id,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		// wp_insert_attachment() does not create _wp_attached_file for an existing file.
		update_attached_file( $attachment_id, $path );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $path );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
		return absint( $attachment_id );
	}

	/** Update the optional media index without making front-end rendering depend on it. */
	private static function update_index( $post_id, $status, $attachment_id = 0, $cover_url = '', $source = '' ) {
		if ( class_exists( 'WPS_Task_Store' ) ) {
			WPS_Task_Store::update_media_index( $post_id, $status, $attachment_id, $cover_url, $source );
		}
	}
}

if ( ! function_exists( 'av_find_cover_file' ) ) {
	function av_find_cover_file( $post_id ) {
		return WPS_Bulk_Cover_Generator::find_cover_file( $post_id );
	}
}
if ( ! function_exists( 'av_set_featured_from_local' ) ) {
	function av_set_featured_from_local( $post_id ) {
		return WPS_Bulk_Cover_Generator::set_featured_from_local( $post_id );
	}
}
if ( ! function_exists( 'av_bulk_process_covers' ) ) {
	function av_bulk_process_covers( $limit = 100, $offset = 0 ) {
		return WPS_Bulk_Cover_Generator::process_offset_batch( $limit, $offset );
	}
}
if ( ! function_exists( 'av_cover_dry_run' ) ) {
	function av_cover_dry_run( $limit = 100, $cursor = 0 ) {
		return WPS_Bulk_Cover_Generator::dry_run_batch( $limit, $cursor );
	}
}
if ( ! function_exists( 'av_scan_missing_covers' ) ) {
	function av_scan_missing_covers( $limit = 100, $cursor = 0 ) {
		return WPS_Bulk_Cover_Generator::scan_missing_batch( $limit, $cursor );
	}
}
if ( ! function_exists( 'av_bulk_page' ) ) {
	function av_bulk_page() {
		if ( class_exists( 'WPS_Dashboard' ) ) {
			WPS_Dashboard::render();
		}
	}
}
