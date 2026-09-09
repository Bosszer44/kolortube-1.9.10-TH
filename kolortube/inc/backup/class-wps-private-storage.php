<?php
/**
 * Protected uploads storage for backups, reports and editor snapshots.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Private_Storage {
	/** Return and create the protected framework root. */
	public static function root() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) ) {
			return new WP_Error( 'wps_uploads_unavailable', __( 'The uploads directory is unavailable.', 'wpst' ) );
		}
		$root = trailingslashit( $upload['basedir'] ) . 'wps-private';
		if ( ! self::ensure_directory( $root ) ) {
			return new WP_Error( 'wps_private_storage_unwritable', __( 'The protected framework storage directory is not writable.', 'wpst' ) );
		}
		return $root;
	}

	/** Return a protected subdirectory. */
	public static function directory( $name ) {
		$root = self::root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$name = sanitize_key( $name );
		if ( '' === $name ) {
			return new WP_Error( 'wps_invalid_private_directory', __( 'Invalid private storage directory.', 'wpst' ) );
		}
		$path = trailingslashit( $root ) . $name;
		if ( ! self::ensure_directory( $path ) ) {
			return new WP_Error( 'wps_private_subdirectory_unwritable', __( 'A protected framework subdirectory is not writable.', 'wpst' ) );
		}
		return $path;
	}

	/** Confirm a path remains below the protected root. */
	public static function is_safe_path( $path ) {
		$root = self::root();
		if ( is_wp_error( $root ) ) {
			return false;
		}
		$root = trailingslashit( wp_normalize_path( $root ) );
		$path = wp_normalize_path( (string) $path );
		return 0 === strpos( $path, $root );
	}

	/** Write a file atomically below protected storage. */
	public static function atomic_write( $path, $contents ) {
		if ( ! self::is_safe_path( $path ) ) {
			return false;
		}
		$tmp = $path . '.tmp-' . wp_generate_password( 8, false, false );
		if ( false === file_put_contents( $tmp, $contents, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return false;
		}
		@chmod( $tmp, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		if ( ! @rename( $tmp, $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.rename_rename
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
			return false;
		}
		return true;
	}

	/** Ensure the directory and deny direct web access. */
	private static function ensure_directory( $path ) {
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return false;
		}
		$files = array(
			'.htaccess'  => "Require all denied\nDeny from all\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></system.webServer></configuration>",
			'index.php'  => "<?php\nhttp_response_code(404);\nexit;\n",
		);
		foreach ( $files as $name => $contents ) {
			$file = trailingslashit( $path ) . $name;
			if ( ! file_exists( $file ) ) {
				file_put_contents( $file, $contents, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
		return is_writable( $path );
	}
}
