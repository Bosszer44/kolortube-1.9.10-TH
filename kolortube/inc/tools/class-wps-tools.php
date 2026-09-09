<?php
/** Theme maintenance, import and export tools. */
defined( 'ABSPATH' ) || exit;

final class WPS_Tools {
	const NONCE_ACTION = 'wps_theme_tools';

	/** Register tool endpoints. */
	public static function register() {
		add_action( 'admin_post_wps_clear_cache', array( __CLASS__, 'clear_cache' ) );
		add_action( 'admin_post_wps_purge_litespeed', array( __CLASS__, 'purge_litespeed' ) );
		add_action( 'admin_post_wps_purge_litespeed_object', array( __CLASS__, 'purge_litespeed_object' ) );
		add_action( 'admin_post_wps_apply_litespeed_mode', array( __CLASS__, 'apply_litespeed_mode' ) );
		add_action( 'admin_post_wps_restore_framework_fallbacks', array( __CLASS__, 'restore_framework_fallbacks' ) );
		add_action( 'admin_post_wps_flush_rewrite', array( __CLASS__, 'flush_rewrite' ) );
		if ( wps_uni_owns_import_export() ) {
			// UNI is the sole Import/Export owner whenever its plugin or WP MY BOSS ownership marker is active.
			// Keep a guarded compatibility endpoint so old bookmarks/forms fail safely.
			add_action( 'admin_post_wps_export_settings', array( __CLASS__, 'blocked_import_export' ) );
			add_action( 'admin_post_wps_import_settings', array( __CLASS__, 'blocked_import_export' ) );
		} else {
			// Standalone emergency fallback only when neither UNI nor WP MY BOSS claims Import/Export ownership.
			add_action( 'admin_post_wps_export_settings', array( __CLASS__, 'export_settings' ) );
			add_action( 'admin_post_wps_import_settings', array( __CLASS__, 'import_settings' ) );
		}
		add_action( 'admin_post_wps_reset_settings', array( __CLASS__, 'reset_settings' ) );
	}

	/** Block legacy Theme settings transfer while UNI owns all Import/Export. */
	public static function blocked_import_export() {
		self::authorize();
		self::redirect_with_notice( 'uni_import_export_owner' );
	}

	public static function clear_cache() {
		self::authorize();
		if ( class_exists( 'WPS_Cache_Manager' ) ) {
			WPS_Cache_Manager::clear_all();
		}
		self::redirect_with_notice( 'cache_cleared' );
	}


	/** Purge LiteSpeed page cache and framework diagnostics. */
	public static function purge_litespeed() {
		self::authorize();
		if ( class_exists( 'WPS_LiteSpeed' ) && WPS_LiteSpeed::purge_all() ) {
			self::redirect_with_notice( 'litespeed_purged' );
		}
		self::redirect_with_notice( 'litespeed_unavailable' );
	}

	/** Purge LiteSpeed object cache only. */
	public static function purge_litespeed_object() {
		self::authorize();
		if ( class_exists( 'WPS_LiteSpeed' ) && WPS_LiteSpeed::purge_object_cache() ) {
			self::redirect_with_notice( 'litespeed_object_purged' );
		}
		self::redirect_with_notice( 'litespeed_unavailable' );
	}

	/** Apply the recommended LiteSpeed ownership settings. */
	public static function apply_litespeed_mode() {
		self::authorize();
		if ( class_exists( 'WPS_LiteSpeed' ) && WPS_LiteSpeed::is_active() ) {
			WPS_LiteSpeed::apply_safe_defaults();
			WPS_LiteSpeed::purge_all();
			self::redirect_with_notice( 'litespeed_mode_applied' );
		}
		self::redirect_with_notice( 'litespeed_unavailable' );
	}

	/** Restore the framework overlap settings saved before LiteSpeed migration. */
	public static function restore_framework_fallbacks() {
		self::authorize();
		if ( class_exists( 'WPS_LiteSpeed' ) && WPS_LiteSpeed::restore_fallback_settings() ) {
			self::redirect_with_notice( 'framework_fallbacks_restored' );
		}
		self::redirect_with_notice( 'framework_fallbacks_unavailable' );
	}

	public static function flush_rewrite() {
		self::authorize();
		flush_rewrite_rules( false );
		do_action( 'wps_rewrite_flushed' );
		self::redirect_with_notice( 'rewrite_flushed' );
	}

