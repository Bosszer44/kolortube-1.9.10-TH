<?php
/**
 * Player integration boundary and legacy-safe helpers.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Player {
	/** Register the player module boundary. */
	public static function register() {
		add_action( 'customize_save_after', array( __CLASS__, 'clear_api_circuit' ), 130 );
		do_action( 'wps_player_module_loaded' );
	}

	/** Clear the temporary remote API circuit breaker after settings change. */
	public static function clear_api_circuit() {
		delete_transient( 'wps_player_api_circuit_open' );
	}

	/**
	 * Return a conservative media extension for a URL.
	 *
	 * @param mixed $url Video URL.
	 * @return string
	 */
	public static function format_from_url( $url ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return 'mp4';
		}
		$extension = sanitize_key( strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) );
		return '' !== $extension ? $extension : 'mp4';
	}

	/**
	 * Extract a YouTube video ID from common URL forms.
	 *
	 * @param string $url YouTube URL.
	 * @return string
	 */
	public static function youtube_id( $url ) {
		$url   = (string) $url;
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$host = isset( $parts['host'] ) ? strtolower( preg_replace( '/^www\./', '', $parts['host'] ) ) : '';
		$path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';
		if ( 'youtu.be' === $host ) {
			return sanitize_text_field( strtok( $path, '/' ) );
		}
		if ( in_array( $host, array( 'youtube.com', 'm.youtube.com' ), true ) ) {
			if ( ! empty( $parts['query'] ) ) {
				parse_str( $parts['query'], $query );
				if ( ! empty( $query['v'] ) ) {
					return sanitize_text_field( $query['v'] );
				}
			}
			if ( preg_match( '#^(?:embed|shorts)/([^/?]+)#', $path, $matches ) ) {
				return sanitize_text_field( $matches[1] );
			}
		}
		return '';
	}

	/** Return a safe player API diagnostic payload. */
	public static function api_status() {
		$enabled  = (bool) get_theme_mod( 'wps_player_api_enabled', false );
		$endpoint = esc_url_raw( (string) get_theme_mod( 'wps_player_api_url', '' ) );
		return array(
			'enabled'      => $enabled,
			'configured'   => $enabled && '' !== $endpoint,
			'circuit_open' => (bool) get_transient( 'wps_player_api_circuit_open' ),
		);
	}

	/**
	 * Optional legacy remote API adapter.
	 *
	 * No request is made unless an endpoint is supplied through the
	 * `wps_legacy_player_api_url` filter. A short circuit breaker prevents an
	 * unavailable endpoint from slowing every video page.
	 *
	 * @param string $source Source URL.
	 * @return array|false
	 */
	public static function legacy_api_request( $source ) {
		$result = self::legacy_api_request_detailed( $source );
		return is_wp_error( $result ) ? false : $result;
	}

	/**
	 * Perform one diagnostic Player API request with bounded retries and a
	 * circuit breaker. The original KolorTube player is never replaced here.
	 *
	 * @param string $source Source URL.
	 * @return array|WP_Error
	 */
	public static function legacy_api_request_detailed( $source ) {
		if ( ! get_theme_mod( 'wps_player_api_enabled', false ) ) {
			return new WP_Error( 'wps_player_api_disabled', __( 'Player API is disabled; the original player remains active.', 'wpst' ) );
		}

		$endpoint = esc_url_raw( (string) apply_filters( 'wps_legacy_player_api_url', get_theme_mod( 'wps_player_api_url', '' ) ) );
		$token    = sanitize_text_field( (string) apply_filters( 'wps_legacy_player_api_token', get_theme_mod( 'wps_player_api_token', '' ) ) );
		$source   = esc_url_raw( (string) $source );
		if ( '' === $endpoint || '' === $source ) {
			return new WP_Error( 'wps_player_api_unconfigured', __( 'Player API has no valid endpoint or source URL.', 'wpst' ) );
		}
		if ( get_transient( 'wps_player_api_circuit_open' ) ) {
			return new WP_Error( 'wps_player_api_circuit', __( 'Player API circuit breaker is temporarily open.', 'wpst' ) );
		}

		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 8,
				'redirection' => 2,
				'headers'     => $headers,
				'body'        => wp_json_encode( array( 'source' => $source, 'token' => $token ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::record_api_failure( 'wps_player_api_http_error', $response->get_error_message(), 0 );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return self::record_api_failure( 'wps_player_api_http_' . $status, sprintf( __( 'Player API HTTP %d', 'wpst' ), $status ), $status );
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return self::record_api_failure( 'wps_player_api_invalid_json', __( 'Player API returned invalid JSON.', 'wpst' ), $status );
		}
		delete_transient( 'wps_player_api_failures' );
		delete_transient( 'wps_player_api_circuit_open' );
		delete_transient( 'wps_player_api_last_error' );
		return $data;
	}

	/** Determine whether a batch should stop instead of warning for every post. */
	public static function api_should_abort_batch( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}
		$code = $error->get_error_code();
		if ( in_array( $code, array( 'wps_player_api_disabled', 'wps_player_api_unconfigured', 'wps_player_api_circuit' ), true ) ) {
			return true;
		}
		if ( preg_match( '/wps_player_api_http_(401|403|404|429|5\d\d)$/', $code ) ) {
			return true;
		}
		return false;
	}

	/** Return the most recent non-secret Player API diagnostic. */
	public static function last_api_error() {
		$value = get_transient( 'wps_player_api_last_error' );
		return is_array( $value ) ? $value : array();
	}

	/** Store a failure and open the circuit after repeated or terminal errors. */
	private static function record_api_failure( $code, $message, $status ) {
		$failures = absint( get_transient( 'wps_player_api_failures' ) ) + 1;
		set_transient( 'wps_player_api_failures', $failures, 10 * MINUTE_IN_SECONDS );
		$terminal = in_array( absint( $status ), array( 401, 403, 404, 429 ), true ) || absint( $status ) >= 500;
		if ( $terminal || $failures >= 3 ) {
			set_transient( 'wps_player_api_circuit_open', 1, 5 * MINUTE_IN_SECONDS );
		}
		$diagnostic = array(
			'code'       => sanitize_key( $code ),
			'message'    => substr( sanitize_text_field( $message ), 0, 500 ),
			'http_status'=> absint( $status ),
			'failures'   => $failures,
			'time'       => current_time( 'mysql', true ),
		);
		set_transient( 'wps_player_api_last_error', $diagnostic, HOUR_IN_SECONDS );
		if ( class_exists( 'WPS_Event_Log' ) ) {
			WPS_Event_Log::log( 'warning', 'player_api_failed', $diagnostic['message'], array( 'http_status' => $diagnostic['http_status'], 'failures' => $failures ) );
		}
		return new WP_Error( $diagnostic['code'], $diagnostic['message'], $diagnostic );
	}
}

/* Legacy public wrappers retained for compatibility with site customizations. */
if ( ! function_exists( 'APIZembedXYZ' ) ) {
	function APIZembedXYZ( $source ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return WPS_Player::legacy_api_request( $source );
	}
}
if ( ! function_exists( 'getvideo' ) ) {
	function getvideo( $url ) {
		return wp_json_encode(
			array(
				'id'    => esc_url_raw( (string) $url ),
				'thumb' => '',
			)
		);
	}
}
if ( ! function_exists( 'splitid' ) ) {
	function splitid( $url ) {
		$url = (string) $url;
		if ( preg_match( '#file/d/([0-9A-Za-z_-]+)#', $url, $matches ) || preg_match( '/[?&]id=([0-9A-Za-z_-]+)/', $url, $matches ) ) {
			return sanitize_text_field( $matches[1] );
		}
		return sanitize_text_field( $url );
	}
}
