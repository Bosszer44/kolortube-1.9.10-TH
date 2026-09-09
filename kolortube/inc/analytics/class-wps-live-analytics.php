<?php
/**
 * Privacy-conscious first-party live visitor estimates.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Live_Analytics {
	/** Return the live-session table name. */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wps_live_sessions';
	}

	/** Register public pings only when explicitly enabled. */
	public static function register() {
		if ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) { return; }
		if ( ! get_theme_mod( 'wps_live_analytics_enabled', false ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 40 );
		add_action( 'wp_ajax_wps_live_ping', array( __CLASS__, 'ping' ) );
		add_action( 'wp_ajax_nopriv_wps_live_ping', array( __CLASS__, 'ping' ) );
		add_action( 'wps_daily_maintenance', array( __CLASS__, 'cleanup' ) );
	}

	/** Load a tiny, non-blocking front-end heartbeat. */
	public static function enqueue() {
		if ( is_admin() || is_feed() || is_robots() || wp_doing_ajax() ) {
			return;
		}
		$file = WPS_PATH . '/assets/js/live-analytics.js';
		wp_enqueue_script( 'wps-live-analytics', WPS_URI . '/assets/js/live-analytics.js', array(), is_file( $file ) ? (string) filemtime( $file ) : WPS_VERSION, true );
		wp_localize_script(
			'wps-live-analytics',
			'WPSLiveAnalytics',
			array(
				'url'      => admin_url( 'admin-ajax.php' ),
				'action'   => 'wps_live_ping',
				'interval' => 120000,
			)
		);
	}

	/** Store a live estimate without retaining the visitor IP address. */
	public static function ping() {
		if ( ! get_theme_mod( 'wps_live_analytics_enabled', false ) || ! self::table_exists() ) {
			wp_send_json_success();
		}
		$visitor = isset( $_POST['visitor'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', wp_unslash( $_POST['visitor'] ) ) : '';
		if ( strlen( $visitor ) < 16 || strlen( $visitor ) > 80 ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}
		$rate_key = 'wps_live_ping_' . hash_hmac( 'sha256', self::remote_ip() . '|' . $visitor, wp_salt( 'nonce' ) );
		if ( get_transient( $rate_key ) ) {
			wp_send_json_success();
		}
		set_transient( $rate_key, 1, 20 );

		$page   = isset( $_POST['page'] ) ? self::sanitize_page( wp_unslash( $_POST['page'] ) ) : '/';
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'direct';
		if ( ! in_array( $source, array( 'direct', 'internal', 'search', 'social', 'referral' ), true ) ) {
			$source = 'referral';
		}
		$ua     = self::user_agent();
		$is_bot = preg_match( '/bot|crawler|spider|slurp|headless|python|curl|wget/i', $ua ) ? 1 : 0;
		$hash   = hash_hmac( 'sha256', $visitor . '|' . self::remote_ip(), wp_salt( 'auth' ) );

		global $wpdb;
		$wpdb->replace(
			self::table_name(),
			array(
				'visitor_hash'    => $hash,
				'last_seen'       => current_time( 'mysql', true ),
				'is_bot'          => $is_bot,
				'source'          => $source,
				'page_path'       => $page,
				'user_agent_hash' => hash( 'sha256', $ua ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_send_json_success();
	}

	/** Return visitors seen in the last five minutes. */
	public static function stats( $minutes = 5 ) {
		$out = array( 'total' => 0, 'people' => 0, 'bots' => 0, 'sources' => array(), 'pages' => array() );
		if ( ! self::table_exists() ) {
			return $out;
		}
		global $wpdb;
		$table  = self::table_name();
		$before = gmdate( 'Y-m-d H:i:s', time() - max( 1, absint( $minutes ) ) * MINUTE_IN_SECONDS );
		$row    = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) total, SUM(CASE WHEN is_bot=0 THEN 1 ELSE 0 END) people, SUM(CASE WHEN is_bot=1 THEN 1 ELSE 0 END) bots FROM {$table} WHERE last_seen >= %s", $before ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$out['total']  = absint( $row['total'] ?? 0 );
		$out['people'] = absint( $row['people'] ?? 0 );
		$out['bots']   = absint( $row['bots'] ?? 0 );
		$sources = $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(*) amount FROM {$table} WHERE last_seen >= %s GROUP BY source ORDER BY amount DESC LIMIT 10", $before ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$pages   = $wpdb->get_results( $wpdb->prepare( "SELECT page_path, COUNT(*) amount FROM {$table} WHERE last_seen >= %s GROUP BY page_path ORDER BY amount DESC LIMIT 10", $before ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$out['sources'] = is_array( $sources ) ? $sources : array();
		$out['pages']   = is_array( $pages ) ? $pages : array();
		return $out;
	}

	/** Delete sessions inactive for more than one day. */
	public static function cleanup() {
		if ( ! self::table_exists() ) {
			return 0;
		}
		global $wpdb;
		$table  = self::table_name();
		$before = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		return absint( $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < %s", $before ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/** Check table availability. */
	public static function table_exists() {
		global $wpdb;
		$table = self::table_name();
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/** Sanitize a same-site relative page path and remove preview secrets. */
	private static function sanitize_page( $page ) {
		$page  = substr( sanitize_text_field( (string) $page ), 0, 500 );
		$parts = wp_parse_url( $page );
		$path  = is_array( $parts ) && isset( $parts['path'] ) ? '/' . ltrim( $parts['path'], '/' ) : '/';
		$query = array();
		if ( is_array( $parts ) && ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			foreach ( array_keys( $query ) as $key ) {
				if ( preg_match( '/nonce|token|password|customize|preview|uuid/i', $key ) ) {
					unset( $query[ $key ] );
				}
			}
		}
		return $path . ( $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
	}

	/** Server peer address used only inside a one-way keyed hash. */
	private static function remote_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/** User agent stored only as a one-way hash. */
	private static function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) : '';
	}
}