	/** Download a portable JSON document containing only known theme settings. */
	public static function export_settings() {
		self::authorize();
		$all_mods = get_theme_mods();
		$mods     = array();
		foreach ( self::allowed_keys() as $key ) {
			if ( in_array( $key, self::sensitive_keys(), true ) ) {
				continue;
			}
			if ( array_key_exists( $key, $all_mods ) ) {
				$mods[ $key ] = $all_mods[ $key ];
			}
		}
		if ( ! get_theme_mod( 'wps_backup_include_menus', true ) ) {
			unset( $mods['nav_menu_locations'] );
		}

		$payload = array(
			'framework'         => 'AV Framework PRO',
			'framework_version' => defined( 'WPS_FRAMEWORK_VERSION' ) ? WPS_FRAMEWORK_VERSION : WPS_VERSION,
			'theme_version'     => wp_get_theme( get_template() )->get( 'Version' ),
			'exported_at'       => wp_date( 'c' ),
			'site_url'          => home_url( '/' ),
			'theme_stylesheet'  => get_stylesheet(),
			'theme_mods'        => $mods,
		);
		$payload  = apply_filters( 'wps_export_payload', $payload );
		$filename = 'av-framework-pro-settings-' . wp_date( 'Y-m-d-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/** Import a validated settings document without deleting current values. */
	public static function import_settings() {
		self::authorize();
		if ( empty( $_FILES['settings_file']['tmp_name'] ) || ! isset( $_FILES['settings_file']['error'] ) || UPLOAD_ERR_OK !== (int) $_FILES['settings_file']['error'] ) {
			self::redirect_with_notice( 'import_failed' );
		}
		if ( empty( $_FILES['settings_file']['size'] ) || (int) $_FILES['settings_file']['size'] > MB_IN_BYTES ) {
			self::redirect_with_notice( 'import_too_large' );
		}
		$name = isset( $_FILES['settings_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['settings_file']['name'] ) ) : '';
		if ( 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			self::redirect_with_notice( 'import_invalid' );
		}

		$raw = file_get_contents( $_FILES['settings_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw || '' === trim( $raw ) ) {
			self::redirect_with_notice( 'import_invalid' );
		}
		$data = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) || empty( $data['theme_mods'] ) || ! is_array( $data['theme_mods'] ) ) {
			self::redirect_with_notice( 'import_invalid' );
		}

		$allowed = array_flip( self::allowed_keys() );
		$count   = 0;
		foreach ( $data['theme_mods'] as $key => $value ) {
			$key = sanitize_key( $key );
			if ( in_array( $key, self::sensitive_keys(), true ) || ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			$value = self::sanitize_import_value( $key, $value );
			if ( null === $value ) {
				continue;
			}
			set_theme_mod( $key, $value );
			++$count;
		}

		if ( class_exists( 'WPS_Cache_Manager' ) ) {
			WPS_Cache_Manager::clear_all();
		}
		self::redirect_with_notice( $count ? 'imported' : 'import_empty' );
	}

	/** Reset framework settings by default; legacy appearance values require explicit opt-in. */
	public static function reset_settings() {
		self::authorize();
		if ( empty( $_POST['confirm_reset'] ) ) {
			self::redirect_with_notice( 'reset_not_confirmed' );
		}

		$scope = isset( $_POST['reset_scope'] ) ? sanitize_key( wp_unslash( $_POST['reset_scope'] ) ) : 'framework';
		$keys  = 'all' === $scope ? self::allowed_keys() : array_keys( WPS_Customizer_Framework::get_setting_schema() );
		foreach ( $keys as $key ) {
			if ( 'nav_menu_locations' !== $key ) {
				remove_theme_mod( $key );
			}
		}

		if ( class_exists( 'WPS_Cache_Manager' ) ) {
			WPS_Cache_Manager::clear_all();
		}
		self::redirect_with_notice( 'all' === $scope ? 'settings_reset_all' : 'settings_reset' );
	}

	/** Return the exact allowlist used by import, export and full reset. */
	public static function allowed_keys() {
		$legacy = class_exists( 'WPS_Compatibility' ) ? WPS_Compatibility::legacy_theme_mod_keys() : array();
		$schema = class_exists( 'WPS_Customizer_Framework' ) ? array_keys( WPS_Customizer_Framework::get_setting_schema() ) : array();
		return array_values( array_unique( array_merge( $legacy, $schema ) ) );
	}

	/** Return secrets that must never be included in portable JSON files. */
	private static function sensitive_keys() {
		return array( 'wps_player_api_token', 'wps_external_index_token' );
	}

	/** Sanitize an imported value according to its original setting contract. */
	private static function sanitize_import_value( $key, $value ) {
		if ( 'nav_menu_locations' === $key ) {
			if ( ! is_array( $value ) || ! get_theme_mod( 'wps_backup_include_menus', true ) ) {
				return null;
			}
			return array_map( 'absint', $value );
		}
		if ( 'custom_logo' === $key ) {
			return absint( $value );
		}

		$schema = WPS_Customizer_Framework::get_setting_schema();
		if ( isset( $schema[ $key ] ) ) {
			$callback = $schema[ $key ]['sanitize_callback'];
			if ( 'wps_sanitize_select' === $callback ) {
				return self::sanitize_select_value( $key, $value, $schema[ $key ]['default'] );
			}
			return is_callable( $callback ) ? call_user_func( $callback, $value ) : null;
		}

		$html_keys = array(
			'google_analytics_code', 'meta_verification_code', 'other_script_codes', 'copyright_content',
			'seo_home_description', 'seo_video_cat_description', 'seo_video_tag_description', 'seo_search_description',
		);
		if ( 0 === strpos( $key, 'ads_' ) || in_array( $key, $html_keys, true ) ) {
			return current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( $value );
		}
		if ( in_array( $key, array( 'main_color', 'link_color', 'body_background_color' ), true ) ) {
			$color = sanitize_hex_color( (string) $value );
			return $color ? $color : null;
		}

		$legacy_select_defaults = array(
			'enable_video_preview'                => 'yes',
			'enable_thumbs_rotation'              => 'yes',
			'enable_video_tracking_link'          => 'no',
			'video_listing_general_show_duration' => 'yes',
			'video_listing_general_show_title'    => 'yes',
			'sidebar_position'                    => 'left',
			'wpst_sidebar_position'               => 'left',
			'mobile_columns'                      => '2',
			'wpst_container_type'                 => 'container',
			'wpst_posts_index_style'              => 'default',
			'seo_home_position'                   => 'bottom',
			'seo_video_cat_position'              => 'bottom',
			'seo_video_tag_position'              => 'bottom',
			'seo_search_position'                 => 'bottom',
		);
		if ( isset( $legacy_select_defaults[ $key ] ) ) {
			return self::sanitize_select_value( $key, $value, $legacy_select_defaults[ $key ] );
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : null;
	}

	/** Validate select imports independently of a live Customizer manager. */
	private static function sanitize_select_value( $key, $value, $default ) {
		$choices = array(
			'enable_video_preview'                => array( 'yes', 'no' ),
			'enable_thumbs_rotation'              => array( 'yes', 'no' ),
			'enable_video_tracking_link'          => array( 'yes', 'no' ),
			'video_listing_general_show_duration' => array( 'yes', 'no' ),
			'video_listing_general_show_title'    => array( 'yes', 'no' ),
			'sidebar_position'                    => array( 'left', 'right', 'none' ),
			'wpst_sidebar_position'               => array( 'left', 'right', 'none' ),
			'mobile_columns'                      => array( '1', '2' ),
			'wpst_container_type'                 => array( 'container', 'container-fluid' ),
			'wpst_posts_index_style'              => array( 'default', 'masonry' ),
			'seo_home_position'                   => array( 'top', 'bottom' ),
			'seo_video_cat_position'              => array( 'top', 'bottom' ),
			'seo_video_tag_position'              => array( 'top', 'bottom' ),
			'seo_search_position'                 => array( 'top', 'bottom' ),
			'wps_heartbeat_mode'                 => array( 'default', 'reduce', 'disable_frontend', 'disable' ),
			'wps_robots_mode'                    => array( 'default', 'index', 'noindex' ),
		);
		$value = sanitize_key( (string) $value );
		return isset( $choices[ $key ] ) && in_array( $value, $choices[ $key ], true ) ? $value : $default;
	}

	private static function authorize() {
		if ( ! current_user_can( 'manage_options' ) || ! get_theme_mod( 'wps_tools_enabled', true ) ) {
			wp_die( esc_html__( 'You are not allowed to use this tool.', 'wpst' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE_ACTION, '_wps_nonce' );
	}

	private static function redirect_with_notice( $code ) {
		set_transient( 'wps_notice_' . get_current_user_id(), sanitize_key( $code ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=av-control-center' ) );
		exit;
	}
}
