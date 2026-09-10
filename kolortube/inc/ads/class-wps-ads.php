<?php
/** Complete advertising inventory and rendering helpers. */
defined( 'ABSPATH' ) || exit;

final class WPS_Ads {
	const OPTION = 'wps_ad_slots';

	public static function register() {
		add_action( 'after_setup_theme', array( __CLASS__, 'maybe_migrate' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'preload_active_images' ), 1 );
	}

	public static function definitions() {
		$uri = get_template_directory_uri();
		$happy2 = '';
		$happy3 = '';
		$happy6 = '';

		return array(
			'ads_home_before_list' => self::def( 'home', 'หน้าแรก: ก่อนรายการ', 'Home: before list', '' ),
			'ads_home_inside_list' => self::def( 'home', 'หน้าแรก: ภายในรายการ', 'Home: inside list', $happy6 ),
			'ads_home_after_list' => self::def( 'home', 'หน้าแรก: หลังรายการ', 'Home: after list', $happy3 ),
			'ads_home_inside_related_videos_list' => self::def( 'single', 'รายการแนะนำ: ภายในรายการ', 'Related videos: inside list', $happy2 ),

			'ads_single_video_page_in_player_1' => self::def( 'single', 'หน้าเรื่อง: ใน Player ช่อง 1', 'Single: in player 1', $happy2 ),
			'ads_single_video_page_in_player_2' => self::def( 'single', 'หน้าเรื่อง: ใน Player ช่อง 2', 'Single: in player 2', $happy2 ),
			'ads_single_video_page_under_player' => self::def( 'single', 'หน้าเรื่อง: ใต้ Player', 'Single: under player', $happy3 ),
			'ads_single_video_page_beside_player_1' => self::def( 'single', 'หน้าเรื่อง: ข้าง Player ช่อง 1', 'Single: beside player 1', $happy2 ),
			'ads_single_video_page_beside_player_2' => self::def( 'single', 'หน้าเรื่อง: ข้าง Player ช่อง 2', 'Single: beside player 2', $happy2 ),
			'ads_single_video_page_before_related_videos' => self::def( 'single', 'หน้าเรื่อง: ก่อนรายการแนะนำ', 'Single: before related videos', $happy3 ),

		'ads_single_static_top' => self::image_def( 'single', 'หน้าเรื่อง: ป้ายบน Player', 'Single: top banner', '', self::default_banner_url( 'bannerK1.gif' ), 'Banner K1' ),
		'ads_single_static_bottom_1' => self::image_def( 'single', 'หน้าเรื่อง: ป้ายใต้ Player 1', 'Single: bottom banner 1', '', self::default_banner_url( 'FREE-50.gif' ), 'FREE-50' ),
		'ads_single_static_bottom_2' => self::image_def( 'single', 'หน้าเรื่อง: ป้ายใต้ Player 2', 'Single: bottom banner 2', '', self::default_banner_url( 'FREE50king.gif' ), 'FREE50 King' ),
		'ads_single_static_bottom_3' => self::image_def( 'single', 'หน้าเรื่อง: ป้ายใต้ Player 3', 'Single: bottom banner 3', '', self::default_banner_url( 'av-ckdm0fzd-gtl00.gif' ), 'Promotion' ),
		'ads_single_static_bottom_4' => self::image_def( 'single', 'หน้าเรื่อง: ป้ายใต้ Player 4', 'Single: bottom banner 4', '', self::default_banner_url( 'JAV-ซับไทย-HD.gif' ), 'Promotion' ),
		'ads_floating_left' => self::image_def( 'floating', 'โฆษณาลอยซ้าย', 'Floating left', 'https://orll.cc/kingof50', self::default_banner_url( 'FREE50king.gif' ), 'FREE50 King', true ),
		'ads_floating_right' => self::image_def( 'floating', 'โฆษณาลอยขวา', 'Floating right', 'https://orll.cc/westblue50', self::default_banner_url( 'FREE-50.gif' ), 'FREE-50', true ),
		// Kept as an empty legacy setting for backward-compatible admin data only.
		// This theme intentionally does not render a floating-bottom banner.
		'ads_floating_bottom' => self::image_def( 'floating', 'โฆษณาลอยด้านล่าง', 'Floating bottom', '', '', '', true ),

			'ads_actor_page_before_list' => self::def( 'actor', 'หน้านักแสดง: ก่อนรายการ', 'Actor: before list', '' ),
			'ads_actor_page_inside_list' => self::def( 'actor', 'หน้านักแสดง: ภายในรายการ', 'Actor: inside list', $happy6 ),
			'ads_actor_page_after_list' => self::def( 'actor', 'หน้านักแสดง: หลังรายการ', 'Actor: after list', $happy3 ),
			'ads_category_page_before_list' => self::def( 'category', 'หน้าหมวดหมู่: ก่อนรายการ', 'Category: before list', '' ),
			'ads_category_page_inside_list' => self::def( 'category', 'หน้าหมวดหมู่: ภายในรายการ', 'Category: inside list', $happy6 ),
			'ads_category_page_after_list' => self::def( 'category', 'หน้าหมวดหมู่: หลังรายการ', 'Category: after list', $happy3 ),
			'ads_tag_page_before_list' => self::def( 'tag', 'หน้าแท็ก: ก่อนรายการ', 'Tag: before list', '' ),
			'ads_tag_page_inside_list' => self::def( 'tag', 'หน้าแท็ก: ภายในรายการ', 'Tag: inside list', $happy6 ),
			'ads_tag_page_after_list' => self::def( 'tag', 'หน้าแท็ก: หลังรายการ', 'Tag: after list', $happy3 ),
			'ads_search_result_page_before_list' => self::def( 'search', 'หน้าค้นหา: ก่อนรายการ', 'Search: before list', '' ),
			'ads_search_result_page_inside_list' => self::def( 'search', 'หน้าค้นหา: ภายในรายการ', 'Search: inside list', $happy6 ),
			'ads_search_result_page_after_list' => self::def( 'search', 'หน้าค้นหา: หลังรายการ', 'Search: after list', $happy3 ),
		);
	}

