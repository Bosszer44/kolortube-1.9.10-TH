<?php
/**
 * Compact local search index for posts and duplicate analysis.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Search_Index {
	const FAILURE_OPTION = 'wps_search_index_failures';
	const EXTERNAL_CIRCUIT = 'wps_external_index_circuit';

	/** Return the search-index table name. */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wps_search_index';
	}

	/** Register incremental indexing and dashboard search. */
	public static function register() {
		if ( defined( 'WPMB_REPAIR_ENGINE_READY' ) && WPMB_REPAIR_ENGINE_READY ) {
			return;
		}
		add_action( 'save_post_post', array( __CLASS__, 'index_saved_post' ), 40, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_post' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'terms_changed' ), 40, 6 );
		add_action( 'wp_ajax_wps_search_index_query', array( __CLASS__, 'ajax_search' ) );
		add_action( 'customize_save_after', array( __CLASS__, 'clear_external_circuit' ), 140 );
	}

	/** Index a published post after save without queueing a whole rebuild. */
	public static function index_saved_post( $post_id, $post, $update ) {
		unset( $update );
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post instanceof WP_Post ) {
			return;
		}
		if ( 'publish' === $post->post_status ) {
			self::index_post( $post_id );
		} else {
			self::delete_post( $post_id );
		}
	}

	/** Reindex when indexed taxonomies change. */
	public static function terms_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		unset( $terms, $tt_ids, $append, $old_tt_ids );
		if ( in_array( $taxonomy, array( 'category', 'post_tag', 'actors', 'studio' ), true ) && 'post' === get_post_type( $object_id ) ) {
			self::index_post( $object_id );
		}
	}

	/** Remove one row when the post is deleted or unpublished. */
	public static function delete_post( $post_id ) {
		global $wpdb;
		$table = self::table_name();
		if ( ! self::table_exists() ) {
			return false;
		}
		return false !== $wpdb->delete( $table, array( 'post_id' => absint( $post_id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/** Build and store one index row. */
	public static function index_post( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || ! self::table_exists() ) {
			return new WP_Error( 'wps_index_post_unavailable', __( 'The post or search-index table is unavailable.', 'wpst' ) );
		}

		$title      = wp_strip_all_tags( get_the_title( $post_id ) );
		$slug       = sanitize_title( $post->post_name );
		$content    = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$excerpt    = wp_strip_all_tags( (string) $post->post_excerpt );
		$code       = self::extract_code( $title . ' ' . $slug . ' ' . $content );
		$actors     = self::term_names( $post_id, 'actors' );
		$studios    = self::term_names( $post_id, 'studio' );
		$categories = self::term_names( $post_id, 'category' );
		$tags       = self::term_names( $post_id, 'post_tag' );
		$source     = self::video_source_url( $post_id );
		$searchable = implode(
			' ',
			array_filter(
				array(
					$title,
					$slug,
					$code,
					implode( ' ', $actors ),
					implode( ' ', $studios ),
					implode( ' ', $categories ),
					implode( ' ', $tags ),
					$excerpt,
				)
			)
		);

		$row = array(
			'post_id'       => $post_id,
			'post_title'    => substr( $title, 0, 500 ),
			'post_name'     => substr( $slug, 0, 200 ),
			'code'          => substr( $code, 0, 64 ),
			'actress'       => substr( implode( ', ', $actors ), 0, 1000 ),
			'studio'        => substr( implode( ', ', $studios ), 0, 500 ),
			'taxonomy_text' => substr( implode( ', ', array_merge( $categories, $tags ) ), 0, 2000 ),
			'searchable'    => substr( $searchable, 0, 8000 ),
			'video_url'     => substr( $source, 0, 2000 ),
			'video_hash'    => $source ? hash( 'sha256', self::normalize_url( $source ) ) : '',
			'title_hash'    => hash( 'sha256', self::normalize_text( $title ) ),
			'content_hash'  => hash( 'sha256', self::normalize_text( $content ) ),
			'updated_at'    => current_time( 'mysql', true ),
		);

		global $wpdb;
		$result = $wpdb->replace(
			self::table_name(),
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( false === $result ) {
			return new WP_Error( 'wps_index_write_failed', $wpdb->last_error ? $wpdb->last_error : __( 'Search index write failed.', 'wpst' ) );
		}

		return $row;
	}

	/** Rebuild a stable cursor-based batch. */
	public static function process_batch( $limit = 100, $cursor = 0 ) {
		$limit = min( 250, max( 1, absint( $limit ) ) );
		$ids   = self::post_ids_after( $cursor, $limit );
		$out   = array(
			'processed'   => 0,
			'indexed'     => 0,
			'failed'      => 0,
			'next_cursor' => absint( $cursor ),
			'done'        => false,
			'total'       => self::published_count(),
			'failures'    => array(),
			'external'    => array( 'sent' => 0, 'failed' => 0 ),
		);
		$external_rows = array();

		foreach ( $ids as $post_id ) {
			$out['processed']++;
			$out['next_cursor'] = max( $out['next_cursor'], $post_id );
			$result = self::index_post( $post_id );
			if ( is_wp_error( $result ) ) {
				$out['failed']++;
				$out['failures'][] = array( 'post_id' => $post_id, 'message' => $result->get_error_message() );
				continue;
			}
			$out['indexed']++;
			$external_rows[] = $result;
		}

		if ( $out['failures'] ) {
			self::append_failures( $out['failures'] );
		}

		if ( $external_rows && get_theme_mod( 'wps_external_index_enabled', false ) ) {
			$external = self::sync_external( $external_rows );
			if ( is_wp_error( $external ) ) {
				$out['external']['failed'] = count( $external_rows );
			} else {
				$out['external']['sent'] = count( $external_rows );
			}
		}

		$out['done'] = count( $ids ) < $limit;
		return $out;
	}

	/** Search the local compact index. */
	public static function search( $query, $limit = 30 ) {
		$query = trim( sanitize_text_field( (string) $query ) );
		if ( '' === $query || ! self::table_exists() ) {
			return array();
		}
		global $wpdb;
		$table = self::table_name();
		$like  = '%' . $wpdb->esc_like( $query ) . '%';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, post_title, post_name, code, actress, studio, taxonomy_text, video_url, updated_at,
				CASE
					WHEN code = %s THEN 100
					WHEN post_title = %s THEN 90
					WHEN post_name = %s THEN 80
					WHEN post_title LIKE %s THEN 60
					WHEN searchable LIKE %s THEN 30
					ELSE 0
				END AS relevance
				FROM {$table}
				WHERE code = %s OR post_title LIKE %s OR post_name LIKE %s OR searchable LIKE %s
				ORDER BY relevance DESC, post_id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				strtoupper( $query ),
				$query,
				sanitize_title( $query ),
				$like,
				$like,
				strtoupper( $query ),
				$like,
				$like,
				$like,
				min( 100, max( 1, absint( $limit ) ) )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	/** AJAX index search used by the single control center. */
	public static function ajax_search() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to search the index.', 'wpst' ) ), 403 );
		}
		check_ajax_referer( WPS_Task_Runner::AJAX_NONCE, 'nonce' );
		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		wp_send_json_success( array( 'results' => self::search( $query, 30 ) ) );
	}

	/** Count indexed posts. */
	public static function count() {
		if ( ! self::table_exists() ) {
			return 0;
		}
		global $wpdb;
		return absint( $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/** Return recorded indexing failures. */
	public static function failures() {
		$value = get_option( self::FAILURE_OPTION, array() );
		return is_array( $value ) ? array_slice( $value, 0, 100 ) : array();
	}

	/** Reindex one failed post from the UI. */
	public static function reindex_post( $post_id ) {
		$result = self::index_post( $post_id );
		if ( ! is_wp_error( $result ) ) {
			$failures = array_filter(
				self::failures(),
				static function ( $failure ) use ( $post_id ) {
					return absint( $failure['post_id'] ?? 0 ) !== absint( $post_id );
				}
			);
			update_option( self::FAILURE_OPTION, array_values( $failures ), false );
		}
		return $result;
	}

	/** Clear the external endpoint circuit breaker after settings change. */
	public static function clear_external_circuit() {
		delete_transient( self::EXTERNAL_CIRCUIT );
	}

	/** Return a best-effort source URL from legacy and framework post metadata. */
	public static function video_source_url( $post_id ) {
		$keys = array(
			'video_url', 'video_file', 'video_embed', 'embed', 'embed_code', 'trailer_url',
			'wpst_video_url', 'wpst_video_embed', '_video_url', '_video_embed', 'iframe',
		);
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
				continue;
			}
			$url = self::extract_first_url( (string) $value );
			if ( $url ) {
				return $url;
			}
		}
		$post = get_post( $post_id );
		if ( $post ) {
			$url = self::extract_first_url( (string) $post->post_content );
			if ( $url ) {
				return $url;
			}
		}
		return '';
	}

	/** Normalize a URL for duplicate hashing. */
	public static function normalize_url( $url ) {
		$url   = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return strtolower( untrailingslashit( $url ) );
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$host   = strtolower( preg_replace( '/^www\./', '', $parts['host'] ) );
		$path   = isset( $parts['path'] ) ? '/' . ltrim( preg_replace( '#/+#', '/', $parts['path'] ), '/' ) : '/';
		$query  = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			foreach ( array_keys( $query ) as $key ) {
				if ( 0 === strpos( strtolower( $key ), 'utm_' ) || in_array( strtolower( $key ), array( 'fbclid', 'gclid' ), true ) ) {
					unset( $query[ $key ] );
				}
			}
			ksort( $query );
		}
		return $scheme . '://' . $host . untrailingslashit( $path ) . ( $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
	}

	/** Normalize text for exact duplicate comparison. */
	public static function normalize_text( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		$text = remove_accents( $text );
		$text = strtolower( preg_replace( '/\s+/u', ' ', trim( $text ) ) );
		return preg_replace( '/[^\p{L}\p{N}]+/u', '', $text );
	}

	/** Return table availability. */
	public static function table_exists() {
		global $wpdb;
		$table = self::table_name();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $table === $found;
	}

	/** Send one batch to an explicitly configured external endpoint. */
	private static function sync_external( array $rows ) {
		$endpoint = esc_url_raw( (string) get_theme_mod( 'wps_external_index_url', '' ) );
		if ( ! get_theme_mod( 'wps_external_index_enabled', false ) || '' === $endpoint ) {
			return new WP_Error( 'wps_external_index_unconfigured', __( 'External index endpoint is not configured.', 'wpst' ) );
		}
		if ( get_transient( self::EXTERNAL_CIRCUIT ) ) {
			return new WP_Error( 'wps_external_index_circuit', __( 'External index circuit breaker is open.', 'wpst' ) );
		}
		$token   = (string) get_theme_mod( 'wps_external_index_token', '' );
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'headers'     => $headers,
				'body'        => wp_json_encode( array( 'site' => home_url( '/' ), 'records' => $rows ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			set_transient( self::EXTERNAL_CIRCUIT, 1, 10 * MINUTE_IN_SECONDS );
			self::log_external_failure( $response->get_error_message() );
			return $response;
		}
		$code = absint( wp_remote_retrieve_response_code( $response ) );
		if ( $code < 200 || $code >= 300 ) {
			set_transient( self::EXTERNAL_CIRCUIT, 1, 10 * MINUTE_IN_SECONDS );
			$error = new WP_Error( 'wps_external_index_http', sprintf( 'External index HTTP %d', $code ) );
			self::log_external_failure( $error->get_error_message() );
			return $error;
		}
		return true;
	}

	/** Record bounded failure diagnostics. */
	private static function append_failures( array $failures ) {
		$current = self::failures();
		$merged  = array();
		foreach ( array_merge( $failures, $current ) as $failure ) {
			$post_id = absint( $failure['post_id'] ?? 0 );
			if ( ! $post_id || isset( $merged[ $post_id ] ) ) {
				continue;
			}
			$merged[ $post_id ] = array(
				'post_id'    => $post_id,
				'message'    => substr( sanitize_text_field( (string) ( $failure['message'] ?? '' ) ), 0, 500 ),
				'failed_at'  => current_time( 'mysql', true ),
			);
		}
		update_option( self::FAILURE_OPTION, array_slice( array_values( $merged ), 0, 100 ), false );
	}

	/** Log one external failure without including tokens. */
	private static function log_external_failure( $message ) {
		if ( class_exists( 'WPS_Event_Log' ) ) {
			WPS_Event_Log::log( 'warning', 'external_index_failed', $message );
		}
	}

	/** Extract a first HTTP(S) URL from plain URL, iframe or shortcode text. */
	private static function extract_first_url( $value ) {
		$value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		if ( preg_match( '#https?://[^\s"\'<>\]]+#i', $value, $matches ) ) {
			return esc_url_raw( rtrim( $matches[0], '.,;)' ) );
		}
		return '';
	}

	/** Extract a catalog code such as ABC-123. */
	private static function extract_code( $value ) {
		if ( preg_match( '/\b([A-Z]{2,12})[-_ ]?(\d{2,7}[A-Z]?)\b/i', (string) $value, $matches ) ) {
			return strtoupper( $matches[1] . '-' . $matches[2] );
		}
		return '';
	}

	/** Get term names without surfacing WP_Error values. */
	private static function term_names( $post_id, $taxonomy ) {
		$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		return is_wp_error( $terms ) ? array() : array_map( 'sanitize_text_field', (array) $terms );
	}

	/** Published post IDs after a stable cursor. */
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
