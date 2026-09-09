<?php
/**
 * Protects the document head from visual banner/player HTML and safely renders
 * analytics/verification snippets after WordPress has printed enqueued assets.
 *
 * @package WPS_Framework
 */
defined( 'ABSPATH' ) || exit;

final class WPS_Head_Output_Guard {
	private const VERSION = '4.3.63-histats';
	private const MARKER  = 'wps_head_output_guard_version';
	private const LOG     = 'wps_quarantined_head_payloads';

	public static function register() {
		add_filter( 'pre_set_theme_mod_google_analytics_code', array( __CLASS__, 'filter_analytics' ), 10, 2 );
		add_filter( 'pre_set_theme_mod_meta_verification_code', array( __CLASS__, 'filter_verification' ), 10, 2 );
		add_filter( 'pre_set_theme_mod_other_script_codes', array( __CLASS__, 'filter_footer_scripts' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'render_head_snippets' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'render_footer_snippets' ), 99 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );

		if ( (string) get_option( self::MARKER, '' ) !== self::VERSION ) {
			self::repair_existing_settings();
		}
	}

	public static function is_visual_payload( $value ) {
		$value = strtolower( (string) $value );
		if ( '' === trim( $value ) ) {
			return false;
		}
		$markers = array(
			'happy-inside-player', 'pomo-box', 'wps-ad-wrap', 'data-wps-ad-slot',
			'see-pomo', 'see-promo', 'fast-ad-stack', 'promo-media', 'floating-ad',
		);
		foreach ( $markers as $marker ) {
			if ( false !== strpos( $value, $marker ) ) {
				return true;
			}
		}
		return (bool) preg_match( '/<\s*\/?\s*(?:div|img|a|button|figure|video|source|picture|canvas|form|input|select|textarea)\b/i', $value );
	}

	private static function has_disallowed_tags( $value, $allowed_tags ) {
		if ( ! preg_match_all( '/<\s*\/?\s*([a-z0-9:-]+)/i', (string) $value, $matches ) ) {
			return false;
		}
		$allowed = array_fill_keys( array_map( 'strtolower', $allowed_tags ), true );
		foreach ( $matches[1] as $tag ) {
			if ( ! isset( $allowed[ strtolower( $tag ) ] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Allow an official Histats script/noscript counter without opening the field to arbitrary visual HTML. */
	private static function is_histats_payload( $value ) {
		$value = (string) $value;
		if ( false === stripos( $value, '_Hasync' ) && false === stripos( $value, 'Histats.start' ) ) {
			return false;
		}
		if ( ! preg_match( '~(?:https?:)?//(?:s[0-9]+|sstatic[0-9]*)\.histats\.com/~i', $value ) ) {
			return false;
		}
		if ( self::has_disallowed_tags( $value, array( 'script', 'noscript', 'a', 'img' ) ) ) {
			return false;
		}
		return ! preg_match( '/\bon[a-z]+\s*=|javascript\s*:/i', $value );
	}

	private static function quarantine( $name, $value, $reason ) {
		$log = get_option( self::LOG, array() );
		$log = is_array( $log ) ? $log : array();
		$log[] = array(
			'time_th' => wp_date( 'c', null, new DateTimeZone( 'Asia/Bangkok' ) ),
			'name'    => sanitize_key( $name ),
			'reason'  => sanitize_key( $reason ),
			'sha256'  => hash( 'sha256', (string) $value ),
			'preview' => function_exists( 'mb_substr' ) ? mb_substr( wp_strip_all_tags( (string) $value ), 0, 180, 'UTF-8' ) : substr( wp_strip_all_tags( (string) $value ), 0, 180 ),
		);
		$log = array_slice( $log, -20 );
		update_option( self::LOG, $log, false );
		set_transient( 'wps_head_output_guard_notice', 1, 120 );
	}

	public static function filter_analytics( $value, $old_value = '' ) {
		unset( $old_value );
		$value = (string) $value;
		if ( '' !== trim( $value ) && ! preg_match( '/<\s*script\b|\b(?:gtag|ga|dataLayer|_gaq)\b/i', $value ) ) {
			self::quarantine( 'google_analytics_code', $value, 'plain_text_analytics_payload' );
			return '';
		}
		if ( self::is_visual_payload( $value ) || self::has_disallowed_tags( $value, array( 'script', 'meta', 'link', 'noscript' ) ) ) {
			self::quarantine( 'google_analytics_code', $value, 'visual_or_invalid_head_html' );
			return '';
		}
		return $value;
	}

	public static function filter_verification( $value, $old_value = '' ) {
		unset( $old_value );
		$value = (string) $value;
		if ( '' !== trim( $value ) && ! preg_match( '/<\s*(?:meta|link)\b/i', $value ) ) {
			self::quarantine( 'meta_verification_code', $value, 'plain_text_verification_payload' );
			return '';
		}
		if ( self::is_visual_payload( $value ) || self::has_disallowed_tags( $value, array( 'meta', 'link' ) ) ) {
			self::quarantine( 'meta_verification_code', $value, 'visual_or_invalid_verification_html' );
			return '';
		}
		return $value;
	}

	public static function filter_footer_scripts( $value, $old_value = '' ) {
		unset( $old_value );
		$value = (string) $value;
		if ( self::is_histats_payload( $value ) ) {
			return $value;
		}
		if ( self::is_visual_payload( $value ) ) {
			self::quarantine( 'other_script_codes', $value, 'visual_banner_html_in_script_field' );
			return '';
		}
		return $value;
	}

	private static function repair_existing_settings() {
		$checks = array(
			'google_analytics_code'  => 'filter_analytics',
			'meta_verification_code' => 'filter_verification',
			'other_script_codes'     => 'filter_footer_scripts',
		);
		foreach ( $checks as $name => $method ) {
			$value = get_theme_mod( $name, '' );
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			$clean = call_user_func( array( __CLASS__, $method ), $value, '' );
			if ( '' === $clean && '' !== trim( (string) $value ) ) {
				remove_theme_mod( $name );
			}
		}
		update_option( self::MARKER, self::VERSION, false );
	}

	public static function render_head_snippets() {
		$analytics = self::filter_analytics( get_theme_mod( 'google_analytics_code', '' ) );
		$verify    = self::filter_verification( get_theme_mod( 'meta_verification_code', '' ) );
		if ( '' !== trim( $analytics ) ) {
			echo "\n<!-- WPS safe analytics -->\n" . $analytics . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( '' !== trim( $verify ) ) {
			echo "\n<!-- WPS safe verification -->\n" . $verify . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public static function render_footer_snippets() {
		$code = self::filter_footer_scripts( get_theme_mod( 'other_script_codes', '' ) );
		if ( '' !== trim( $code ) ) {
			echo "\n<!-- WPS safe footer scripts -->\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'wps_head_output_guard_notice' ) ) {
			return;
		}
		delete_transient( 'wps_head_output_guard_notice' );
		echo '<div class="notice notice-warning is-dismissible"><p><strong>KolorTube:</strong> ย้าย HTML ป้ายที่ปนอยู่ในช่อง Header/Analytics ออกแล้ว เพื่อให้ CSS และ JavaScript โหลดตามปกติ ข้อมูลเดิมถูกเก็บเป็นลายนิ้วมือในระบบตรวจสอบ</p></div>';
	}
}
