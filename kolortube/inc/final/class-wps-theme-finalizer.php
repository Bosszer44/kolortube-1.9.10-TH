<?php
/**
 * Final theme controls for header, player display, settings lock and repair utilities.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Theme_Finalizer {
	const OPTION = 'wps_theme_finalizer_settings';
	const REPORT_OPTION = 'wps_theme_finalizer_report';

	public static function register() {
		add_action( 'init', array( __CLASS__, 'ensure_defaults' ), 5 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 30 );
		add_action( 'admin_post_wps_finalizer_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_wps_finalizer_scan_ads', array( __CLASS__, 'scan_ads' ) );
		add_action( 'admin_post_wps_finalizer_repair_ads', array( __CLASS__, 'repair_ads' ) );
		add_action( 'admin_post_wps_finalizer_link_scan', array( __CLASS__, 'link_scan' ) );
		add_action( 'admin_post_wps_finalizer_link_repair', array( __CLASS__, 'link_repair' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_source_guard' ), 0 );
		add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
		add_filter( 'theme_mod_video_listing_general_show_duration', array( __CLASS__, 'filter_listing_duration' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_assets' ), 30 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'customize_save_after', array( __CLASS__, 'snapshot_theme_mods' ) );
		add_action( 'updated_option', array( __CLASS__, 'snapshot_watch' ), 10, 3 );
	}

	public static function defaults() {
		return array(
			'header_split'              => 1,
			'header_mobile_search_full' => 1,
			'player_show_poster_overlay'=> 1,
			'player_hide_duration'      => 0,
			'player_hide_views'         => 0,
			'player_hide_likes'         => 0,
			'player_hide_like_percent'  => 0,
			'player_hide_rating_bar'    => 0,
			'lock_theme_settings'       => 1,
			'auto_snapshot_settings'    => 1,
			'old_domains'               => '',
			'current_domain'            => wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
			'ad_download_missing'       => 1,
			'ad_overwrite_working'      => 0,
			'ad_store_attachment_id'    => 1,
			'source_guard_enabled'       => 0,
			'source_guard_hide_generator'=> 1,
			'source_guard_hide_maps'     => 1,
			'source_guard_hide_comments' => 1,
			'source_guard_hide_custom_css'=> 0,
			'source_guard_hide_data_attrs'=> 0,
			'source_guard_strip_video_urls'=> 0,
			'source_guard_blocked_words' => "javct\nvdohide\npronxxnx\nggcdn\nembed.php",
		);
	}

	public static function ensure_defaults() {
		$settings = get_option( self::OPTION, null );
		if ( ! is_array( $settings ) ) {
			update_option( self::OPTION, self::defaults(), false );
			return;
		}
		$merged = wp_parse_args( $settings, self::defaults() );
		if ( $merged !== $settings ) {
			update_option( self::OPTION, $merged, false );
		}
	}

	public static function settings() {
		$settings = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	public static function get( $key, $fallback = null ) {
		$settings = self::settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	public static function enabled( $key ) {
		return ! empty( self::get( $key, 0 ) );
	}

	public static function admin_menu() {
		add_submenu_page(
			( defined( 'WPMB_WEBSITE_MANAGER_READY' ) ? 'wpmb-dashboard' : 'av-control-center' ),
			'ธีม / Player / ซ่อมลิงก์',
			'ธีมจบงาน',
			'manage_options',
			'wps-theme-finalizer',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wps-theme-finalizer' ) && false === strpos( (string) $hook, 'wps-taxonomy-cleaner' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_register_style( 'wps-finalizer-admin', false, array(), WPS_FRAMEWORK_VERSION );
		wp_enqueue_style( 'wps-finalizer-admin' );
		wp_add_inline_style( 'wps-finalizer-admin', self::admin_css() );
	}

	public static function save_settings() {
		self::require_admin();
		check_admin_referer( 'wps_finalizer_save', '_wps_nonce' );
		$old_domains = isset( $_POST['old_domains'] ) ? sanitize_textarea_field( wp_unslash( $_POST['old_domains'] ) ) : '';
		$current_domain = isset( $_POST['current_domain'] ) ? sanitize_text_field( wp_unslash( $_POST['current_domain'] ) ) : '';
		$source_guard_blocked_words = isset( $_POST['source_guard_blocked_words'] ) ? sanitize_textarea_field( wp_unslash( $_POST['source_guard_blocked_words'] ) ) : '';
		$settings = array_merge( self::settings(), array(
			'header_split'               => empty( $_POST['header_split'] ) ? 0 : 1,
			'header_mobile_search_full'  => empty( $_POST['header_mobile_search_full'] ) ? 0 : 1,
			'player_show_poster_overlay' => empty( $_POST['player_show_poster_overlay'] ) ? 0 : 1,
			'player_hide_duration'       => empty( $_POST['player_hide_duration'] ) ? 0 : 1,
			'player_hide_views'          => empty( $_POST['player_hide_views'] ) ? 0 : 1,
			'player_hide_likes'          => empty( $_POST['player_hide_likes'] ) ? 0 : 1,
			'player_hide_like_percent'   => empty( $_POST['player_hide_like_percent'] ) ? 0 : 1,
			'player_hide_rating_bar'     => empty( $_POST['player_hide_rating_bar'] ) ? 0 : 1,
			'lock_theme_settings'        => empty( $_POST['lock_theme_settings'] ) ? 0 : 1,
			'auto_snapshot_settings'     => empty( $_POST['auto_snapshot_settings'] ) ? 0 : 1,
			'ad_download_missing'        => empty( $_POST['ad_download_missing'] ) ? 0 : 1,
			'ad_overwrite_working'       => empty( $_POST['ad_overwrite_working'] ) ? 0 : 1,
			'ad_store_attachment_id'     => empty( $_POST['ad_store_attachment_id'] ) ? 0 : 1,
			'source_guard_enabled'        => empty( $_POST['source_guard_enabled'] ) ? 0 : 1,
			'source_guard_hide_generator' => empty( $_POST['source_guard_hide_generator'] ) ? 0 : 1,
			'source_guard_hide_maps'      => empty( $_POST['source_guard_hide_maps'] ) ? 0 : 1,
			'source_guard_hide_comments'  => empty( $_POST['source_guard_hide_comments'] ) ? 0 : 1,
			'source_guard_hide_custom_css'=> empty( $_POST['source_guard_hide_custom_css'] ) ? 0 : 1,
			'source_guard_hide_data_attrs'=> empty( $_POST['source_guard_hide_data_attrs'] ) ? 0 : 1,
			'source_guard_strip_video_urls'=> empty( $_POST['source_guard_strip_video_urls'] ) ? 0 : 1,
			'source_guard_blocked_words'  => $source_guard_blocked_words,
			'old_domains'                => $old_domains,
			'current_domain'             => $current_domain ?: wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
		) );
		update_option( self::OPTION, $settings, false );
		if ( ! empty( $_POST['snapshot_now'] ) ) {
			self::snapshot_theme_mods();
		}
		self::redirect( 'saved' );
	}

	public static function body_classes( $classes ) {
		$classes[] = 'wps-theme-finalizer';
		if ( self::enabled( 'header_split' ) ) {
			$classes[] = 'wps-header-split-on';
		}
		foreach ( array(
			'player_hide_duration' => 'wps-hide-duration',
			'player_hide_views' => 'wps-hide-views',
			'player_hide_likes' => 'wps-hide-likes',
			'player_hide_like_percent' => 'wps-hide-like-percent',
			'player_hide_rating_bar' => 'wps-hide-rating-bar',
		) as $key => $class ) {
			if ( self::enabled( $key ) ) {
				$classes[] = $class;
			}
		}
		return array_values( array_unique( $classes ) );
	}

	public static function filter_listing_duration( $value ) {
		return self::enabled( 'player_hide_duration' ) ? 'no' : $value;
	}

	public static function frontend_assets() {
		wp_register_style( 'wps-theme-finalizer-front', false, array(), WPS_FRAMEWORK_VERSION );
		wp_enqueue_style( 'wps-theme-finalizer-front' );
		wp_add_inline_style( 'wps-theme-finalizer-front', self::frontend_css() );
		wp_register_script( 'wps-theme-finalizer-inline', '', array(), WPS_FRAMEWORK_VERSION, true );
		wp_enqueue_script( 'wps-theme-finalizer-inline' );
		wp_add_inline_script( 'wps-theme-finalizer-inline', self::frontend_js() );
	}

	public static function player_wrapper_class() {
		return self::enabled( 'player_show_poster_overlay' ) ? ' wps-player-poster-enabled' : '';
	}

	public static function player_wrapper_style( $poster ) {
		if ( ! self::enabled( 'player_show_poster_overlay' ) || '' === trim( (string) $poster ) ) {
			return '';
		}
		return ' style="--wps-player-poster:url(' . esc_url( $poster ) . ');"';
	}

	public static function poster_overlay( $poster, $title = '' ) {
		if ( ! self::enabled( 'player_show_poster_overlay' ) || '' === trim( (string) $poster ) ) {
			return '';
		}
		$title = '' === $title ? get_the_title() : $title;
		return '<button type="button" class="wps-player-poster-overlay" data-wps-player-poster aria-label="Play video"><span class="wps-player-poster-bg"><img src="' . esc_url( $poster ) . '" alt="' . esc_attr( $title ) . '"></span><span class="wps-player-play-icon" aria-hidden="true"></span></button>';
	}

	public static function single_show_likes() {
		return ! self::enabled( 'player_hide_likes' );
	}


	public static function maybe_start_source_guard() {
		if ( ! self::enabled( 'source_guard_enabled' ) ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'source_guard_filter' ) );
	}

	public static function source_guard_filter( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		$settings = self::settings();
		if ( ! empty( $settings['source_guard_hide_custom_css'] ) ) {
			$html = preg_replace( '~<style[^>]+id=["\']wp-custom-css["\'][^>]*>.*?</style>~is', '', $html );
		}
		if ( ! empty( $settings['source_guard_hide_maps'] ) ) {
			$html = preg_replace( '~/\*#\s*sourceURL=.*?\*/~is', '', $html );
			$html = preg_replace( '~/\*#\s*sourceMappingURL=.*?\*/~is', '', $html );
			$html = preg_replace( '~//#\s*sourceMappingURL=.*?$~im', '', $html );
		}
		if ( ! empty( $settings['source_guard_hide_generator'] ) ) {
			$html = preg_replace( '~<meta[^>]+name=["\']generator["\'][^>]*>\s*~i', '', $html );
			$html = preg_replace( '~<link[^>]+rel=["\']EditURI["\'][^>]*>\s*~i', '', $html );
			$html = preg_replace( '~<link[^>]+xmlrpc\.php[^>]*>\s*~i', '', $html );
			$html = preg_replace( '~<link[^>]+wp-json[^>]*>\s*~i', '', $html );
		}
		if ( ! empty( $settings['source_guard_hide_data_attrs'] ) ) {
			$html = preg_replace( '~\sdata-thumbs=(["\']).*?\1~is', '', $html );
			$html = preg_replace( '~\sdata-post-id=(["\']).*?\1~is', '', $html );
		}
		$blocked_words = self::source_guard_blocked_words();
		if ( ! empty( $settings['source_guard_strip_video_urls'] ) && $blocked_words ) {
			foreach ( $blocked_words as $word ) {
				$q = preg_quote( $word, '~' );
				$html = preg_replace( '~\s(?:href|src)=["\'][^"\']*' . $q . '[^"\']*["\']~i', '', $html );
				$html = preg_replace( '~\sdata-[a-z0-9_-]+=["\'][^"\']*' . $q . '[^"\']*["\']~i', '', $html );
			}
			$html = preg_replace( '~<iframe\b(?![^>]*\bsrc=)[^>]*>\s*</iframe>~i', '', $html );
			$html = preg_replace( '~<source\b(?![^>]*\bsrc=)[^>]*>~i', '', $html );
		}
		if ( $blocked_words ) {
			foreach ( $blocked_words as $word ) {
				$q = preg_quote( $word, '~' );
				$html = preg_replace( '~<!--.*?' . $q . '.*?-->~is', '', $html );
			}
		}
		if ( ! empty( $settings['source_guard_hide_comments'] ) ) {
			$html = preg_replace( '~<!--(?!\[if).*?-->~s', '', $html );
		}
		return is_string( $html ) ? $html : '';
	}

	private static function source_guard_blocked_words() {
		$raw = (string) self::get( 'source_guard_blocked_words', '' );
		$lines = preg_split( '/[\r\n,]+/', $raw );
		$out = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function scan_ads() {
		self::require_admin();
		check_admin_referer( 'wps_finalizer_scan_ads', '_wps_nonce' );
		$report = self::ad_repair_report( false );
		self::store_report( 'ad_scan', $report );
		self::redirect( 'ad_scan_done' );
	}

	public static function repair_ads() {
		self::require_admin();
		check_admin_referer( 'wps_finalizer_repair_ads', '_wps_nonce' );
		$report = self::ad_repair_report( true );
		self::store_report( 'ad_repair', $report );
		self::redirect( 'ad_repair_done' );
	}

	private static function ad_repair_report( $write = false ) {
		$slots = class_exists( 'WPS_Ads' ) ? WPS_Ads::all() : get_option( 'wps_ad_slots', array() );
		if ( ! is_array( $slots ) ) {
			$slots = array();
		}
		$settings = self::settings();
		$old_domains = self::old_domains();
		$updated = 0;
		$items = array();
		foreach ( $slots as $key => $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}
			$url = trim( (string) ( $slot['image_url'] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			$host = preg_replace( '/^www\./', '', $host );
			$is_old_host = in_array( $host, $old_domains, true );
			$working = self::remote_url_works( $url );
			$attachment = self::find_attachment_by_url_or_filename( $url );
			$status = $working ? 'working' : 'broken';
			$new_url = '';
			$new_id = 0;
			$action = 'none';
			if ( $attachment ) {
				$new_id = absint( $attachment['id'] );
				$new_url = (string) $attachment['url'];
				$action = ( $new_url !== $url ) ? 'replace_from_media' : 'already_current';
			} elseif ( ( ! $working || $is_old_host || ! empty( $settings['ad_overwrite_working'] ) ) && ! empty( $settings['ad_download_missing'] ) ) {
				$download = self::download_ad_image( $url, $slot['alt'] ?? 'promotion' );
				if ( ! is_wp_error( $download ) && ! empty( $download['url'] ) ) {
					$new_id = absint( $download['id'] ?? 0 );
					$new_url = (string) $download['url'];
					$action = 'downloaded';
				} else {
					$action = is_wp_error( $download ) ? 'download_failed: ' . $download->get_error_message() : 'download_failed';
				}
			}
			if ( $new_url && $new_url !== $url && ( ! $working || $is_old_host || ! empty( $settings['ad_overwrite_working'] ) ) ) {
				if ( $write ) {
					$slots[ $key ]['image_url'] = esc_url_raw( $new_url );
					if ( ! empty( $settings['ad_store_attachment_id'] ) && $new_id ) {
						$slots[ $key ]['image_id'] = $new_id;
					}
				}
				$updated++;
			}
			$items[] = array(
				'slot' => $key,
				'old_url' => $url,
				'host' => $host,
				'old_host' => $is_old_host,
				'status' => $status,
				'action' => $action,
				'new_url' => $new_url,
				'attachment_id' => $new_id,
			);
		}
		if ( $write && $updated > 0 ) {
			update_option( 'wps_ad_slots', $slots, false );
			if ( class_exists( 'WPS_LiteSpeed' ) ) {
				WPS_LiteSpeed::purge_all();
			}
		}
		return array(
			'created_at' => current_time( 'mysql' ),
			'mode' => $write ? 'repair' : 'scan',
			'total' => count( $items ),
			'updated' => $updated,
			'items' => $items,
		);
	}

	private static function remote_url_works( $url ) {
		$response = wp_remote_head( $url, array( 'timeout' => 6, 'redirection' => 3, 'sslverify' => false ) );
		if ( is_wp_error( $response ) ) {
			$response = wp_remote_get( $url, array( 'timeout' => 8, 'redirection' => 3, 'sslverify' => false, 'limit_response_size' => 1024 ) );
		}
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = absint( wp_remote_retrieve_response_code( $response ) );
		return $code >= 200 && $code < 400;
	}

	private static function find_attachment_by_url_or_filename( $url ) {
		global $wpdb;
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$filename = basename( $path );
		if ( '' === $filename ) {
			return array();
		}
		$candidates = array_unique( array_filter( array(
			$filename,
			preg_replace( '/-scaled(?=\.[^.]+$)/i', '', $filename ),
			preg_replace( '/-\d+x\d+(?=\.[^.]+$)/i', '', $filename ),
			preg_replace( '/-\d+(?=\.[^.]+$)/', '', $filename ),
		) ) );
		foreach ( $candidates as $candidate ) {
			$like = '%' . $wpdb->esc_like( '/' . $candidate );
			$meta = $wpdb->get_row( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1", $like ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $meta ) {
				$found_url = wp_get_attachment_url( absint( $meta['post_id'] ) );
				if ( $found_url ) {
					return array( 'id' => absint( $meta['post_id'] ), 'url' => $found_url, 'file' => $meta['meta_value'] );
				}
			}
		}
		return array();
	}

	private static function download_ad_image( $url, $alt = 'promotion' ) {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$tmp = download_url( $url, 20 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $name || false === strpos( $name, '.' ) ) {
			$name = 'ad-image-' . time() . '.gif';
		}
		$file = array( 'name' => sanitize_file_name( $name ), 'tmp_name' => $tmp );
		$id = media_handle_sideload( $file, 0, sanitize_text_field( $alt ) );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		return array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) );
	}

	public static function link_scan() {
		self::require_admin();
		check_admin_referer( 'wps_finalizer_link_scan', '_wps_nonce' );
		$report = self::link_replace_report( false );
		self::store_report( 'link_scan', $report );
		self::redirect( 'link_scan_done' );
	}

	public static function link_repair() {
		self::require_admin();
		check_admin_referer( 'wps_finalizer_link_repair', '_wps_nonce' );
		$report = self::link_replace_report( true );
		self::store_report( 'link_repair', $report );
		self::redirect( 'link_repair_done' );
	}

	private static function link_replace_report( $write = false ) {
		global $wpdb;
		$old_domains = self::old_domains();
		$current = self::current_domain();
		$include_guid = ! empty( $_POST['include_guid'] );
		$tables = array(
			$wpdb->posts => array( 'ID', array( 'post_content', 'post_excerpt' ) ),
			$wpdb->postmeta => array( 'meta_id', array( 'meta_value' ) ),
			$wpdb->options => array( 'option_id', array( 'option_value' ) ),
			$wpdb->termmeta => array( 'meta_id', array( 'meta_value' ) ),
		);
		if ( $include_guid ) {
			$tables[ $wpdb->posts ][1][] = 'guid';
		}
		$items = array();
		$total_changed = 0;
		foreach ( $tables as $table => $spec ) {
			list( $pk, $columns ) = $spec;
			foreach ( $columns as $column ) {
				$where = array();
				$params = array();
				foreach ( $old_domains as $domain ) {
					$where[] = "$column LIKE %s";
					$params[] = '%' . $wpdb->esc_like( $domain ) . '%';
				}
				if ( empty( $where ) ) {
					continue;
				}
				$sql = "SELECT `$pk`, `$column` FROM `$table` WHERE " . implode( ' OR ', $where ) . ' LIMIT 500';
				$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				foreach ( (array) $rows as $row ) {
					$old_value = $row[ $column ];
					$new_value = self::replace_domains_value( $old_value, $old_domains, $current );
					$changed = ( $new_value !== $old_value );
					if ( $changed ) {
						$total_changed++;
						if ( $write ) {
							$wpdb->update( $table, array( $column => $new_value ), array( $pk => $row[ $pk ] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						}
					}
					$items[] = array( 'table' => $table, 'id' => $row[ $pk ], 'column' => $column, 'changed' => $changed, 'preview' => self::preview_value( $old_value ) );
				}
			}
		}
		return array( 'created_at' => current_time( 'mysql' ), 'mode' => $write ? 'repair' : 'scan', 'current_domain' => $current, 'old_domains' => $old_domains, 'changed' => $total_changed, 'items' => $items );
	}

	private static function replace_domains_value( $value, array $old_domains, $current ) {
		$unserialized = maybe_unserialize( $value );
		if ( $unserialized !== $value || is_array( $unserialized ) || is_object( $unserialized ) ) {
			$replaced = self::replace_recursive( $unserialized, $old_domains, $current );
			return maybe_serialize( $replaced );
		}
		return self::replace_string_domains( (string) $value, $old_domains, $current );
	}

	private static function replace_recursive( $value, array $old_domains, $current ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::replace_recursive( $v, $old_domains, $current );
			}
			return $value;
		}
		if ( is_object( $value ) ) {
			foreach ( get_object_vars( $value ) as $k => $v ) {
				$value->$k = self::replace_recursive( $v, $old_domains, $current );
			}
			return $value;
		}
		if ( is_string( $value ) ) {
			return self::replace_string_domains( $value, $old_domains, $current );
		}
		return $value;
	}

	private static function replace_string_domains( $value, array $old_domains, $current ) {
		foreach ( $old_domains as $domain ) {
			$value = str_replace( array( 'https://' . $domain, 'http://' . $domain, '//' . $domain ), array( 'https://' . $current, 'https://' . $current, '//' . $current ), $value );
		}
		return $value;
	}

	private static function preview_value( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = preg_replace( '/\s+/', ' ', $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 180 ) : substr( $value, 0, 180 );
	}

	private static function old_domains() {
		$lines = preg_split( '/[\r\n,]+/', (string) self::get( 'old_domains', '' ) );
		$domains = array();
		foreach ( (array) $lines as $line ) {
			$host = trim( strtolower( preg_replace( '#^https?://#', '', $line ) ) );
			$host = trim( preg_replace( '#/.*$#', '', $host ) );
			$host = preg_replace( '/^www\./', '', $host );
			if ( $host && $host !== self::current_domain() ) {
				$domains[] = $host;
			}
		}
		return array_values( array_unique( $domains ) );
	}

	private static function current_domain() {
		$host = trim( strtolower( (string) self::get( 'current_domain', '' ) ) );
		if ( '' === $host ) {
			$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		}
		return preg_replace( '/^www\./', '', $host );
	}

	public static function snapshot_theme_mods() {
		if ( ! self::enabled( 'lock_theme_settings' ) ) {
			return;
		}
		$snapshot = array(
			'created_at' => current_time( 'mysql' ),
			'theme' => get_stylesheet(),
			'template' => get_template(),
			'theme_mods' => get_theme_mods(),
			'wps_ad_slots' => get_option( 'wps_ad_slots', array() ),
			'wps_42_options' => get_option( 'wps_42_options', array() ),
			'wps_theme_finalizer_settings' => self::settings(),
		);
		update_option( 'wps_theme_settings_lock_snapshot', $snapshot, false );
	}

	public static function snapshot_watch( $option, $old_value, $value ) {
		if ( ! self::enabled( 'auto_snapshot_settings' ) ) {
			return;
		}
		if ( 0 === strpos( (string) $option, 'theme_mods_' ) || in_array( $option, array( 'wps_ad_slots', 'wps_42_options', self::OPTION ), true ) ) {
			self::snapshot_theme_mods();
		}
	}

	private static function store_report( $key, array $report ) {
		$reports = get_option( self::REPORT_OPTION, array() );
		$reports = is_array( $reports ) ? $reports : array();
		$reports[ $key ] = $report;
		update_option( self::REPORT_OPTION, $reports, false );
	}

	private static function report( $key ) {
		$reports = get_option( self::REPORT_OPTION, array() );
		return is_array( $reports ) && isset( $reports[ $key ] ) ? $reports[ $key ] : array();
	}

	private static function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage theme tools.', 'wpst' ), '', array( 'response' => 403 ) );
		}
	}

	private static function redirect( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'wps-theme-finalizer', 'wps_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_admin_page() {
		self::require_admin();
		$settings = self::settings();
		$notice = isset( $_GET['wps_notice'] ) ? sanitize_key( wp_unslash( $_GET['wps_notice'] ) ) : '';
		$ad_report = self::report( 'ad_repair' ) ?: self::report( 'ad_scan' );
		$link_report = self::report( 'link_repair' ) ?: self::report( 'link_scan' );
		$backup_settings = class_exists( 'WPS_Full_Backup' ) ? WPS_Full_Backup::settings() : array();
		?>
		<div class="wrap wps-finalizer-wrap">
			<h1>ธีมจบงาน / Player / Header / Repair</h1>
			<?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wps-finalizer-grid">
				<input type="hidden" name="action" value="wps_finalizer_save">
				<?php wp_nonce_field( 'wps_finalizer_save', '_wps_nonce' ); ?>
				<section class="wps-finalizer-card"><h2>Header</h2>
					<label><input type="checkbox" name="header_split" value="1" <?php checked( ! empty( $settings['header_split'] ) ); ?>> แยกโลโก้อยู่คนละฝั่งกับ Search / ภาษา / เมนู</label><br>
					<label><input type="checkbox" name="header_mobile_search_full" value="1" <?php checked( ! empty( $settings['header_mobile_search_full'] ) ); ?>> มือถือให้ช่องค้นหาเปิดเต็มแถว</label>
				</section>
				<section class="wps-finalizer-card"><h2>Player Display</h2>
					<label><input type="checkbox" name="player_show_poster_overlay" value="1" <?php checked( ! empty( $settings['player_show_poster_overlay'] ) ); ?>> แสดงรูปปกในตัวเล่นก่อนกด Play</label><br>
					<label><input type="checkbox" name="player_hide_duration" value="1" <?php checked( ! empty( $settings['player_hide_duration'] ) ); ?>> ซ่อนเวลา</label><br>
					<label><input type="checkbox" name="player_hide_views" value="1" <?php checked( ! empty( $settings['player_hide_views'] ) ); ?>> ซ่อนวิว</label><br>
					<label><input type="checkbox" name="player_hide_likes" value="1" <?php checked( ! empty( $settings['player_hide_likes'] ) ); ?>> ซ่อนไลค์/ดิสไลค์</label><br>
					<label><input type="checkbox" name="player_hide_like_percent" value="1" <?php checked( ! empty( $settings['player_hide_like_percent'] ) ); ?>> ซ่อนเปอร์เซ็นต์ไลค์</label><br>
					<label><input type="checkbox" name="player_hide_rating_bar" value="1" <?php checked( ! empty( $settings['player_hide_rating_bar'] ) ); ?>> ซ่อน Rating bar</label>
				</section>
				<section class="wps-finalizer-card"><h2>Source Guard</h2>
					<p>กรองเฉพาะ HTML Source หน้าเว็บแบบเปิด/ปิดได้ ค่าเริ่มต้นยังไม่เปิด เพื่อไม่ให้กระทบ player หรือ CSS เดิม</p>
					<label><input type="checkbox" name="source_guard_enabled" value="1" <?php checked( ! empty( $settings['source_guard_enabled'] ) ); ?>> เปิด Source Guard</label><br>
					<label><input type="checkbox" name="source_guard_hide_generator" value="1" <?php checked( ! empty( $settings['source_guard_hide_generator'] ) ); ?>> ซ่อน meta/link ของ WordPress ที่ไม่จำเป็น</label><br>
					<label><input type="checkbox" name="source_guard_hide_maps" value="1" <?php checked( ! empty( $settings['source_guard_hide_maps'] ) ); ?>> ลบ sourceURL/sourceMappingURL</label><br>
					<label><input type="checkbox" name="source_guard_hide_comments" value="1" <?php checked( ! empty( $settings['source_guard_hide_comments'] ) ); ?>> ลบ HTML comments ทั่วไป</label><br>
					<label><input type="checkbox" name="source_guard_hide_custom_css" value="1" <?php checked( ! empty( $settings['source_guard_hide_custom_css'] ) ); ?>> ซ่อน Additional CSS จาก Source <strong>ใช้เมื่อย้าย CSS ไปไฟล์แล้วเท่านั้น</strong></label><br>
					<label><input type="checkbox" name="source_guard_hide_data_attrs" value="1" <?php checked( ! empty( $settings['source_guard_hide_data_attrs'] ) ); ?>> ซ่อน data-thumbs / data-post-id</label><br>
					<label><input type="checkbox" name="source_guard_strip_video_urls" value="1" <?php checked( ! empty( $settings['source_guard_strip_video_urls'] ) ); ?>> โหมดเข้ม: ถอด href/src/data-* ที่ตรงคำวิดีโอ <strong>อาจทำให้ player ไม่แสดง</strong></label>
					<p><label>คำที่ใช้กรอง<br><textarea class="large-text code" rows="4" name="source_guard_blocked_words"><?php echo esc_textarea( $settings['source_guard_blocked_words'] ); ?></textarea></label></p>
				</section>
				<section class="wps-finalizer-card"><h2>Settings Lock</h2>
					<label><input type="checkbox" name="lock_theme_settings" value="1" <?php checked( ! empty( $settings['lock_theme_settings'] ) ); ?>> ล็อก snapshot ค่าธีม/โฆษณาไว้ใน wp_options</label><br>
					<label><input type="checkbox" name="auto_snapshot_settings" value="1" <?php checked( ! empty( $settings['auto_snapshot_settings'] ) ); ?>> สำรอง snapshot อัตโนมัติเมื่อมีการบันทึกค่า</label><br>
					<label><input type="checkbox" name="snapshot_now" value="1"> สร้าง snapshot ตอนกดบันทึกครั้งนี้</label>
				</section>
				<section class="wps-finalizer-card"><h2>Domains / Ads Repair</h2>
					<p><label>โดเมนปัจจุบัน<br><input class="regular-text" name="current_domain" value="<?php echo esc_attr( $settings['current_domain'] ); ?>"></label></p>
					<p><label>โดเมนเก่า / เว็บที่เคย clone มา<br><textarea class="large-text code" rows="5" name="old_domains"><?php echo esc_textarea( $settings['old_domains'] ); ?></textarea></label></p>
					<label><input type="checkbox" name="ad_download_missing" value="1" <?php checked( ! empty( $settings['ad_download_missing'] ) ); ?>> ถ้าไม่มีรูป ให้ดาวน์โหลดเข้า Media Library</label><br>
					<label><input type="checkbox" name="ad_overwrite_working" value="1" <?php checked( ! empty( $settings['ad_overwrite_working'] ) ); ?>> เขียนทับแม้ URL เดิมยังเปิดได้ ถ้าเป็นโดเมนเก่า</label><br>
					<label><input type="checkbox" name="ad_store_attachment_id" value="1" <?php checked( ! empty( $settings['ad_store_attachment_id'] ) ); ?>> เก็บ attachment_id ไว้กับป้ายโฆษณา</label>
				</section>
				<p class="submit wps-finalizer-submit"><?php submit_button( 'บันทึกตั้งค่าธีม', 'primary', 'submit', false ); ?></p>
			</form>

			<div class="wps-finalizer-grid">
				<section class="wps-finalizer-card"><h2>ซ่อมรูปโฆษณา</h2><p>เช็ก URL รูป แตก/โดเมนเก่า/วันที่ path ไม่ตรง แล้วค้นใน Media Library หรือโหลดเข้าเว็บปัจจุบัน</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_finalizer_scan_ads"><?php wp_nonce_field( 'wps_finalizer_scan_ads', '_wps_nonce' ); ?><?php submit_button( 'ตรวจรูปโฆษณาทั้งหมด', 'secondary', 'submit', false ); ?></form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_finalizer_repair_ads"><?php wp_nonce_field( 'wps_finalizer_repair_ads', '_wps_nonce' ); ?><?php submit_button( 'ซ่อมและบันทึกรูปโฆษณา', 'primary', 'submit', false ); ?></form>
					<?php self::render_report_table( $ad_report, array( 'slot', 'status', 'action', 'old_url', 'new_url' ) ); ?>
				</section>
				<section class="wps-finalizer-card"><h2>ค้นหาและแก้ลิงก์เว็บเก่า</h2><p>รองรับข้อมูล serialized ใน options/meta และเลือกแก้ GUID ได้เมื่อต้องการใช้กับเว็บ clone</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_finalizer_link_scan"><?php wp_nonce_field( 'wps_finalizer_link_scan', '_wps_nonce' ); ?><label><input type="checkbox" name="include_guid" value="1"> รวม GUID</label> <?php submit_button( 'สแกนลิงก์เก่า', 'secondary', 'submit', false ); ?></form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_finalizer_link_repair"><?php wp_nonce_field( 'wps_finalizer_link_repair', '_wps_nonce' ); ?><label><input type="checkbox" name="include_guid" value="1"> รวม GUID</label> <?php submit_button( 'แก้ลิงก์เป็นโดเมนปัจจุบัน', 'primary', 'submit', false ); ?></form>
					<?php self::render_report_table( $link_report, array( 'table', 'id', 'column', 'changed', 'preview' ) ); ?>
				</section>
				<section class="wps-finalizer-card"><h2>Backup ทั้งเว็บ</h2><p>ตั้งเวลา backup database + uploads + themes + plugins และส่ง Google Drive ได้โดยไม่ใช้ปลั๊กอินเสียเงิน</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_save_backup_settings"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?>
					<label><input type="checkbox" name="backup_enabled" value="1" <?php checked( ! empty( $backup_settings['enabled'] ) ); ?>> เปิด Auto Backup</label><br>
					<label>รอบเวลา <select name="schedule"><option value="daily" <?php selected( $backup_settings['schedule'] ?? 'daily', 'daily' ); ?>>ทุกวัน</option><option value="weekly" <?php selected( $backup_settings['schedule'] ?? '', 'weekly' ); ?>>ทุกสัปดาห์</option><option value="monthly" <?php selected( $backup_settings['schedule'] ?? '', 'monthly' ); ?>>ทุกเดือน</option></select></label>
					<label>เวลา <input type="time" name="time" value="<?php echo esc_attr( $backup_settings['time'] ?? '03:00' ); ?>"></label>
					<p><label><input type="checkbox" name="include_uploads" value="1" <?php checked( ! empty( $backup_settings['include_uploads'] ) ); ?>> uploads</label> <label><input type="checkbox" name="include_themes" value="1" <?php checked( ! empty( $backup_settings['include_themes'] ) ); ?>> themes</label> <label><input type="checkbox" name="include_plugins" value="1" <?php checked( ! empty( $backup_settings['include_plugins'] ) ); ?>> plugins</label> <label><input type="checkbox" name="include_mu_plugins" value="1" <?php checked( ! empty( $backup_settings['include_mu_plugins'] ) ); ?>> mu-plugins</label> <label><input type="checkbox" name="include_theme" value="1" <?php checked( ! empty( $backup_settings['include_theme'] ) ); ?>> current theme</label> <label><input type="checkbox" name="include_root_files" value="1" <?php checked( ! empty( $backup_settings['include_root_files'] ) ); ?>> root files</label></p>
					<p><label>เก็บย้อนหลัง <input type="number" name="retention" min="1" max="30" value="<?php echo esc_attr( absint( $backup_settings['retention'] ?? 5 ) ); ?>"></label></p>
					<label><input type="checkbox" name="drive_enabled" value="1" <?php checked( ! empty( $backup_settings['drive_enabled'] ) ); ?>> ส่งไป Google Drive</label>
					<p><input type="text" class="regular-text" name="drive_folder_id" placeholder="Drive folder ID" value="<?php echo esc_attr( $backup_settings['drive_folder_id'] ?? '' ); ?>"></p>
					<p><textarea class="large-text code" rows="4" name="service_account_json" placeholder="Service Account JSON — เว้นว่างถ้าบันทึกไว้แล้ว"></textarea></p>
					<label><input type="checkbox" name="clear_drive_credentials" value="1"> ล้าง Drive credentials</label><br>
					<?php submit_button( 'บันทึก Backup Settings', 'primary', 'submit', false ); ?></form>
				</section>
			</div>
		</div>
		<?php
	}

	private static function render_report_table( $report, array $columns ) {
		if ( empty( $report ) || empty( $report['items'] ) || ! is_array( $report['items'] ) ) {
			return;
		}
		echo '<p><strong>' . esc_html( $report['mode'] ?? '' ) . '</strong> ' . esc_html( $report['created_at'] ?? '' ) . ' · ' . esc_html( 'items: ' . count( $report['items'] ) ) . '</p>';
		echo '<div class="wps-finalizer-report"><table class="widefat striped"><thead><tr>';
		foreach ( $columns as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( array_slice( $report['items'], 0, 30 ) as $item ) {
			echo '<tr>';
			foreach ( $columns as $column ) {
				$value = isset( $item[ $column ] ) ? $item[ $column ] : '';
				if ( is_bool( $value ) ) {
					$value = $value ? 'yes' : 'no';
				}
				echo '<td><code>' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</code></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function frontend_css() {
		return <<<'CSS'
.wps-header-split-on #wrapper-navbar .nav-container{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:nowrap}.wps-header-split-on #wrapper-navbar .custom-logo-link,.wps-header-split-on #wrapper-navbar .navbar-brand{flex:0 0 auto}.wps-header-split-on #wrapper-navbar .search-nav{margin-left:auto;display:flex;align-items:center;justify-content:flex-end;gap:14px;min-width:0}.wps-header-split-on #wrapper-navbar .navbar-collapse{justify-content:flex-end}.wps-header-split-on #wrapper-navbar .header-search-form{max-width:760px;margin:0 auto}.wps-hide-duration .video-block .duration,.wps-hide-duration .video-duration,.wps-hide-duration .vjs-duration,.wps-hide-duration .vjs-remaining-time,.wps-hide-duration [class*="duration"]{display:none!important}.wps-hide-views #video-views,.wps-hide-views .views-number,.wps-hide-views [class*="views"]{display:none!important}.wps-hide-likes #video-rate,.wps-hide-likes .post-like,.wps-hide-likes [class*="like"],.wps-hide-likes [class*="dislike"]{display:none!important}.wps-hide-like-percent .percentage,.wps-hide-like-percent .like-percentage,.wps-hide-like-percent [class*="percent"]{display:none!important}.wps-hide-rating-bar .rating-bar,.wps-hide-rating-bar [class*="rating"]{display:none!important}.responsive-player.wps-player-poster-enabled{position:relative;background:#000;overflow:hidden}.wps-player-poster-overlay{position:absolute;inset:0;z-index:12;border:0;padding:0;margin:0;width:100%;height:100%;cursor:pointer;background:#000;display:flex;align-items:center;justify-content:center;overflow:hidden}.wps-player-poster-overlay.is-hidden{opacity:0;pointer-events:none;transition:.22s ease}.wps-player-poster-bg{position:absolute;inset:0}.wps-player-poster-bg img{width:100%;height:100%;object-fit:cover;display:block;filter:brightness(.72)}.wps-player-poster-overlay:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.16),rgba(0,0,0,.52))}.wps-player-play-icon{position:relative;z-index:2;width:78px;height:78px;border-radius:50%;background:rgba(255,255,255,.92);box-shadow:0 14px 40px rgba(0,0,0,.4)}.wps-player-play-icon:before{content:"";position:absolute;left:31px;top:22px;border-left:26px solid #111;border-top:17px solid transparent;border-bottom:17px solid transparent}.wps-single-static-ad,.happy-player-under{display:flex;justify-content:center;margin:14px auto}.wps-ad-image{max-width:100%;height:auto;display:block}.wps-floating-ad-layer .wps-floating-ad{z-index:9997}@media (max-width:767px){.wps-header-split-on #wrapper-navbar .nav-container{position:relative;gap:10px;min-height:64px}.wps-header-split-on #wrapper-navbar .custom-logo-link,.wps-header-split-on #wrapper-navbar .navbar-brand{max-width:56%;min-width:0;z-index:2}.wps-header-split-on #wrapper-navbar .search-nav{position:static;z-index:3}.wps-header-split-on #wrapper-navbar .custom-logo{max-width:190px;height:auto}.wps-header-split-on #wrapper-navbar .search-nav{gap:8px}.wps-player-play-icon{width:62px;height:62px}.wps-player-play-icon:before{left:25px;top:17px;border-left-width:22px;border-top-width:14px;border-bottom-width:14px}.wps-header-split-on #wrapper-navbar .header-search-form{padding:8px 12px;width:100%}.wps-header-split-on #wrapper-navbar .navbar-collapse,.wps-header-split-on #wrapper-navbar #navbarNavDropdown{position:absolute!important;top:calc(100% + 8px)!important;left:10px!important;right:10px!important;width:auto!important;max-height:calc(100vh - 116px);overflow-y:auto;border-radius:12px;box-shadow:0 10px 32px rgba(0,0,0,.24)}}
CSS;
	}

	private static function frontend_js() {
		return <<<'JS'
document.addEventListener('click',function(e){var poster=e.target.closest('[data-wps-player-poster]');if(!poster)return;poster.classList.add('is-hidden');setTimeout(function(){poster.style.display='none';},260);var wrap=poster.closest('.responsive-player');if(!wrap)return;var video=wrap.querySelector('video');if(video&&typeof video.play==='function'){video.play().catch(function(){});}});
JS;
	}

	private static function admin_css() {
		return '.wps-finalizer-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin:16px 0}.wps-finalizer-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;box-shadow:0 1px 2px rgba(0,0,0,.04)}.wps-finalizer-card h2{margin-top:0}.wps-finalizer-card label{line-height:1.9}.wps-finalizer-submit{grid-column:1/-1}.wps-finalizer-report{max-height:360px;overflow:auto;border:1px solid #dcdcde}.wps-finalizer-report code{white-space:normal}';
	}
}