	private static function default_banner_url( $filename ) {
		if ( defined( 'WP_MY_BOSS_MAIN_FILE' ) ) {
			return plugins_url( 'assets/default-banners/single/' . $filename, WP_MY_BOSS_MAIN_FILE );
		}
		return '';
	}

	private static function def( $group, $th, $en, $html ) {
		return array(
			'group' => $group, 'label_th' => $th, 'label_en' => $en,
			'default_html' => $html, 'default_link' => '', 'default_image' => '',
			'default_alt' => '', 'floating' => false, 'closeable' => false,
		);
	}

	private static function image_def( $group, $th, $en, $link, $image, $alt, $floating = false ) {
		return array(
			'group' => $group, 'label_th' => $th, 'label_en' => $en,
			'default_html' => '', 'default_link' => $link, 'default_image' => $image,
			'default_alt' => $alt, 'floating' => $floating, 'closeable' => $floating,
		);
	}

	public static function keys() {
		return array_keys( self::definitions() );
	}

	public static function maybe_migrate() {
		if ( false !== get_option( self::OPTION, false ) ) {
			self::maybe_seed_floating_defaults();
			return;
		}
		$slots = array();
		$theme_mods = get_theme_mods();
		$theme_mods = is_array( $theme_mods ) ? $theme_mods : array();
		foreach ( self::definitions() as $key => $def ) {
			$has_legacy_value = array_key_exists( $key, $theme_mods );
			$legacy = $has_legacy_value ? (string) $theme_mods[ $key ] : '';
			$slot = array(
				'enabled' => 0,
				'link_url' => $def['default_link'],
				'image_url' => $def['default_image'],
				'alt' => $def['default_alt'],
				'html' => '',
				'new_tab' => 1,
				'nofollow' => 1,
				'closeable' => $def['closeable'] ? 1 : 0,
			);
			// An explicitly saved empty theme-mod means the banner was disabled in the backup.
			$source = $has_legacy_value ? $legacy : $def['default_html'];
			if ( 'ads_floating_right' === $key && class_exists( 'WPS_Control_Center_42' ) ) {
				$slot['link_url'] = (string) WPS_Control_Center_42::get( 'promo_right_link_url', $slot['link_url'] );
				$slot['image_url'] = (string) WPS_Control_Center_42::get( 'promo_right_image_url', $slot['image_url'] );
			}
			if ( '' !== trim( $source ) ) {
				$parsed = self::parse_simple_image_ad( $source );
				if ( $parsed ) {
					$slot = array_merge( $slot, $parsed );
				} else {
					$slot['html'] = $source;
				}
			}
			$slot['enabled'] = ( '' !== trim( $slot['html'] ) || '' !== trim( $slot['image_url'] ) ) ? 1 : 0;
			$slots[ $key ] = $slot;
		}
		update_option( self::OPTION, $slots, false );
		update_option( 'wps_ad_inventory_source', 'backup-theme-files-and-theme-mods', false );
		update_option( 'wps_floating_defaults_seeded_v1', 1, false );
	}

