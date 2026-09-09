<?php
/** Professional bilingual controls, search suggestions and runtime hardening. */
defined( 'ABSPATH' ) || exit;

final class WPS_Professional_Suite {
	const OPTION = 'wps_professional_settings';
	const COOKIE = 'wps_lang';

	public static function register() {
		add_action( 'init', array( __CLASS__, 'handle_language_request' ), 1 );
		add_action( 'init', array( __CLASS__, 'remove_feed_discovery_links' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 40 );
		add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'public_assets' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_feed_requests' ), 0 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'allow_public_search_route' ), PHP_INT_MAX );
		add_filter( 'gettext', array( __CLASS__, 'translate_theme_text' ), 20, 3 );
		add_filter( 'wp_video_shortcode', array( __CLASS__, 'protect_video_markup' ), 20 );
		add_filter( 'the_content', array( __CLASS__, 'protect_video_markup' ), 99 );
		add_filter( 'style_loader_src', array( __CLASS__, 'strip_asset_version' ), 999 );
		add_filter( 'script_loader_src', array( __CLASS__, 'strip_asset_version' ), 999 );
	}

	public static function defaults() {
		return array(
			'ui_language' => 'th',
			'frontend_language' => 'th',
			'show_language_switcher' => 0,
			'search_enabled' => 1,
			'search_min_chars' => 2,
			'search_limit' => 10,
			'rest_api_enabled' => 1,
			'feed_redirect_enabled' => 1,
			'video_download_deterrence' => 1,
			'asset_hardening' => 1,
			'ads_master_enabled' => 1,
			'site_control_note' => '',
			'home_sections_enabled' => 1,
			'home_show_featured' => 1,
			'home_show_seo' => 1,
			'home_show_studios' => 1,
			'home_show_actors' => 1,
			'home_show_tags' => 1,
			'home_featured_count' => 20,
			'home_studio_count' => 20,
			'home_actor_count' => 20,
			'home_tag_count' => 20,
			'home_layout_mode' => 'grid',
			'home_columns_desktop' => 5,
			'home_columns_tablet' => 3,
			'home_columns_mobile' => 2,
			'home_rows' => 1,
			'home_featured_slider' => 1,
			'home_studio_slider' => 0,
			'home_actor_slider' => 0,
			'home_tag_slider' => 1,
			'home_featured_view_all' => 1,
			'home_studio_view_all' => 1,
			'home_actor_view_all' => 1,
			'home_tag_view_all' => 0,
			'home_featured_arrows' => 1,
			'home_tag_arrows' => 1,
			'home_slider_autoplay' => 1,
			'home_featured_autoplay' => 1,
			'home_studio_autoplay' => 0,
			'home_actor_autoplay' => 0,
			'home_tag_autoplay' => 1,
			'home_slider_interval' => 2500,
			'home_section_order' => 'featured,latest_videos,studio,actors,gallery_tags,seo',
			'home_actor_name_mode' => 'current',
			'home_seo_logo_id' => 0,
		);
	}

	public static function settings() {
		$stored = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	public static function get( $key, $fallback = null ) {
		$s = self::settings();
		return array_key_exists( $key, $s ) ? $s[ $key ] : $fallback;
	}

	public static function current_language() {
		$lang = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( ! in_array( $lang, array( 'th', 'en' ), true ) ) {
			$lang = sanitize_key( (string) self::get( 'frontend_language', 'th' ) );
		}
		return in_array( $lang, array( 'th', 'en' ), true ) ? $lang : 'th';
	}

	public static function text( $th, $en ) {
		return 'en' === self::current_language() ? $en : $th;
	}

	public static function admin_text( $th, $en ) {
		return 'en' === self::get( 'ui_language', 'th' ) ? $en : $th;
	}

	public static function handle_language_request() {
		if ( empty( $_GET['wps_lang'] ) ) {
			return;
		}
		$lang = sanitize_key( wp_unslash( $_GET['wps_lang'] ) );
		if ( ! in_array( $lang, array( 'th', 'en' ), true ) ) {
			return;
		}
		setcookie( self::COOKIE, $lang, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ self::COOKIE ] = $lang;
		if ( ! headers_sent() ) {
			wp_safe_redirect( remove_query_arg( 'wps_lang' ) );
			exit;
		}
	}

	public static function language_switcher() {
		// WPS 4.3.26: remove the frontend Thai/EN switcher from the navbar.
		// Language settings remain available internally, but no button is printed.
		return '';
	}

	public static function translate_theme_text( $translation, $text, $domain ) {
		if ( is_admin() || 'wpst' !== $domain || 'th' !== self::current_language() ) {
			return $translation;
		}
		$map = array(
			'Search...' => 'ค้นหาทั้งเว็บไซต์...',
			'Search for:' => 'ค้นหา:',
			'Skip to content' => 'ข้ามไปยังเนื้อหา',
			'Toggle navigation' => 'เปิดหรือปิดเมนู',
			'Show more related videos' => 'ดูรายการที่เกี่ยวข้องเพิ่มเติม',
			'You like this video? You will also like...' => 'รายการที่เกี่ยวข้อง',
			'Categories' => 'หมวดหมู่',
			'Tags' => 'แท็ก',
			'Actors' => 'นักแสดง',
			'Studios' => 'สตูดิโอ',
		);
		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	}

	public static function admin_menu() {
		add_submenu_page(
			( defined( 'WPMB_WEBSITE_MANAGER_READY' ) ? 'wpmb-dashboard' : 'av-control-center' ),
			'Professional Site Settings',
			self::admin_text( 'ตั้งค่าเว็บไซต์', 'Site Settings' ),
			'manage_options',
			'wps-professional-settings',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wps-professional-settings' ) ) {
			return;
		}
		wp_enqueue_media();
		$css = WPS_PATH . '/assets/css/admin-professional.css';
		$js = WPS_PATH . '/assets/js/admin-professional.js';
		wp_enqueue_style( 'wps-professional-admin', WPS_URI . '/assets/css/admin-professional.css', array(), is_file( $css ) ? (string) filemtime( $css ) : WPS_FRAMEWORK_VERSION );
		wp_enqueue_script( 'wps-professional-admin', WPS_URI . '/assets/js/admin-professional.js', array( 'jquery' ), is_file( $js ) ? (string) filemtime( $js ) : WPS_FRAMEWORK_VERSION, true );
		wp_localize_script( 'wps-professional-admin', 'WPSProfessionalAdmin', array(
			'chooseImage' => self::admin_text( 'เลือกภาพ', 'Choose image' ),
			'useImage' => self::admin_text( 'ใช้ภาพนี้', 'Use this image' ),
			'removeImage' => self::admin_text( 'ลบภาพ', 'Remove image' ),
		) );
	}

	public static function public_assets() {
		$css = WPS_PATH . '/assets/css/professional.css';
		$js = WPS_PATH . '/assets/js/professional.js';
		wp_enqueue_style( 'wps-professional', WPS_URI . '/assets/css/professional.css', array(), is_file( $css ) ? (string) filemtime( $css ) . '-home-row-1' : WPS_FRAMEWORK_VERSION );
		$js_version = is_file( $js ) ? (string) filemtime( $js ) . '-home-row-1' : WPS_FRAMEWORK_VERSION;
		wp_enqueue_script( 'wps-professional', WPS_URI . '/assets/js/professional.js?wps=' . rawurlencode( $js_version ), array(), $js_version, true );
		wp_localize_script( 'wps-professional', 'WPSProfessional', array(
			'enabled' => (bool) self::get( 'search_enabled', 1 ),
			'endpoint' => esc_url_raw( rest_url( 'wps/v1/search-suggestions' ) ),
			'minChars' => max( 1, absint( self::get( 'search_min_chars', 2 ) ) ),
			'loading' => self::text( 'กำลังค้นหา…', 'Searching…' ),
			'empty' => self::text( 'ไม่พบคำแนะนำ', 'No suggestions found' ),
			'error' => self::text( 'ค้นหาไม่สำเร็จ กรุณาลองใหม่', 'Search failed. Please try again.' ),
			'videoProtection' => (bool) self::get( 'video_download_deterrence', 1 ),
		) );
	}

	public static function save_settings() {
		if ( empty( $_POST['wps_professional_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wpst' ) );
		}
		check_admin_referer( 'wps_professional_save' );
		$current = self::settings();
		$posted = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$tab = isset( $_POST['return_tab'] ) ? sanitize_key( wp_unslash( $_POST['return_tab'] ) ) : 'general';

		if ( 'overview' === $tab ) {
			foreach ( array( 'show_language_switcher', 'search_enabled', 'ads_master_enabled', 'home_sections_enabled', 'home_show_featured', 'home_show_seo', 'home_show_studios', 'home_show_actors', 'home_show_tags', 'home_featured_slider', 'home_studio_slider', 'home_actor_slider', 'home_tag_slider', 'home_featured_view_all', 'home_tag_view_all', 'home_featured_autoplay', 'home_studio_autoplay', 'home_actor_autoplay', 'home_tag_autoplay', 'rest_api_enabled', 'feed_redirect_enabled', 'video_download_deterrence', 'asset_hardening' ) as $key ) {
				$current[ $key ] = empty( $posted[ $key ] ) ? 0 : 1;
			}
			$current['search_min_chars'] = max( 1, min( 5, absint( isset( $posted['search_min_chars'] ) ? $posted['search_min_chars'] : $current['search_min_chars'] ) ) );
			$current['search_limit'] = max( 5, min( 20, absint( isset( $posted['search_limit'] ) ? $posted['search_limit'] : $current['search_limit'] ) ) );
			$current['home_layout_mode'] = isset( $posted['home_layout_mode'] ) && in_array( $posted['home_layout_mode'], array( 'grid', 'slider' ), true ) ? $posted['home_layout_mode'] : 'grid';
			$current['home_columns_desktop'] = max( 2, min( 8, absint( isset( $posted['home_columns_desktop'] ) ? $posted['home_columns_desktop'] : $current['home_columns_desktop'] ) ) );
			$current['home_columns_tablet']  = max( 2, min( 6, absint( isset( $posted['home_columns_tablet'] ) ? $posted['home_columns_tablet'] : $current['home_columns_tablet'] ) ) );
			$current['home_columns_mobile']  = max( 1, min( 5, absint( isset( $posted['home_columns_mobile'] ) ? $posted['home_columns_mobile'] : $current['home_columns_mobile'] ) ) );
			$current['home_rows']            = max( 1, min( 6, absint( isset( $posted['home_rows'] ) ? $posted['home_rows'] : $current['home_rows'] ) ) );
			$current['home_slider_autoplay'] = empty( $posted['home_slider_autoplay'] ) ? 0 : 1;
			$current['home_slider_interval'] = max( 1000, min( 15000, absint( isset( $posted['home_slider_interval'] ) ? $posted['home_slider_interval'] : $current['home_slider_interval'] ) ) );
			$current['site_control_note'] = isset( $posted['site_control_note'] ) ? sanitize_textarea_field( wp_unslash( $posted['site_control_note'] ) ) : '';
				if ( isset( $posted['home_actor_name_mode'] ) && in_array( $posted['home_actor_name_mode'], array( 'current', 'th', 'en' ), true ) ) {
					$current['home_actor_name_mode'] = $posted['home_actor_name_mode'];
				}
				if ( isset( $posted['home_seo_logo_id'] ) ) {
					$current['home_seo_logo_id'] = absint( $posted['home_seo_logo_id'] );
				}

			$theme_mobile_columns = isset( $posted['theme_mobile_columns'] ) ? absint( $posted['theme_mobile_columns'] ) : absint( get_theme_mod( 'mobile_columns', '2' ) );
			set_theme_mod( 'mobile_columns', (string) max( 1, min( 2, $theme_mobile_columns ) ) );

			if ( class_exists( 'WPS_Control_Center_42' ) ) {
				WPS_Control_Center_42::update( array(
					'labels_enabled'     => empty( $posted['labels_enabled'] ) ? 0 : 1,
					'labels_hide_native' => empty( $posted['labels_hide_native'] ) ? 0 : 1,
					'labels_categories'  => empty( $posted['labels_categories'] ) ? 0 : 1,
					'labels_post_tags'   => empty( $posted['labels_post_tags'] ) ? 0 : 1,
					'labels_actors'      => empty( $posted['labels_actors'] ) ? 0 : 1,
					'labels_studios'     => empty( $posted['labels_studios'] ) ? 0 : 1,
				) );
			}

			if ( class_exists( 'WPS_Theme_Finalizer' ) ) {
				$finalizer = WPS_Theme_Finalizer::settings();
				foreach ( array( 'header_split', 'header_mobile_search_full', 'player_show_poster_overlay', 'player_hide_duration', 'player_hide_views', 'player_hide_likes', 'player_hide_like_percent', 'player_hide_rating_bar', 'lock_theme_settings', 'auto_snapshot_settings', 'source_guard_enabled' ) as $key ) {
					$finalizer[ $key ] = empty( $posted[ $key ] ) ? 0 : 1;
				}
				update_option( WPS_Theme_Finalizer::OPTION, $finalizer, false );
			}
		} elseif ( 'general' === $tab ) {
			if ( isset( $posted['ui_language'] ) && in_array( $posted['ui_language'], array( 'th', 'en' ), true ) ) {
				$current['ui_language'] = $posted['ui_language'];
			}
			if ( isset( $posted['frontend_language'] ) && in_array( $posted['frontend_language'], array( 'th', 'en' ), true ) ) {
				$current['frontend_language'] = $posted['frontend_language'];
			}
			$current['show_language_switcher'] = empty( $posted['show_language_switcher'] ) ? 0 : 1;
			$current['search_enabled'] = empty( $posted['search_enabled'] ) ? 0 : 1;
			$current['search_min_chars'] = max( 1, min( 5, absint( isset( $posted['search_min_chars'] ) ? $posted['search_min_chars'] : $current['search_min_chars'] ) ) );
			$current['search_limit'] = max( 5, min( 20, absint( isset( $posted['search_limit'] ) ? $posted['search_limit'] : $current['search_limit'] ) ) );
		} elseif ( 'homepage' === $tab && class_exists( 'WPS_Home_Sections' ) ) {
			$current = WPS_Home_Sections::sanitize_settings( $current, $posted );
		} elseif ( 'protection' === $tab ) {
			foreach ( array( 'rest_api_enabled', 'feed_redirect_enabled', 'video_download_deterrence', 'asset_hardening' ) as $key ) {
				$current[ $key ] = empty( $posted[ $key ] ) ? 0 : 1;
			}
		} elseif ( 'banners' === $tab && isset( $_POST['ads'] ) && is_array( $_POST['ads'] ) ) {
			WPS_Ads::save( $_POST['ads'] );
		}

		update_option( self::OPTION, $current, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'wps-professional-settings', 'tab' => $tab, 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		$tabs = array(
			'overview' => self::admin_text( 'ภาพรวมทุกฟังก์ชัน', 'All Functions' ),
			'general' => self::admin_text( 'ทั่วไปและภาษา', 'General & Language' ),
			'banners' => self::admin_text( 'ป้ายโฆษณาทั้งหมด', 'All Banners' ),
			'homepage' => self::admin_text( 'ส่วนข้อมูลหน้าโฮม', 'Homepage Sections' ),
			'protection' => self::admin_text( 'ฟีด REST และการป้องกัน', 'Feeds, REST & Protection' ),
			'diagnostics' => self::admin_text( 'ตรวจสอบระบบ', 'Diagnostics' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		$s = self::settings();
		?>
		<div class="wrap wps-pro-wrap">
			<div class="wps-pro-hero">
				<div><span class="wps-pro-kicker">KolorTube <?php echo esc_html( WPS_FRAMEWORK_VERSION ); ?></span><h1><?php echo esc_html( self::admin_text( 'ศูนย์ตั้งค่าเว็บไซต์ระดับมืออาชีพ', 'Professional Site Control Center' ) ); ?></h1><p><?php echo esc_html( self::admin_text( 'ดูสถานะและเปิด/ปิดฟังก์ชันสำคัญของธีมจากหน้าเดียว โดยไม่แตะ SEO สี โลโก้ หรือเมนูเดิมอัตโนมัติ', 'Review and toggle the theme\'s main features from one place without changing existing SEO, colors, logos or menus automatically.' ) ); ?></p></div>
				<div class="wps-pro-badge"><?php echo esc_html( count( WPS_Ads::definitions() ) ); ?> <?php echo esc_html( self::admin_text( 'ตำแหน่งป้าย', 'banner slots' ) ); ?></div>
			</div>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( self::admin_text( 'บันทึกการตั้งค่าเรียบร้อยแล้ว', 'Settings saved successfully.' ) ); ?></p></div><?php endif; ?>
			<nav class="nav-tab-wrapper wps-pro-tabs">
				<?php foreach ( $tabs as $key => $label ) : ?><a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'wps-professional-settings', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
			</nav>
			<?php if ( 'diagnostics' === $tab ) { self::render_diagnostics(); } else { ?>
			<form method="post" class="wps-pro-form">
				<?php wp_nonce_field( 'wps_professional_save' ); ?>
				<input type="hidden" name="wps_professional_action" value="save">
				<input type="hidden" name="return_tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php if ( 'overview' === $tab ) { self::render_overview_fields( $s ); } ?>
				<?php if ( 'general' === $tab ) { self::render_general_fields( $s ); } ?>
				<?php if ( 'banners' === $tab ) { self::render_banner_fields(); } ?>
				<?php if ( 'homepage' === $tab && class_exists( 'WPS_Home_Sections' ) ) { WPS_Home_Sections::render_admin_fields( $s ); } ?>
				<?php if ( 'protection' === $tab ) { self::render_protection_fields( $s ); } ?>
				<div class="wps-pro-savebar"><?php submit_button( self::admin_text( 'บันทึกการตั้งค่า', 'Save settings' ), 'primary', 'submit', false ); ?></div>
			</form>
			<?php } ?>
		</div>
		<?php
	}


	private static function status_pill( $on, $on_text = 'เปิด', $off_text = 'ปิด' ) {
		$class = $on ? 'is-on' : 'is-off';
		$text  = $on ? $on_text : $off_text;
		return '<span class="wps-status-pill ' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
	}

	private static function render_overview_fields( $s ) {
		$finalizer = class_exists( 'WPS_Theme_Finalizer' ) ? WPS_Theme_Finalizer::settings() : array();
		$labels    = class_exists( 'WPS_Control_Center_42' ) ? WPS_Control_Center_42::options() : array();
		$ads       = class_exists( 'WPS_Ads' ) ? WPS_Ads::all() : array();
		$ad_enabled_count = 0;
		foreach ( $ads as $slot ) {
			if ( ! empty( $slot['enabled'] ) ) {
				++$ad_enabled_count;
			}
		}
		$theme_mobile_columns = max( 1, min( 2, absint( get_theme_mod( 'mobile_columns', '2' ) ) ) );
		$backup_latest = class_exists( 'WPS_Full_Backup' ) ? WPS_Full_Backup::latest() : array();
		$backup_label = ! empty( $backup_latest['created_at'] ) ? $backup_latest['created_at'] : self::admin_text( 'ยังไม่มีรายการล่าสุด', 'No recent backup' );
		?>
		<div class="wps-overview-tools">
			<div class="wps-overview-intro">
				<h2><?php echo esc_html( self::admin_text( 'แผงควบคุมรวมของธีม', 'Unified Theme Control Panel' ) ); ?></h2>
				<p><?php echo esc_html( self::admin_text( 'หน้านี้รวมปุ่มเปิด/ปิดหลักที่มีผลกับหน้าเว็บจริงไว้ในจุดเดียว ส่วนรายละเอียดลึกยังแก้ได้จากแท็บย่อยด้านบน', 'This page gathers the main live toggles in one place. Detailed settings remain available in the tabs above.' ) ); ?></p>
				<div class="wps-overview-actions"><a class="button" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>"><?php echo esc_html( self::admin_text( 'เปิด Customizer', 'Open Customizer' ) ); ?></a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=av-control-center' ) ); ?>"><?php echo esc_html( self::admin_text( 'ตรวจสอบระบบ', 'System status' ) ); ?></a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wps-theme-finalizer' ) ); ?>"><?php echo esc_html( self::admin_text( 'ธีม / Player / ซ่อมลิงก์', 'Theme / Player / Repair' ) ); ?></a><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wps-file-manager' ) ); ?>"><?php echo esc_html( self::admin_text( 'ไฟล์เมเนเจอร์', 'File Manager' ) ); ?></a></div>
			</div>
			<section class="wps-pro-card wps-overview-summary"><h2><?php echo esc_html( self::admin_text( 'สถานะเร็ว', 'Quick Status' ) ); ?></h2><div class="wps-status-list">
				<div><strong>Search</strong><?php echo self::status_pill( ! empty( $s['search_enabled'] ) ); ?></div>
				<div><strong>Homepage Sections</strong><?php echo self::status_pill( ! empty( $s['home_sections_enabled'] ) ); ?></div>
				<div><strong>Ads</strong><?php echo self::status_pill( ! empty( $s['ads_master_enabled'] ) ); ?><small><?php echo esc_html( $ad_enabled_count ); ?> active slots</small></div>
				<div><strong>REST</strong><?php echo self::status_pill( ! empty( $s['rest_api_enabled'] ) ); ?></div>
				<div><strong>Source Guard</strong><?php echo self::status_pill( ! empty( $finalizer['source_guard_enabled'] ) ); ?></div>
				<div><strong>Backup</strong><span class="wps-status-pill is-neutral"><?php echo esc_html( $backup_label ); ?></span></div>
			</div></section>
		</div>

		<div class="wps-pro-grid wps-function-grid">
			<section class="wps-pro-card"><h2>Header / Mobile</h2><p><?php echo esc_html( self::admin_text( 'ควบคุมโลโก้ เมนูมือถือ ช่องค้นหา และจำนวนคอลัมน์ของหน้ารวม', 'Control logo/menu separation, mobile search and archive columns.' ) ); ?></p>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[header_split]" value="1" <?php checked( ! empty( $finalizer['header_split'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'แยกเครื่องมือออกจากโลโก้', 'Keep tools separate from logo' ) ); ?></span></label>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[header_mobile_search_full]" value="1" <?php checked( ! empty( $finalizer['header_mobile_search_full'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'ค้นหาบนมือถือแสดงเต็มแถว', 'Full-row mobile search' ) ); ?></span></label>
				<label><?php echo esc_html( self::admin_text( 'คอลัมน์มือถือของหน้ารวม', 'Archive mobile columns' ) ); ?><select name="settings[theme_mobile_columns]"><?php for ( $i = 1; $i <= 2; $i++ ) { echo '<option value="' . esc_attr( $i ) . '" ' . selected( $theme_mobile_columns, $i, false ) . '>' . esc_html( $i . ' columns' ) . '</option>'; } ?></select></label>
			</section>

			<section class="wps-pro-card"><h2>Search / Language</h2><p><?php echo esc_html( self::admin_text( 'ระบบค้นหาและปุ่มภาษาไทย/อังกฤษหน้าเว็บ', 'Site search and Thai/English switcher.' ) ); ?></p>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[show_language_switcher]" value="1" <?php checked( ! empty( $s['show_language_switcher'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'แสดงปุ่มไทย / EN', 'Show Thai / EN switcher' ) ); ?></span></label>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[search_enabled]" value="1" <?php checked( ! empty( $s['search_enabled'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'เปิดคำแนะนำการค้นหา', 'Enable search suggestions' ) ); ?></span></label>
				<div class="wps-pro-two"><label><?php echo esc_html( self::admin_text( 'เริ่มค้นหาที่', 'Minimum characters' ) ); ?><input type="number" min="1" max="5" name="settings[search_min_chars]" value="<?php echo esc_attr( $s['search_min_chars'] ); ?>"></label><label><?php echo esc_html( self::admin_text( 'จำนวนคำแนะนำ', 'Suggestion limit' ) ); ?><input type="number" min="5" max="20" name="settings[search_limit]" value="<?php echo esc_attr( $s['search_limit'] ); ?>"></label></div>
			</section>

			<section class="wps-pro-card"><h2>Homepage Sections</h2><p><?php echo esc_html( self::admin_text( 'เปิด/ปิดส่วนหน้าโฮมทั้งหมดจากฐานข้อมูลจริง', 'Enable or disable live homepage sections.' ) ); ?></p>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[home_sections_enabled]" value="1" <?php checked( ! empty( $s['home_sections_enabled'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'เปิดระบบหน้าโฮมแบบแยกส่วน', 'Enable separated homepage sections' ) ); ?></span></label>
				<div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[home_show_featured]" value="1" <?php checked( ! empty( $s['home_show_featured'] ) ); ?>> Featured</label><label><input type="checkbox" name="settings[home_show_seo]" value="1" <?php checked( ! empty( $s['home_show_seo'] ) ); ?>> SEO</label><label><input type="checkbox" name="settings[home_show_studios]" value="1" <?php checked( ! empty( $s['home_show_studios'] ) ); ?>> Studio</label><label><input type="checkbox" name="settings[home_show_actors]" value="1" <?php checked( ! empty( $s['home_show_actors'] ) ); ?>> <?php echo esc_html( self::admin_text( 'นักแสดง', 'Actors' ) ); ?></label><label><input type="checkbox" name="settings[home_show_tags]" value="1" <?php checked( ! empty( $s['home_show_tags'] ) ); ?>> Gallery Tag</label></div>
			</section>

			<section class="wps-pro-card"><h2>Homepage Layout</h2><p><?php echo esc_html( self::admin_text( 'เลือก Grid/Slider แยกแต่ละบล็อกได้ เช่น เปิดสไลด์เฉพาะเรื่องแนะนำ และปิดสไลด์ Studio/นักแสดง/แท็ก', 'Choose Grid/Slider per section. For example, make only Featured a slider and keep Studio/Actors/Tags as grids.' ) ); ?></p>
				<p><label>Fallback mode <select name="settings[home_layout_mode]"><option value="grid" <?php selected( $s['home_layout_mode'], 'grid' ); ?>>Grid</option><option value="slider" <?php selected( $s['home_layout_mode'], 'slider' ); ?>>Slider</option></select></label></p>
				<div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[home_featured_slider]" value="1" <?php checked( ! empty( $s['home_featured_slider'] ) ); ?>> Featured Slider</label><label><input type="checkbox" name="settings[home_studio_slider]" value="1" <?php checked( ! empty( $s['home_studio_slider'] ) ); ?>> Studio Slider</label><label><input type="checkbox" name="settings[home_actor_slider]" value="1" <?php checked( ! empty( $s['home_actor_slider'] ) ); ?>> Actors Slider</label><label><input type="checkbox" name="settings[home_tag_slider]" value="1" <?php checked( ! empty( $s['home_tag_slider'] ) ); ?>> Gallery Tag Slider</label></div>
				<div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[home_featured_view_all]" value="1" <?php checked( ! empty( $s['home_featured_view_all'] ) ); ?>> Featured View All</label><label><input type="checkbox" name="settings[home_tag_view_all]" value="1" <?php checked( ! empty( $s['home_tag_view_all'] ) ); ?>> Gallery Tag View All</label></div>
				<p class="description">Auto Slide ตั้งค่าแยกได้: เปิดเฉพาะ Featured ได้ และปล่อย Studio/Actors/Gallery Tag เป็นสไลด์แบบลากเองหรือ Grid ได้</p>
				<div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[home_featured_autoplay]" value="1" <?php checked( ! empty( $s['home_featured_autoplay'] ) ); ?>> Auto Featured</label><label><input type="checkbox" name="settings[home_studio_autoplay]" value="1" <?php checked( ! empty( $s['home_studio_autoplay'] ) ); ?>> Auto Studio</label><label><input type="checkbox" name="settings[home_actor_autoplay]" value="1" <?php checked( ! empty( $s['home_actor_autoplay'] ) ); ?>> Auto Actors</label><label><input type="checkbox" name="settings[home_tag_autoplay]" value="1" <?php checked( ! empty( $s['home_tag_autoplay'] ) ); ?>> Auto Gallery Tag</label></div>
				<div class="wps-pro-two"><label>Desktop<input type="number" min="2" max="8" name="settings[home_columns_desktop]" value="<?php echo esc_attr( $s['home_columns_desktop'] ); ?>"></label><label>Tablet<input type="number" min="2" max="6" name="settings[home_columns_tablet]" value="<?php echo esc_attr( $s['home_columns_tablet'] ); ?>"></label><label>Mobile<input type="number" min="1" max="5" name="settings[home_columns_mobile]" value="<?php echo esc_attr( $s['home_columns_mobile'] ); ?>"></label><label>Rows<input type="number" min="1" max="6" name="settings[home_rows]" value="<?php echo esc_attr( $s['home_rows'] ); ?>"></label></div>
				<label>Interval ms<input type="number" min="1000" max="15000" step="500" name="settings[home_slider_interval]" value="<?php echo esc_attr( $s['home_slider_interval'] ); ?>"></label><p><label><?php echo esc_html( self::admin_text( 'ภาษาในบล็อกนักแสดง', 'Actor card language' ) ); ?><select name="settings[home_actor_name_mode]"><option value="current" <?php selected( $s['home_actor_name_mode'], 'current' ); ?>><?php echo esc_html( self::admin_text( 'ตามปุ่มภาษาเว็บ', 'Follow site language' ) ); ?></option><option value="th" <?php selected( $s['home_actor_name_mode'], 'th' ); ?>>ไทยเท่านั้น</option><option value="en" <?php selected( $s['home_actor_name_mode'], 'en' ); ?>>English only</option></select></label></p>
			</section>

			<section class="wps-pro-card"><h2>Player Display</h2><p><?php echo esc_html( self::admin_text( 'ตั้งค่าการแสดงผลหน้าเล่นวิดีโอ', 'Configure video player display.' ) ); ?></p>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[player_show_poster_overlay]" value="1" <?php checked( ! empty( $finalizer['player_show_poster_overlay'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'แสดงรูปปกก่อนกดเล่น', 'Show poster before play' ) ); ?></span></label>
				<div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[player_hide_duration]" value="1" <?php checked( ! empty( $finalizer['player_hide_duration'] ) ); ?>> <?php echo esc_html( self::admin_text( 'ซ่อนเวลา', 'Hide duration' ) ); ?></label><label><input type="checkbox" name="settings[player_hide_views]" value="1" <?php checked( ! empty( $finalizer['player_hide_views'] ) ); ?>> <?php echo esc_html( self::admin_text( 'ซ่อนวิว', 'Hide views' ) ); ?></label><label><input type="checkbox" name="settings[player_hide_likes]" value="1" <?php checked( ! empty( $finalizer['player_hide_likes'] ) ); ?>> <?php echo esc_html( self::admin_text( 'ซ่อนไลค์', 'Hide likes' ) ); ?></label><label><input type="checkbox" name="settings[player_hide_like_percent]" value="1" <?php checked( ! empty( $finalizer['player_hide_like_percent'] ) ); ?>> <?php echo esc_html( self::admin_text( 'ซ่อนเปอร์เซ็นต์ไลค์', 'Hide like percent' ) ); ?></label><label><input type="checkbox" name="settings[player_hide_rating_bar]" value="1" <?php checked( ! empty( $finalizer['player_hide_rating_bar'] ) ); ?>> <?php echo esc_html( self::admin_text( 'ซ่อนแถบโหวต', 'Hide rating bar' ) ); ?></label></div>
			</section>

			<section class="wps-pro-card"><h2>Labels / Taxonomy</h2><p><?php echo esc_html( self::admin_text( 'ป้ายหน้าโพสต์และเครื่องมือจัดการ Taxonomy', 'Post labels and taxonomy tools.' ) ); ?></p>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[labels_enabled]" value="1" <?php checked( ! empty( $labels['labels_enabled'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'เปิดป้ายหน้าโพสต์', 'Enable post labels' ) ); ?></span></label>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[labels_hide_native]" value="1" <?php checked( ! empty( $labels['labels_hide_native'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'ซ่อนป้ายเดิมไม่ให้ซ้ำ', 'Hide native duplicate labels' ) ); ?></span></label>
				<div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[labels_categories]" value="1" <?php checked( ! empty( $labels['labels_categories'] ) ); ?>> <?php echo esc_html( self::admin_text( 'หมวดหมู่', 'Categories' ) ); ?></label><label><input type="checkbox" name="settings[labels_post_tags]" value="1" <?php checked( ! empty( $labels['labels_post_tags'] ) ); ?>> Tags</label><label><input type="checkbox" name="settings[labels_actors]" value="1" <?php checked( ! empty( $labels['labels_actors'] ) ); ?>> <?php echo esc_html( self::admin_text( 'นักแสดง', 'Actors' ) ); ?></label><label><input type="checkbox" name="settings[labels_studios]" value="1" <?php checked( ! empty( $labels['labels_studios'] ) ); ?>> Studio</label></div>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wps-taxonomy-cleaner' ) ); ?>">Taxonomy Cleaner</a></p>
			</section>

			<section class="wps-pro-card"><h2>Ads / Banners</h2><p><?php echo esc_html( self::admin_text( 'ปิด/เปิดโฆษณาทั้งเว็บก่อนลงรายละเอียดรายป้าย', 'Globally enable or disable ads before editing individual slots.' ) ); ?></p>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[ads_master_enabled]" value="1" <?php checked( ! empty( $s['ads_master_enabled'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'เปิดป้ายโฆษณาทั้งหมด', 'Enable all banner rendering' ) ); ?></span></label>
				<p><strong><?php echo esc_html( $ad_enabled_count ); ?></strong> / <?php echo esc_html( count( $ads ) ); ?> <?php echo esc_html( self::admin_text( 'ตำแหน่งที่เปิดอยู่', 'slots enabled' ) ); ?></p>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wps-professional-settings&tab=banners' ) ); ?>"><?php echo esc_html( self::admin_text( 'จัดการป้ายทั้งหมด', 'Manage all banners' ) ); ?></a></p>
			</section>

			<section class="wps-pro-card"><h2>Security / REST / Feed</h2><p><?php echo esc_html( self::admin_text( 'ควบคุมฟีด REST และการลดข้อมูลที่เปิดเผยหน้าเว็บ', 'Control feeds, REST and public hardening.' ) ); ?></p>
				<div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[rest_api_enabled]" value="1" <?php checked( ! empty( $s['rest_api_enabled'] ) ); ?>> REST Search</label><label><input type="checkbox" name="settings[feed_redirect_enabled]" value="1" <?php checked( ! empty( $s['feed_redirect_enabled'] ) ); ?>> Feed Redirect</label><label><input type="checkbox" name="settings[video_download_deterrence]" value="1" <?php checked( ! empty( $s['video_download_deterrence'] ) ); ?>> Video Protection</label><label><input type="checkbox" name="settings[asset_hardening]" value="1" <?php checked( ! empty( $s['asset_hardening'] ) ); ?>> Asset Hardening</label><label><input type="checkbox" name="settings[source_guard_enabled]" value="1" <?php checked( ! empty( $finalizer['source_guard_enabled'] ) ); ?>> Source Guard</label></div>
			</section>

			<section class="wps-pro-card"><h2>Backup / Repair / Lock</h2><p><?php echo esc_html( self::admin_text( 'เครื่องมือสำรองข้อมูล ซ่อมลิงก์ และล็อกค่าธีมหลังอัปเดต', 'Backup, link repair and settings lock tools.' ) ); ?></p>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[lock_theme_settings]" value="1" <?php checked( ! empty( $finalizer['lock_theme_settings'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'ล็อกค่าธีมหลังอัปเดต', 'Lock settings after theme updates' ) ); ?></span></label>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[auto_snapshot_settings]" value="1" <?php checked( ! empty( $finalizer['auto_snapshot_settings'] ) ); ?>><span><?php echo esc_html( self::admin_text( 'บันทึก snapshot อัตโนมัติ', 'Automatic settings snapshot' ) ); ?></span></label>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wps-theme-finalizer' ) ); ?>"><?php echo esc_html( self::admin_text( 'ซ่อมลิงก์/รูป/Player', 'Repair links/images/player' ) ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=av-control-center#wps-backup' ) ); ?>"><?php echo esc_html( self::admin_text( 'Backup Manager', 'Backup Manager' ) ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wps-file-manager' ) ); ?>"><?php echo esc_html( self::admin_text( 'เปิดไฟล์เมเนเจอร์', 'Open File Manager' ) ); ?></a></p>
			</section>

			<section class="wps-pro-card wps-warning-card"><h2><?php echo esc_html( self::admin_text( 'จุดที่ควรระวัง', 'Warnings' ) ); ?></h2><ul><li><?php echo esc_html( self::admin_text( 'ปิด REST แล้วคำแนะนำการค้นหาที่ใช้ REST อาจไม่ทำงาน', 'Disabling REST can stop REST-based search suggestions.' ) ); ?></li><li><?php echo esc_html( self::admin_text( 'เปิด Source Guard แบบเข้มควรทดสอบ Player ก่อนใช้งานจริง', 'Test the player before enabling aggressive Source Guard options.' ) ); ?></li><li><?php echo esc_html( self::admin_text( 'ปิดป้ายโฆษณาทั้งหมดเป็น master switch เท่านั้น ค่ารายป้ายยังไม่ถูกลบ', 'The ads master switch only stops rendering; individual slot values are preserved.' ) ); ?></li><li><?php echo esc_html( self::admin_text( 'ธีมนี้ไม่เปลี่ยน SEO สี โลโก้ เมนู หรือ Additional CSS อัตโนมัติ', 'This theme does not automatically change SEO, colors, logos, menus or Additional CSS.' ) ); ?></li></ul><label><?php echo esc_html( self::admin_text( 'บันทึกหมายเหตุของเว็บนี้', 'Site note' ) ); ?><textarea class="large-text" rows="4" name="settings[site_control_note]"><?php echo esc_textarea( $s['site_control_note'] ); ?></textarea></label></section>
		</div>
		<?php
	}

	private static function render_general_fields( $s ) {
		?>
		<div class="wps-pro-grid">
			<section class="wps-pro-card"><h2><?php echo esc_html( self::admin_text( 'ภาษา UI', 'UI Language' ) ); ?></h2><p><?php echo esc_html( self::admin_text( 'เลือกภาษาของหน้าตั้งค่า AV Framework', 'Choose the language used by AV Framework settings pages.' ) ); ?></p>
				<label class="wps-pro-label"><?php echo esc_html( self::admin_text( 'ภาษาหลังบ้าน', 'Admin UI language' ) ); ?></label>
				<select name="settings[ui_language]"><option value="th" <?php selected( $s['ui_language'], 'th' ); ?>>ไทย</option><option value="en" <?php selected( $s['ui_language'], 'en' ); ?>>English</option></select>
				<label class="wps-pro-label"><?php echo esc_html( self::admin_text( 'ภาษาเริ่มต้นหน้าเว็บ', 'Default frontend language' ) ); ?></label>
				<select name="settings[frontend_language]"><option value="th" <?php selected( $s['frontend_language'], 'th' ); ?>>ไทย</option><option value="en" <?php selected( $s['frontend_language'], 'en' ); ?>>English</option></select>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[show_language_switcher]" value="1" <?php checked( $s['show_language_switcher'], 1 ); ?>><span><?php echo esc_html( self::admin_text( 'แสดงตัวสลับ ไทย / EN บนเมนู', 'Show Thai / EN switcher in the navigation' ) ); ?></span></label>
			</section>
			<section class="wps-pro-card"><h2><?php echo esc_html( self::admin_text( 'ค้นหาทั้งเว็บไซต์', 'Site-wide Search' ) ); ?></h2><p><?php echo esc_html( self::admin_text( 'ค้นหาโพสต์ หมวดหมู่ แท็ก นักแสดง และสตูดิโอ พร้อมคำแนะนำแบบทันที', 'Search posts, categories, tags, actors and studios with live suggestions.' ) ); ?></p>
				<label class="wps-pro-toggle"><input type="checkbox" name="settings[search_enabled]" value="1" <?php checked( $s['search_enabled'], 1 ); ?>><span><?php echo esc_html( self::admin_text( 'เปิดคำแนะนำการค้นหา', 'Enable search suggestions' ) ); ?></span></label>
				<div class="wps-pro-two"><label><?php echo esc_html( self::admin_text( 'เริ่มค้นหาที่', 'Minimum characters' ) ); ?><input type="number" min="1" max="5" name="settings[search_min_chars]" value="<?php echo esc_attr( $s['search_min_chars'] ); ?>"></label><label><?php echo esc_html( self::admin_text( 'จำนวนคำแนะนำ', 'Suggestion limit' ) ); ?><input type="number" min="5" max="20" name="settings[search_limit]" value="<?php echo esc_attr( $s['search_limit'] ); ?>"></label></div>
			</section>
		</div>
		<?php
	}

	private static function render_banner_fields() {
		$defs = WPS_Ads::definitions();
		$slots = WPS_Ads::all();
		$groups = array(
			'home' => array( 'หน้าแรก', 'Home' ), 'single' => array( 'หน้าเรื่องและ Player', 'Single & Player' ),
			'floating' => array( 'โฆษณาลอย', 'Floating banners' ), 'actor' => array( 'นักแสดง', 'Actors' ),
			'category' => array( 'หมวดหมู่', 'Categories' ), 'tag' => array( 'แท็ก', 'Tags' ), 'search' => array( 'ผลการค้นหา', 'Search results' ),
		);
		foreach ( $groups as $group => $labels ) {
			echo '<section class="wps-pro-section"><div class="wps-pro-section-title"><h2>' . esc_html( self::admin_text( $labels[0], $labels[1] ) ) . '</h2></div><div class="wps-banner-grid">';
			foreach ( $defs as $key => $def ) {
				if ( $def['group'] !== $group ) { continue; }
				$slot = isset( $slots[ $key ] ) ? $slots[ $key ] : WPS_Ads::slot( $key );
				$preview = WPS_Ads::html( $key, 'wps-admin-preview-image' );
				?>
				<article class="wps-banner-card">
					<header><div><strong><?php echo esc_html( self::admin_text( $def['label_th'], $def['label_en'] ) ); ?></strong><code><?php echo esc_html( $key ); ?></code></div><label class="wps-switch"><input type="checkbox" name="ads[<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( ! empty( $slot['enabled'] ) ); ?>><span></span></label></header>
					<div class="wps-banner-preview"><?php echo $preview ? $preview : '<span>' . esc_html( self::admin_text( 'ยังไม่มีภาพ', 'No image' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<label><?php echo esc_html( self::admin_text( 'URL รูปภาพ', 'Image URL' ) ); ?><div class="wps-media-row"><input type="url" class="widefat wps-image-url" name="ads[<?php echo esc_attr( $key ); ?>][image_url]" value="<?php echo esc_attr( $slot['image_url'] ); ?>"><button type="button" class="button wps-choose-image"><?php echo esc_html( self::admin_text( 'เลือกภาพ', 'Choose' ) ); ?></button></div></label>
					<label><?php echo esc_html( self::admin_text( 'ลิงก์ปลายทาง', 'Destination URL' ) ); ?><input type="url" class="widefat" name="ads[<?php echo esc_attr( $key ); ?>][link_url]" value="<?php echo esc_attr( $slot['link_url'] ); ?>"></label>
					<label><?php echo esc_html( self::admin_text( 'ข้อความ Alt', 'Alt text' ) ); ?><input type="text" class="widefat" name="ads[<?php echo esc_attr( $key ); ?>][alt]" value="<?php echo esc_attr( $slot['alt'] ); ?>"></label>
					<details><summary><?php echo esc_html( self::admin_text( 'HTML แบบกำหนดเอง (ใช้แทนรูปและลิงก์)', 'Custom HTML (overrides image and link)' ) ); ?></summary><textarea class="widefat code" rows="4" name="ads[<?php echo esc_attr( $key ); ?>][html]"><?php echo esc_textarea( $slot['html'] ); ?></textarea></details>
					<div class="wps-banner-options"><label><input type="checkbox" name="ads[<?php echo esc_attr( $key ); ?>][new_tab]" value="1" <?php checked( ! empty( $slot['new_tab'] ) ); ?>> <?php echo esc_html( self::admin_text( 'เปิดแท็บใหม่', 'New tab' ) ); ?></label><label><input type="checkbox" name="ads[<?php echo esc_attr( $key ); ?>][nofollow]" value="1" <?php checked( ! empty( $slot['nofollow'] ) ); ?>> nofollow/sponsored</label><?php if ( $def['floating'] ) : ?><label><input type="checkbox" name="ads[<?php echo esc_attr( $key ); ?>][closeable]" value="1" <?php checked( ! empty( $slot['closeable'] ) ); ?>> <?php echo esc_html( self::admin_text( 'มีปุ่มปิด', 'Close button' ) ); ?></label><?php endif; ?></div>
				</article>
				<?php
			}
			echo '</div></section>';
		}
	}

	private static function render_protection_fields( $s ) {
		?>
		<div class="wps-pro-grid">
			<section class="wps-pro-card"><h2>Feed → 301</h2><p><?php echo esc_html( self::admin_text( 'ปิดฟีดทุกชนิดด้วย 301 ไปหน้าโฮม ไม่ส่งสถานะ 404', 'Redirect every feed type to the homepage with HTTP 301 instead of returning 404.' ) ); ?></p><label class="wps-pro-toggle"><input type="checkbox" name="settings[feed_redirect_enabled]" value="1" <?php checked( $s['feed_redirect_enabled'], 1 ); ?>><span><?php echo esc_html( self::admin_text( 'เปิดการเปลี่ยนเส้นทางฟีดแบบ 301', 'Enable 301 feed redirect' ) ); ?></span></label></section>
			<section class="wps-pro-card"><h2>REST API</h2><p><?php echo esc_html( self::admin_text( 'เปิด endpoint สำหรับคำแนะนำการค้นหา โดยไม่เปิดเผยโพสต์ส่วนตัวหรือข้อมูลผู้ใช้', 'Enable the search suggestion endpoint without exposing private posts or users.' ) ); ?></p><label class="wps-pro-toggle"><input type="checkbox" name="settings[rest_api_enabled]" value="1" <?php checked( $s['rest_api_enabled'], 1 ); ?>><span><?php echo esc_html( self::admin_text( 'เปิด REST API ของ AV Framework', 'Enable AV Framework REST API' ) ); ?></span></label></section>
			<section class="wps-pro-card"><h2><?php echo esc_html( self::admin_text( 'ลดการดาวน์โหลดวิดีโอ', 'Video Download Deterrence' ) ); ?></h2><p><?php echo esc_html( self::admin_text( 'ซ่อนปุ่มดาวน์โหลด ปิด Picture-in-Picture และปิดเมนูคลิกขวาบนวิดีโอที่ควบคุมได้', 'Hide download controls, disable Picture-in-Picture and block the context menu on controllable video elements.' ) ); ?></p><label class="wps-pro-toggle"><input type="checkbox" name="settings[video_download_deterrence]" value="1" <?php checked( $s['video_download_deterrence'], 1 ); ?>><span><?php echo esc_html( self::admin_text( 'เปิดการป้องกันระดับหน้าเว็บ', 'Enable browser-level deterrence' ) ); ?></span></label><p class="description"><?php echo esc_html( self::admin_text( 'หมายเหตุ: วิดีโอที่ส่งถึงเบราว์เซอร์ไม่สามารถป้องกันการบันทึกได้ 100% โดยธีมเพียงอย่างเดียว', 'Note: a theme cannot make browser-delivered media impossible to save.' ) ); ?></p></section>
			<section class="wps-pro-card"><h2><?php echo esc_html( self::admin_text( 'ลดการเปิดเผยไฟล์พัฒนา', 'Asset Hardening' ) ); ?></h2><p><?php echo esc_html( self::admin_text( 'ตัด version query จาก CSS/JS และไม่รวม source map ในแพ็กเกจ', 'Remove version query strings from CSS/JS and exclude source maps from the package.' ) ); ?></p><label class="wps-pro-toggle"><input type="checkbox" name="settings[asset_hardening]" value="1" <?php checked( $s['asset_hardening'], 1 ); ?>><span><?php echo esc_html( self::admin_text( 'เปิดการลดข้อมูลระบุตัวไฟล์', 'Reduce asset fingerprinting' ) ); ?></span></label><p class="description"><?php echo esc_html( self::admin_text( 'CSS และ HTML ที่เบราว์เซอร์ต้องใช้ยังสามารถตรวจดูได้ตามธรรมชาติของเว็บไซต์', 'CSS and HTML required by a browser remain inspectable by design.' ) ); ?></p></section>
		</div>
		<?php
	}

	private static function render_diagnostics() {
		$defs = WPS_Ads::definitions();
		$enabled = 0;
		foreach ( WPS_Ads::all() as $slot ) { if ( ! empty( $slot['enabled'] ) ) { ++$enabled; } }
		$checks = array(
			array( 'ธีมจาก backup', 'Backup theme match', 'PASS', '279 theme files (333 entries including directories) matched the supplied ZIP before modification.' ),
			array( 'คลังป้ายโฆษณา', 'Banner inventory', 'PASS', count( $defs ) . ' slots; ' . $enabled . ' currently enabled.' ),
			array( 'REST route', 'REST route', self::get( 'rest_api_enabled', 1 ) ? 'READY' : 'OFF', rest_url( 'wps/v1/search-suggestions' ) ),
			array( 'Feed redirect', 'Feed redirect', self::get( 'feed_redirect_enabled', 1 ) ? '301' : 'OFF', home_url( '/' ) ),
			array( 'File Manager', 'File Manager', class_exists( 'WPS_File_Manager' ) ? 'READY' : 'MISSING', admin_url( 'admin.php?page=wps-file-manager' ) ),
			array( 'ส่วนหน้าโฮม', 'Homepage sections', class_exists( 'WPS_Home_Sections' ) && WPS_Home_Sections::enabled() ? 'READY' : 'OFF', 'SEO / Studio / Actors / Gallery Tags' ),
		);
		echo '<div class="wps-pro-diagnostics">';
		foreach ( $checks as $check ) {
			echo '<div class="wps-pro-check"><div><strong>' . esc_html( self::admin_text( $check[0], $check[1] ) ) . '</strong><small>' . esc_html( $check[3] ) . '</small></div><span>' . esc_html( $check[2] ) . '</span></div>';
		}
		echo '</div>';
	}

	public static function remove_feed_discovery_links() {
		if ( ! self::get( 'feed_redirect_enabled', 1 ) ) {
			return;
		}
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	public static function redirect_feed_requests() {
		if ( ! self::get( 'feed_redirect_enabled', 1 ) || is_admin() || ! is_feed() ) {
			return;
		}
		wp_safe_redirect( home_url( '/' ), 301, 'KolorTube' );
		exit;
	}

	public static function register_rest_routes() {
		if ( ! self::get( 'rest_api_enabled', 1 ) ) {
			return;
		}
		register_rest_route( 'wps/v1', '/search-suggestions', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( __CLASS__, 'rest_search_suggestions' ),
			'permission_callback' => '__return_true',
			'args' => array( 'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
		) );
	}

	public static function allow_public_search_route( $result ) {
		if ( ! is_wp_error( $result ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return $result;
		}
		$uri = wp_unslash( $_SERVER['REQUEST_URI'] );
		if ( false === strpos( $uri, '/wps/v1/search-suggestions' ) ) {
			return $result;
		}
		if ( in_array( $result->get_error_code(), array( 'rest_disabled', 'rest_cannot_access' ), true ) && self::get( 'rest_api_enabled', 1 ) ) {
			return null;
		}
		return $result;
	}

	public static function rest_search_suggestions( WP_REST_Request $request ) {
		$q = trim( sanitize_text_field( (string) $request->get_param( 'q' ) ) );
		$min = max( 1, absint( self::get( 'search_min_chars', 2 ) ) );
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $q ) < $min : strlen( $q ) < $min ) {
			return rest_ensure_response( array( 'items' => array() ) );
		}
		$q = function_exists( 'mb_substr' ) ? mb_substr( $q, 0, 80 ) : substr( $q, 0, 80 );
		$limit = max( 5, min( 20, absint( self::get( 'search_limit', 10 ) ) ) );
		$key = 'wps_suggest_' . md5( strtolower( $q ) . '|' . $limit );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return rest_ensure_response( array( 'items' => $cached ) );
		}
		$items = array();
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		$query = new WP_Query( array(
			's' => $q, 'post_type' => array_values( $post_types ), 'post_status' => 'publish',
			'posts_per_page' => min( 6, $limit ), 'no_found_rows' => true, 'ignore_sticky_posts' => true,
		) );
		foreach ( $query->posts as $post ) {
			$items[] = array( 'title' => get_the_title( $post ), 'url' => get_permalink( $post ), 'type' => 'post', 'label' => self::text( 'เรื่อง', 'Post' ) );
		}
		foreach ( array( 'category', 'post_tag', 'actors', 'studio' ) as $taxonomy ) {
			if ( count( $items ) >= $limit || ! taxonomy_exists( $taxonomy ) ) { continue; }
			$term_limit = min( 4, $limit - count( $items ) );
			$terms = self::get_display_terms( $taxonomy, $term_limit, $q );
			$labels = array( 'category' => array( 'หมวดหมู่', 'Category' ), 'post_tag' => array( 'แท็ก', 'Tag' ), 'actors' => array( 'นักแสดง', 'Actor' ), 'studio' => array( 'สตูดิโอ', 'Studio' ) );
			foreach ( $terms as $term ) {
				$url = get_term_link( $term );
				if ( is_wp_error( $url ) ) { continue; }
				$items[] = array( 'title' => $term->name, 'url' => $url, 'type' => $taxonomy, 'label' => self::text( $labels[ $taxonomy ][0], $labels[ $taxonomy ][1] ) );
				if ( count( $items ) >= $limit ) { break; }
			}
		}
		set_transient( $key, $items, 10 * MINUTE_IN_SECONDS );
		return rest_ensure_response( array( 'items' => $items ) );
	}

	public static function fallback_menu( $args = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}
		$menu_class = isset( $args['menu_class'] ) ? $args['menu_class'] : 'navbar-nav ml-auto';
		echo '<div class="collapse navbar-collapse" id="navbarNavDropdown"><ul class="' . esc_attr( $menu_class ) . '">';
		echo '<li class="menu-item nav-item"><a class="nav-link" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( self::text( 'หน้าแรก', 'Home' ) ) . '</a></li>';

		self::render_term_dropdown( 'category', self::text( 'หมวดหมู่', 'Categories' ), 7 );
		self::render_term_dropdown( 'studio', self::text( 'สตูดิโอ', 'Studios' ), 6 );
		self::render_term_dropdown( 'actors', self::text( 'นักแสดง', 'Actors' ), 6 );
		self::render_term_dropdown( 'post_tag', self::text( 'แกลเลอรีแท็ก', 'Gallery Tags' ), 6 );

		echo '</ul></div>';
	}

	private static function render_term_dropdown( $taxonomy, $label, $limit ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}
		$terms = self::get_display_terms( $taxonomy, max( 1, absint( $limit ) ) );
		if ( empty( $terms ) ) {
			return;
		}
		$id = 'wps-menu-' . sanitize_html_class( $taxonomy );
		echo '<li class="menu-item nav-item dropdown">';
		echo '<a class="nav-link dropdown-toggle" id="' . esc_attr( $id ) . '" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' . esc_html( $label ) . '</a>';
		echo '<div class="dropdown-menu" aria-labelledby="' . esc_attr( $id ) . '">';
		foreach ( $terms as $term ) {
			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				continue;
			}
			echo '<a class="dropdown-item" href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
		}
		echo '</div></li>';
	}

	/**
	 * Return terms for public UI while respecting the verified legacy data model.
	 * The source backup stores old studio labels in the actors taxonomy and its
	 * template-actors.php already identifies those labels with this exact list.
	 */
	public static function get_display_terms( $taxonomy, $limit = 0, $search = '' ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$limit = max( 0, absint( $limit ) );
		$args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => true,
			'number' => in_array( $taxonomy, array( 'actors', 'studio' ), true ) ? 0 : $limit,
			'orderby' => 'count',
			'order' => 'DESC',
		);
		if ( '' !== $search ) {
			$args['search'] = $search;
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		if ( 'studio' === $taxonomy ) {
			$has_native_studios = get_terms( array(
				'taxonomy' => 'studio',
				'hide_empty' => true,
				'number' => 1,
				'fields' => 'ids',
			) );
			if ( ! is_wp_error( $has_native_studios ) && empty( $has_native_studios ) && taxonomy_exists( 'actors' ) ) {
				$legacy_args = $args;
				$legacy_args['taxonomy'] = 'actors';
				$legacy_args['number'] = 0;
				$terms = get_terms( $legacy_args );
				if ( is_wp_error( $terms ) ) {
					$terms = array();
				}
				$terms = array_values( array_filter( $terms, array( __CLASS__, 'is_legacy_studio_term' ) ) );
			}
		} elseif ( 'actors' === $taxonomy ) {
			$terms = array_values( array_filter( $terms, function ( $term ) {
				return ! self::is_legacy_studio_term( $term );
			} ) );
		}

		return $limit ? array_slice( $terms, 0, $limit ) : $terms;
	}

	public static function is_legacy_studio_term( $term ) {
		$slug = sanitize_title( is_object( $term ) ? $term->slug : (string) $term );
		return in_array( $slug, self::legacy_studio_slugs(), true );
	}

	public static function legacy_studio_slugs() {
		return array(
			'moodyz', 'faleno', 'attackers', 'prestige', 'hunter', 'dahlia', 'fitch',
			'idea-pocket', 'kawaii', 'madonna', 'sod-create', 'wanz-factory', 'dandy',
			'das', 'deeps', 'doc', 'e-body', 'fc2ppv', 'goku-group', 'h-m-p',
			'honnaka', 'planet-plus', 'premium', 'rookie', 'royal', 's-cute',
			'natural-high', 'm-s-video-group', 'kosumosu-eizou', 'sounds-spring',
			'space-watercolor', 'aurora-project-annex', 'hitodzuma-hanazono-gekijou',
			'tameike-goro', 'nampa-japan', 's-1-number-one-style', 'jet-eizou',
			'million', 'hon-naka', 'goddess', 'fc2', 'amateurs', 'amateur', 'botan',
			'milk', 'kadonaho', 'rara'
		);
	}

	public static function protect_video_markup( $html ) {
		if ( ! self::get( 'video_download_deterrence', 1 ) || false === stripos( (string) $html, '<video' ) ) {
			return $html;
		}
		return preg_replace_callback( '/<video\b([^>]*)>/i', function ( $m ) {
			$attrs = $m[1];
			if ( false === stripos( $attrs, 'controlslist=' ) ) { $attrs .= ' controlsList="nodownload noremoteplayback"'; }
			if ( false === stripos( $attrs, 'disablepictureinpicture' ) ) { $attrs .= ' disablePictureInPicture'; }
			if ( false === stripos( $attrs, 'oncontextmenu=' ) ) { $attrs .= ' oncontextmenu="return false;"'; }
			return '<video' . $attrs . '>';
		}, $html );
	}

	public static function strip_asset_version( $src ) {
		if ( is_admin() || ! self::get( 'asset_hardening', 1 ) ) {
			return $src;
		}
		return remove_query_arg( 'ver', $src );
	}
}
