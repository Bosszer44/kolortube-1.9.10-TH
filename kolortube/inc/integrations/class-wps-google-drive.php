<?php
/**
 * Optional Google Drive service-account backup uploader.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Google_Drive {
	const CREDENTIAL_OPTION = 'wps_google_drive_credentials';

	/** Save validated encrypted service-account JSON. */
	public static function save_credentials( $json ) {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) || 'service_account' !== ( $data['type'] ?? '' ) || empty( $data['client_email'] ) || empty( $data['private_key'] ) || empty( $data['token_uri'] ) ) {
			return new WP_Error( 'wps_drive_invalid_credentials', __( 'The service-account JSON is invalid.', 'wpst' ) );
		}
		$minimal = array(
			'type'         => 'service_account',
			'client_email' => sanitize_email( $data['client_email'] ),
			'private_key'  => (string) $data['private_key'],
			'token_uri'    => esc_url_raw( $data['token_uri'] ),
			'project_id'   => sanitize_text_field( (string) ( $data['project_id'] ?? '' ) ),
		);
		$encrypted = WPS_Secrets::encrypt( wp_json_encode( $minimal ) );
		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}
		update_option( self::CREDENTIAL_OPTION, $encrypted, false );
		return true;
	}

	/** Remove saved service-account credentials. */
	public static function clear_credentials() {
		delete_option( self::CREDENTIAL_OPTION );
	}

	/** Return decrypted credentials to internal callers only. */
	public static function credentials() {
		$stored = get_option( self::CREDENTIAL_OPTION, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return array();
		}
		$json = WPS_Secrets::decrypt( $stored );
		$data = $json ? json_decode( $json, true ) : array();
		return is_array( $data ) ? $data : array();
	}

	/** Connection status without exposing secrets. */
	public static function status() {
		$credentials = self::credentials();
		$settings    = WPS_Full_Backup::settings();
		return array(
			'configured'  => ! empty( $credentials['client_email'] ) && ! empty( $credentials['private_key'] ),
			'client_email'=> isset( $credentials['client_email'] ) ? sanitize_email( $credentials['client_email'] ) : '',
			'folder_id'   => sanitize_text_field( (string) ( $settings['drive_folder_id'] ?? '' ) ),
		);
	}

	/** Upload a completed ZIP and return its Drive file ID. */
	public static function upload( $path, $folder_id = '' ) {
		if ( ! is_readable( $path ) || ! is_file( $path ) ) {
			return new WP_Error( 'wps_drive_file_missing', __( 'The backup file is unavailable for upload.', 'wpst' ) );
		}
		$credentials = self::credentials();
		if ( ! $credentials ) {
			return new WP_Error( 'wps_drive_unconfigured', __( 'Google Drive credentials are not configured.', 'wpst' ) );
		}
		$token = self::access_token( $credentials );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$boundary = 'wps-' . wp_generate_password( 24, false, false );
		$metadata = array( 'name' => basename( $path ) );
		$folder_id = sanitize_text_field( (string) $folder_id );
		if ( '' !== $folder_id ) {
			$metadata['parents'] = array( $folder_id );
		}
		$body  = '--' . $boundary . "\r\n";
		$body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
		$body .= wp_json_encode( $metadata ) . "\r\n";
		$body .= '--' . $boundary . "\r\n";
		$body .= "Content-Type: application/zip\r\n\r\n";
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			return new WP_Error( 'wps_drive_read_failed', __( 'Could not read the backup file.', 'wpst' ) );
		}
		$body .= $contents . "\r\n--" . $boundary . "--";
		$response = wp_remote_post(
			'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,size,createdTime',
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'multipart/related; boundary=' . $boundary,
				),
				'body'    => $body,
			)
		);
		unset( $body, $contents );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = absint( wp_remote_retrieve_response_code( $response ) );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $data['id'] ) ) {
			$message = isset( $data['error']['message'] ) ? sanitize_text_field( $data['error']['message'] ) : sprintf( 'Google Drive HTTP %d', $code );
			return new WP_Error( 'wps_drive_upload_failed', $message );
		}
		return sanitize_text_field( $data['id'] );
	}

	/** Exchange a signed service-account JWT for an OAuth access token. */
	private static function access_token( array $credentials ) {
		$cache_key = 'wps_drive_access_' . md5( $credentials['client_email'] );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$now    = time();
		$header = self::base64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::base64url(
			wp_json_encode(
				array(
					'iss'   => $credentials['client_email'],
					'scope' => 'https://www.googleapis.com/auth/drive.file',
					'aud'   => $credentials['token_uri'],
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);
		$unsigned = $header . '.' . $claims;
		$signature = '';
		if ( ! function_exists( 'openssl_sign' ) || ! openssl_sign( $unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'wps_drive_sign_failed', __( 'Could not sign the Google service-account request.', 'wpst' ) );
		}
		$jwt = $unsigned . '.' . self::base64url( $signature );
		$response = wp_remote_post(
			$credentials['token_uri'],
			array(
				'timeout' => 20,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			$message = isset( $data['error_description'] ) ? sanitize_text_field( $data['error_description'] ) : __( 'Google did not return an access token.', 'wpst' );
			return new WP_Error( 'wps_drive_token_failed', $message );
		}
		$ttl = max( 60, absint( $data['expires_in'] ?? 3600 ) - 120 );
		set_transient( $cache_key, sanitize_text_field( $data['access_token'] ), $ttl );
		return sanitize_text_field( $data['access_token'] );
	}

	/** Base64 URL encoding for JWT values. */
	private static function base64url( $value ) {
		return rtrim( strtr( base64_encode( (string) $value ), '+/', '-_' ), '=' );
	}
}