	/**
	 * Adds the two portable floating-banner defaults once for sites created
	 * before these slots existed. Existing configured banners are never changed.
	 */
	private static function maybe_seed_floating_defaults() {
		if ( get_option( 'wps_floating_defaults_seeded_v1', false ) ) {
			return;
		}

		$slots = get_option( self::OPTION, array() );
		if ( ! is_array( $slots ) ) {
			return;
		}

		$changed = false;
		foreach ( array( 'ads_floating_left', 'ads_floating_right' ) as $key ) {
			$current = isset( $slots[ $key ] ) && is_array( $slots[ $key ] ) ? $slots[ $key ] : array();
			$has_value = '' !== trim( (string) ( $current['image_url'] ?? '' ) ) || '' !== trim( (string) ( $current['link_url'] ?? '' ) ) || '' !== trim( (string) ( $current['html'] ?? '' ) );
			if ( $has_value ) {
				continue;
			}

			$def = self::definitions()[ $key ];
			$slots[ $key ] = array_merge( $current, array(
				'enabled'   => 1,
				'link_url'  => $def['default_link'],
				'image_url' => $def['default_image'],
				'alt'       => $def['default_alt'],
				'html'      => '',
				'new_tab'   => 1,
				'nofollow'  => 1,
				'closeable' => 1,
			) );
			$changed = true;
		}

		if ( $changed ) {
			update_option( self::OPTION, $slots, false );
		}
		update_option( 'wps_floating_defaults_seeded_v1', 1, false );
	}

