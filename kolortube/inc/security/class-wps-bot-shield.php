<?php
/**
 * Optional verified-bot and rate-limiting security layer.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Bot_Shield {
	/** Register only lightweight hooks; enforcement remains opt-in. */
	public static function register() {
		if ( ! get_theme_mod( 'wps_bot_shield_enabled', false ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'protect_request' ), 0 );
		add_filter( 'pre_comment_approved', array( __CLASS__, 'moderate_comment' ), 10, 2 );
	}

	/** Rate-limit clearly automated public requests and block fake Googlebot claims. */
	public static function protect_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'WP_CLI' ) || is_user_logged_in() ) {
			return;
		}
		$ua = self::user_agent();
		if ( '' === $ua || ! self::looks_automated( $ua ) ) {
			return;
		}

		if ( false !== stripos( $ua, 'googlebot' ) && get_theme_mod( 'wps_fake_googlebot_blocking', true ) && ! self::is_verified_googlebot() ) {
			self::block( 'fake_googlebot', __( 'Unverified Googlebot claim blocked.', 'wpst' ), 403 );
		}

		$limit = max( 20, absint( apply_filters( 'wps_bot_rate_limit_per_minute', 120 ) ) );
		$key   = 'wps_bot_rate_' . self::request_hash();
		$count = absint( get_transient( $key ) ) + 1;
		set_transient( $key, $count, MINUTE_IN_SECONDS + 10 );
		if ( $count > $limit ) {
			self::block( 'bot_rate_limited', __( 'Automated request rate limit exceeded.', 'wpst' ), 429 );
		}
	}

	/** Mark excessive automated comments as spam instead of causing a fatal response. */
	public static function moderate_comment( $approved, $commentdata ) {
		$ua = self::user_agent();
		if ( ! self::looks_automated( $ua ) ) {
			return $approved;
		}
		$key   = 'wps_bot_comment_' . self::request_hash();
		$count = absint( get_transient( $key ) ) + 1;
		set_transient( $key, $count, 10 * MINUTE_IN_SECONDS );
		if ( $count > 3 ) {
			self::log( 'warning', 'comment_bot_spam', __( 'Automated comment rate exceeded.', 'wpst' ) );
			return 'spam';
		}
		return $approved;
	}

	/** Verify Google crawler identity with reverse and forward DNS. */
	public static function is_verified_googlebot() {
		$ip = self::remote_ip();
		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		$key    = 'wps_googlebot_' . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return '1' === (string) $cached;
		}
		$host = strtolower( rtrim( (string) gethostbyaddr( $ip ), '.' ) );
		$valid_suffix = preg_match( '/(?:^|\.)(googlebot\.com|google\.com)$/', $host );
		$verified = false;
		if ( $valid_suffix ) {
			$addresses = gethostbynamel( $host );
			$verified  = is_array( $addresses ) && in_array( $ip, $addresses, true );
		}
		set_transient( $key, $verified ? '1' : '0', DAY_IN_SECONDS );
		return $verified;
	}

	/** Current configuration summary. */
	public static function status() {
		return array(
			'enabled'              => (bool) get_theme_mod( 'wps_bot_shield_enabled', false ),
			'fake_googlebot_block' => (bool) get_theme_mod( 'wps_fake_googlebot_blocking', true ),
			'rate_limit'           => max( 20, absint( apply_filters( 'wps_bot_rate_limit_per_minute', 120 ) ) ),
		);
	}

	/** Detect common automated clients conservatively. */
	private static function looks_automated( $ua ) {
		return (bool) preg_match( '/bot|crawler|spider|slurp|bingpreview|headless|python|curl|wget|httpclient|scrapy/i', (string) $ua );
	}

	/** Block one public request without exposing internal details. */
	private static function block( $code, $message, $status ) {
		self::log( 'warning', $code, $message );
		status_header( absint( $status ) );
		nocache_headers();
		header( 'Retry-After: 60' );
		wp_die( esc_html( $message ), esc_html__( 'Request blocked', 'wpst' ), array( 'response' => absint( $status ) ) );
	}

	/** Log without retaining raw IP addresses. */
	private static function log( $level, $code, $message ) {
		if ( class_exists( 'WPS_Event_Log' ) ) {
			WPS_Event_Log::log( $level, $code, $message, array( 'request_hash' => substr( self::request_hash(), 0, 16 ) ) );
		}
	}

	/** Return a keyed request fingerprint that cannot reveal the source IP. */
	private static function request_hash() {
		return hash_hmac( 'sha256', self::remote_ip() . '|' . self::user_agent(), wp_salt( 'nonce' ) );
	}

	/** Safely read the direct server peer address only. */
	private static function remote_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/** Safely read the current user agent. */
	private static function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) : '';
	}
}
