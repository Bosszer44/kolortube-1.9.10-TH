<?php
/**
 * Small encrypted secret store backed by WordPress salts.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Secrets {
	/** Encrypt a UTF-8 string. */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;
		$key       = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			return 'sodium:' . base64_encode( $nonce . $cipher );
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv  = random_bytes( 12 );
			$tag = '';
			$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $cipher ) {
				return 'openssl:' . base64_encode( $iv . $tag . $cipher );
			}
		}
		return new WP_Error( 'wps_crypto_unavailable', __( 'No supported encryption provider is available.', 'wpst' ) );
	}

	/** Decrypt a stored secret. */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		$key    = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
		if ( 0 === strpos( $stored, 'sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$raw = base64_decode( substr( $stored, 7 ), true );
			if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return false;
			}
			$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return sodium_crypto_secretbox_open( $cipher, $nonce, $key );
		}
		if ( 0 === strpos( $stored, 'openssl:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $stored, 8 ), true );
			if ( false === $raw || strlen( $raw ) <= 28 ) {
				return false;
			}
			$iv     = substr( $raw, 0, 12 );
			$tag    = substr( $raw, 12, 16 );
			$cipher = substr( $raw, 28 );
			return openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		}
		return false;
	}
}