	private static function parse_simple_image_ad( $html ) {
		if ( ! preg_match( '/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $img ) ) {
			return array();
		}
		$link = '';
		$alt = '';
		if ( preg_match( '/<a\b[^>]*\bhref=["\']([^"\']+)["\']/i', $html, $a ) ) {
			$link = $a[1];
		}
		if ( preg_match( '/<img\b[^>]*\balt=["\']([^"\']*)["\']/i', $html, $a ) ) {
			$alt = $a[1];
		}
		$image = 0 === strpos( $img[1], '//' ) ? 'https:' . $img[1] : $img[1];
		return array( 'link_url' => $link, 'image_url' => $image, 'alt' => $alt, 'html' => '' );
	}

	public static function all() {
		self::maybe_migrate();
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function slot( $key ) {
		$defs = self::definitions();
		if ( ! isset( $defs[ $key ] ) ) {
			return array();
		}
		$all = self::all();
		$base = array(
			'enabled' => 0, 'link_url' => '', 'image_url' => '', 'alt' => '', 'html' => '',
			'new_tab' => 1, 'nofollow' => 1, 'closeable' => $defs[ $key ]['closeable'] ? 1 : 0,
		);
		$slot = array_merge( $base, isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array() );

		// Keep the legacy Customizer controls connected when a site has not
		// migrated that slot into the central ad inventory yet.
		$legacy = get_theme_mod( $key, '' );
		if ( '' !== trim( (string) $legacy ) && '' === trim( (string) $slot['html'] ) && '' === trim( (string) $slot['image_url'] ) ) {
			$parsed = self::parse_simple_image_ad( $legacy );
			if ( $parsed ) {
				$slot = array_merge( $slot, $parsed );
			} else {
				$slot['html'] = wp_kses_post( $legacy );
			}
			$slot['enabled'] = 1;
		}

		return $slot;
	}

	public static function save( $posted ) {
		$slots = array();
		foreach ( self::definitions() as $key => $def ) {
			$row = isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ? $posted[ $key ] : array();
			$slots[ $key ] = array(
				'enabled' => empty( $row['enabled'] ) ? 0 : 1,
				'link_url' => isset( $row['link_url'] ) ? esc_url_raw( wp_unslash( $row['link_url'] ) ) : '',
				'image_url' => isset( $row['image_url'] ) ? esc_url_raw( wp_unslash( $row['image_url'] ) ) : '',
				'alt' => isset( $row['alt'] ) ? sanitize_text_field( wp_unslash( $row['alt'] ) ) : '',
				'html' => isset( $row['html'] ) ? wp_kses_post( wp_unslash( $row['html'] ) ) : '',
				'new_tab' => empty( $row['new_tab'] ) ? 0 : 1,
				'nofollow' => empty( $row['nofollow'] ) ? 0 : 1,
				'closeable' => empty( $row['closeable'] ) ? 0 : 1,
			);
		}
		update_option( self::OPTION, $slots, false );
		if ( isset( $slots['ads_floating_right'] ) && class_exists( 'WPS_Control_Center_42' ) ) {
			WPS_Control_Center_42::update( array(
				'promo_right_link_url' => $slots['ads_floating_right']['link_url'],
				'promo_right_image_url' => $slots['ads_floating_right']['image_url'],
			) );
		}
		return $slots;
	}

	private static function is_priority_slot( $key ) {
		return in_array( $key, array(
			'ads_single_static_top',
			'ads_single_static_bottom_1',
			'ads_single_static_bottom_2',
			'ads_floating_left',
			'ads_floating_right',
			'ads_floating_bottom',
		), true );
	}

	public static function preload_active_images() {
		// Priority images in this inventory belong to the single-video template.
		// Do not preload them on archives, the home page, or a different request.
		// The single-video template owns its ad markup directly, so inventory
		// images must not be preloaded on that page.
		if ( is_admin() || ! is_singular() || is_single() || ( class_exists( 'WPS_Professional_Suite' ) && ! WPS_Professional_Suite::get( 'ads_master_enabled', 1 ) ) ) {
			return;
		}
		$printed_hosts = array();
		$preloaded = 0;
		foreach ( self::keys() as $key ) {
			$slot = self::slot( $key );
			if ( empty( $slot['enabled'] ) || empty( $slot['image_url'] ) || ! self::is_priority_slot( $key ) ) {
				continue;
			}
			$url = esc_url( $slot['image_url'] );
			if ( ! $url ) {
				continue;
			}
			$host = wp_parse_url( $url, PHP_URL_HOST );
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			if ( $host && empty( $printed_hosts[ $host ] ) ) {
				$origin = ( $scheme ? $scheme : 'https' ) . '://' . $host;
				echo '<link rel="preconnect" href="' . esc_url( $origin ) . '" crossorigin>' . "\n";
				echo '<link rel="dns-prefetch" href="//' . esc_attr( $host ) . '">' . "\n";
				$printed_hosts[ $host ] = true;
			}
			if ( $preloaded < 6 ) {
				echo '<link rel="preload" as="image" href="' . $url . '" fetchpriority="high">' . "\n";
				$preloaded++;
			}
		}
	}

	public static function html( $key, $class = '' ) {
		if ( class_exists( 'WPS_Professional_Suite' ) && ! WPS_Professional_Suite::get( 'ads_master_enabled', 1 ) ) {
			return '';
		}
		$slot = self::slot( $key );
		if ( empty( $slot ) || empty( $slot['enabled'] ) ) {
			return '';
		}
		if ( '' !== trim( (string) $slot['html'] ) ) {
			$html = function_exists( 'wpst_render_shortcodes' ) ? wpst_render_shortcodes( $slot['html'] ) : do_shortcode( $slot['html'] );
			return apply_filters( 'wps_ad_html', $html, $key, $slot );
		}
		if ( '' === trim( (string) $slot['image_url'] ) ) {
			return '';
		}
		$img_class = trim( 'wps-ad-image ' . $class );
		$is_priority = self::is_priority_slot( $key );
		$loading = $is_priority ? 'eager' : 'lazy';
		$fetchpriority = $is_priority ? ' fetchpriority="high"' : '';
		$onload = "this.closest('.wps-ad-wrap')&&this.closest('.wps-ad-wrap').classList.add('wps-ad-loaded');";
		$onerror = "this.closest('.wps-ad-wrap')&&this.closest('.wps-ad-wrap').classList.add('wps-ad-error');this.remove();";
		$image = '<img class="' . esc_attr( $img_class ) . '" loading="' . esc_attr( $loading ) . '" decoding="async"' . $fetchpriority . ' src="' . esc_url( $slot['image_url'] ) . '" alt="' . esc_attr( $slot['alt'] ) . '" onload="' . esc_attr( $onload ) . '" onerror="' . esc_attr( $onerror ) . '">';
		if ( '' !== trim( (string) $slot['link_url'] ) ) {
			$rel = $slot['nofollow'] ? 'nofollow sponsored noopener' : 'noopener';
			$target = $slot['new_tab'] ? ' target="_blank"' : '';
			$image = '<a class="wps-ad-link" href="' . esc_url( $slot['link_url'] ) . '"' . $target . ' rel="' . esc_attr( $rel ) . '">' . $image . '</a>';
		}
		$wrap_class = 'wps-ad-wrap wps-ad-slot-' . sanitize_html_class( $key ) . ( $is_priority ? ' wps-ad-priority' : '' );
		$image = '<span class="' . esc_attr( $wrap_class ) . '" data-wps-ad-slot="' . esc_attr( $key ) . '">' . $image . '</span>';
		return apply_filters( 'wps_ad_html', $image, $key, $slot );
	}

	public static function floating_html() {
		// Floating ads are owned by the singular template that calls this method.
		// The queried-object check also prevents cached/AJAX markup from leaking to
		// another page where the floating-ad code was never placed.
		$page_id = absint( get_queried_object_id() );
		if ( ! is_singular() || ! $page_id || $page_id !== absint( get_the_ID() ) ) {
			return '';
		}

		$items = array(
			'ads_floating_left' => 'wps-floating-left',
			'ads_floating_right' => 'wps-floating-right',
		);
		$out = '';
		foreach ( $items as $key => $position ) {
			$html = self::html( $key, 'wps-floating-media' );
			$slot = self::slot( $key );
			if ( '' === trim( $html ) ) {
				continue;
			}
			$out .= '<aside class="wps-floating-ad ' . esc_attr( $position ) . '" data-wps-floating-ad>';
			$out .= $html . '</aside>';
		}
		if ( '' === $out ) {
			return '';
		}

		return '<div class="wps-floating-ad-layer" data-wps-floating-page-id="' . esc_attr( $page_id ) . '">' . $out . '</div>';
	}

	public static function is_active() {
		foreach ( self::keys() as $key ) {
			if ( '' !== trim( self::html( $key ) ) ) {
				return true;
			}
		}
		return false;
	}
}
