<?php
if ( ! defined( 'WPS_INLINE_SUBTITLE_FIELD_READY' ) ) {
	define( 'WPS_INLINE_SUBTITLE_FIELD_READY', true );
}
/**
 * WPS Framework theme bootstrap.
 *
 * The parent-theme identity remains KolorTube so existing theme mods, menu
 * locations, WP-Script Core integrations, and child themes continue to work.
 *
   * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

$wps_theme = wp_get_theme( get_template() );

if ( ! defined( 'WPS_VERSION' ) ) {
	define( 'WPS_VERSION', $wps_theme->get( 'Version' ) ? $wps_theme->get( 'Version' ) : '1.8.1' );
}
if ( ! defined( 'WPS_FRAMEWORK_VERSION' ) ) {
	define( 'WPS_FRAMEWORK_VERSION', '4.3.64.4' );
}
if ( ! defined( 'WPS_PATH' ) ) {
	define( 'WPS_PATH', get_template_directory() );
}
if ( ! defined( 'WPS_URI' ) ) {
	define( 'WPS_URI', get_template_directory_uri() );
}

/**
 * Install uploaded theme ZIPs into the active parent theme directory.
 *
 * WordPress normally uses the ZIP's folder name as the destination, which can
 * create a duplicate theme when a package uses a different folder name.
 */
if ( ! function_exists( 'wps_install_theme_into_active_directory' ) ) {
	function wps_install_theme_into_active_directory( $source, $remote_source, $upgrader ) {
		if ( ! is_object( $upgrader ) || ! is_a( $upgrader, 'Theme_Upgrader' ) || ! is_dir( $source ) ) {
			return $source;
		}

		$active_slug = sanitize_key( get_template() );
		$style_file  = trailingslashit( $source ) . 'style.css';
		if ( '' === $active_slug || ! is_readable( $style_file ) ) {
			return $source;
		}

		$destination = trailingslashit( $remote_source ) . $active_slug;
		if ( wp_normalize_path( $source ) === wp_normalize_path( $destination ) ) {
			return $source;
		}

		if ( file_exists( $destination ) || is_link( $destination ) ) {
			return new WP_Error( 'theme_destination_exists', 'ไม่สามารถเตรียมโฟลเดอร์ธีมเดิมสำหรับการอัปเดตได้' );
		}

		if ( ! rename( $source, $destination ) ) {
			return new WP_Error( 'theme_source_rename_failed', 'ไม่สามารถจับคู่ ZIP กับธีมที่เปิดใช้งานอยู่ได้' );
		}

		return $destination;
	}
}
add_filter( 'upgrader_source_selection', 'wps_install_theme_into_active_directory', 10, 3 );

if ( ! function_exists( 'wpst_get_brightness' ) ) {
	function wpst_get_brightness( $color, $dark = '#000000', $light = '#ffffff' ) {
		$hex = ltrim( (string) $color, '#' );
		if ( ! preg_match( '/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $hex ) ) {
			return $dark;
		}
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$brightness = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
		return $brightness > 186 ? $dark : $light;
	}
}

/**
 * Return the text color with the strongest WCAG contrast against a background.
 */
if ( ! function_exists( 'wpst_get_contrast_text_color' ) ) {
	function wpst_get_contrast_text_color( $background, $light = '#ffffff', $dark = '#111111' ) {
		$background = sanitize_hex_color( $background );
		if ( ! $background ) { return $light; }
		$hex = ltrim( $background, '#' );
		if ( 3 === strlen( $hex ) ) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
		$rgb = array( hexdec(substr($hex,0,2))/255, hexdec(substr($hex,2,2))/255, hexdec(substr($hex,4,2))/255 );
		$linear = array_map( static function( $c ) { return $c <= 0.04045 ? $c/12.92 : pow(($c+0.055)/1.055,2.4); }, $rgb );
		$luminance = (0.2126*$linear[0])+(0.7152*$linear[1])+(0.0722*$linear[2]);
		$contrast = static function( $candidate ) use ( $luminance ) {
			$candidate = sanitize_hex_color( $candidate );
			if ( ! $candidate ) { return 0; }
			$h = ltrim($candidate,'#');
			if ( 3 === strlen($h) ) { $h=$h[0].$h[0].$h[1].$h[1].$h[2].$h[2]; }
			$rgb = array( hexdec(substr($h,0,2))/255, hexdec(substr($h,2,2))/255, hexdec(substr($h,4,2))/255 );
			$lin = array_map( static function( $c ) { return $c <= 0.04045 ? $c/12.92 : pow(($c+0.055)/1.055,2.4); }, $rgb );
			$lum = (0.2126*$lin[0])+(0.7152*$lin[1])+(0.0722*$lin[2]);
			return (max($luminance,$lum)+0.05)/(min($luminance,$lum)+0.05);
		};
		return $contrast($dark) >= $contrast($light) ? $dark : $light;
	}
}

if ( ! function_exists( 'wps_uni_owns_import_export' ) ) {
	function wps_uni_owns_import_export() {
		// ธีมทำงานด้วยตัวเอง ไม่พึ่งพาปลั๊กอินภายนอก
		return false;
	}
}

if ( ! function_exists( 'wps_resolve_image_value_to_url' ) ) {
	function wps_resolve_image_value_to_url( $value, $size = 'video-thumb' ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$url = wps_resolve_image_value_to_url( $item, $size );
				if ( $url ) {
					return $url;
				}
			}
			return '';
		}

		if ( is_object( $value ) ) {
			return wps_resolve_image_value_to_url( (array) $value, $size );
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '~^https?://~i', $value ) ) {
			return esc_url_raw( $value );
		}

		if ( is_numeric( $value ) ) {
			$attachment_id = absint( $value );
			foreach ( array_unique( array_filter( array( $size, 'video-thumb', 'medium_large', 'large', 'full', 'thumbnail' ) ) ) as $try_size ) {
				$url = wp_get_attachment_image_url( $attachment_id, $try_size );
				if ( $url ) {
					return esc_url_raw( $url );
				}
			}
		}

		$maybe = maybe_unserialize( $value );
		if ( $maybe !== $value ) {
			$url = wps_resolve_image_value_to_url( $maybe, $size );
			if ( $url ) {
				return $url;
			}
		}

		if ( preg_match_all( '~\d+~', $value, $m ) ) {
			foreach ( $m[0] as $id ) {
				$url = wps_resolve_image_value_to_url( $id, $size );
				if ( $url ) {
					return $url;
				}
			}
		}

		return '';
	}
}

if ( ! function_exists( 'wps_get_post_gallery_first_image_url' ) ) {
	function wps_get_post_gallery_first_image_url( $post_id, $size = 'video-thumb' ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return '';
		}

		$gallery_keys = array(
			'_wps_42_gallery_ids',
			'wps_gallery_ids',
			'_gallery_image_ids',
			'gallery_image_ids',
			'gallery',
			'_gallery',
			'images',
			'_images',
		);
		foreach ( $gallery_keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			$url   = wps_resolve_image_value_to_url( $value, $size );
			if ( $url ) {
				return $url;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'wps_get_term_original_meta_image_url' ) ) {
	function wps_get_term_original_meta_image_url( $term, $taxonomy = '', $size = 'video-thumb' ) {
		if ( ! $term instanceof WP_Term ) {
			$term = get_term( $term, $taxonomy );
		}
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		$taxonomy = $taxonomy ? $taxonomy : $term->taxonomy;
		$keys_by_taxonomy = array(
			'category' => array( 'category-image-id', 'category_image_id', 'category_image', 'thumbnail_id', 'image_id', 'term_image_id', 'term_image', 'tax_image_id', 'tax_image' ),
			'actors'   => array( 'actors-image-id', 'actor-image-id', 'actors_image_id', 'actor_image_id', 'actors_image', 'actor_image', 'thumbnail_id', 'image_id', 'term_image_id', 'term_image' ),
			'studio'   => array( 'studio-image-id', 'studio_image_id', 'studio_image', 'thumbnail_id', 'image_id', 'term_image_id', 'term_image' ),
			'post_tag' => array( 'post_tag-image-id', 'tag-image-id', 'tag_image_id', 'tag_image', 'thumbnail_id', 'image_id', 'term_image_id', 'term_image' ),
		);

		$keys = isset( $keys_by_taxonomy[ $taxonomy ] ) ? $keys_by_taxonomy[ $taxonomy ] : array( 'thumbnail_id', 'image_id', 'term_image_id', 'term_image' );
		foreach ( $keys as $key ) {
			$url = wps_resolve_image_value_to_url( get_term_meta( $term->term_id, $key, true ), $size );
			if ( $url ) {
				return $url;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'wps_get_latest_attachment_image_url' ) ) {
	function wps_get_latest_attachment_image_url( $size = 'video-thumb' ) {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 12,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( empty( $attachments ) ) {
			return '';
		}
		$sizes = array_unique( array_filter( array( $size, 'video-thumb', 'medium_large', 'large', 'full' ) ) );
		foreach ( $attachments as $attachment_id ) {
			foreach ( $sizes as $try_size ) {
				$url = wp_get_attachment_image_url( absint( $attachment_id ), $try_size );
				if ( $url ) {
					return esc_url_raw( $url );
				}
			}
		}
		return '';
	}
}

if ( ! function_exists( 'wps_get_post_best_image_url' ) ) {
	function wps_get_post_best_image_url( $post_id, $size = 'video-thumb' ) {
		$post_id = $post_id instanceof WP_Post ? $post_id->ID : absint( $post_id );
		if ( ! $post_id ) {
			return '';
		}

		$sizes = array_unique( array_filter( array( $size, 'video-thumb', 'medium_large', 'large', 'full' ) ) );
		foreach ( $sizes as $try_size ) {
			$url = get_the_post_thumbnail_url( $post_id, $try_size );
			if ( $url ) {
				return esc_url_raw( $url );
			}
		}

		$gallery_url = function_exists( 'wps_get_post_gallery_first_image_url' ) ? wps_get_post_gallery_first_image_url( $post_id, $size ) : '';
		if ( $gallery_url ) {
			return $gallery_url;
		}

		$children = get_children(
			array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'numberposts'    => 1,
				'orderby'        => 'menu_order ID',
				'order'          => 'ASC',
			)
		);
		if ( $children ) {
			$attachment = reset( $children );
			foreach ( $sizes as $try_size ) {
				$url = wp_get_attachment_image_url( $attachment->ID, $try_size );
				if ( $url ) {
					return esc_url_raw( $url );
				}
			}
		}

		foreach ( array( 'thumb', 'poster', 'cover', 'image', 'thumbnail', 'video_thumb', 'video_poster', 'video_thumb_url', 'poster_url' ) as $meta_key ) {
			$meta_url = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
			if ( $meta_url && preg_match( '~^https?://~i', $meta_url ) ) {
				return esc_url_raw( $meta_url );
			}
		}

		if ( function_exists( 'wpst_get_video_thumb_url' ) ) {
			$url = wpst_get_video_thumb_url( $post_id, $size, false );
			if ( $url ) {
				return esc_url_raw( $url );
			}
		}

		$post = get_post( $post_id );
		if ( $post && ! empty( $post->post_content ) && preg_match( '~<img[^>]+src=["\']([^"\']+)["\']~i', $post->post_content, $m ) ) {
			return esc_url_raw( $m[1] );
		}

		return '';
	}
}

if ( ! function_exists( 'wps_get_latest_site_image_url' ) ) {
	function wps_get_latest_site_image_url( $size = 'video-thumb' ) {
		$q = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 10,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		);
		foreach ( $q->posts as $post_obj ) {
			$post_id = $post_obj instanceof WP_Post ? $post_obj->ID : absint( $post_obj );
			if ( ! $post_id ) {
				continue;
			}
			$url = wps_get_post_best_image_url( $post_id, $size );
			if ( $url ) {
				wp_reset_postdata();
				return $url;
			}
		}
		wp_reset_postdata();
		$attachment_url = function_exists( 'wps_get_latest_attachment_image_url' ) ? wps_get_latest_attachment_image_url( $size ) : '';
		return $attachment_url ? $attachment_url : get_template_directory_uri() . '/img/no-thumb.png';
	}
}

if ( ! function_exists( 'wps_get_term_best_image_url' ) ) {
	function wps_get_term_best_image_url( $term, $taxonomy = '', $size = 'video-thumb' ) {
		if ( ! $term instanceof WP_Term ) {
			$term = get_term( $term, $taxonomy );
		}
		if ( ! $term || is_wp_error( $term ) ) {
			return function_exists( 'wps_get_latest_site_image_url' ) ? wps_get_latest_site_image_url( $size ) : '';
		}

		$taxonomy = $taxonomy ? $taxonomy : $term->taxonomy;
		$original = function_exists( 'wps_get_term_original_meta_image_url' ) ? wps_get_term_original_meta_image_url( $term, $taxonomy, $size ) : '';
		if ( $original ) {
			return $original;
		}

		$post_ids = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 12,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'fields'              => 'ids',
				'tax_query'           => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => array( $term->term_id ),
					),
				),
			)
		);
		foreach ( $post_ids as $post_id ) {
			$url = wps_get_post_best_image_url( $post_id, $size );
			if ( $url ) {
				return $url;
			}
		}

		return function_exists( 'wps_get_latest_site_image_url' ) ? wps_get_latest_site_image_url( $size ) : get_template_directory_uri() . '/img/no-thumb.png';
	}
}

if ( ! function_exists( 'wps_term_image_img_html' ) ) {
	function wps_term_image_img_html( $term, $taxonomy = '', $size = 'video-thumb', $class = 'video-img img-fluid' ) {
		if ( ! $term instanceof WP_Term ) {
			$term = get_term( $term, $taxonomy );
		}
		if ( ! $term || is_wp_error( $term ) ) {
			$alt = get_bloginfo( 'name' );
		} else {
			$alt      = $term->name;
			$taxonomy = $taxonomy ? $taxonomy : $term->taxonomy;
		}

		$src = function_exists( 'wps_get_term_best_image_url' ) && $term && ! is_wp_error( $term ) ? wps_get_term_best_image_url( $term, $taxonomy, $size ) : '';
		if ( ! $src && function_exists( 'wps_get_latest_site_image_url' ) ) {
			$src = wps_get_latest_site_image_url( $size );
		}
		if ( ! $src ) {
			$src = get_template_directory_uri() . '/img/no-thumb.png';
		}

		$fallback = function_exists( 'wps_get_latest_site_image_url' ) ? wps_get_latest_site_image_url( $size ) : '';
		if ( ! $fallback ) {
			$fallback = get_template_directory_uri() . '/img/no-thumb.png';
		}

		$loading = class_exists( 'WPS_Performance' ) ? WPS_Performance::image_loading_attributes() : ' loading="lazy" decoding="async"';
		$onerror = "if(!this.dataset.wpsFallbackTried&&this.dataset.fallback){this.dataset.wpsFallbackTried='1';this.src=this.dataset.fallback;}else{this.onerror=null;this.src='" . esc_url( get_template_directory_uri() . '/img/no-thumb.png' ) . "';}";

		return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $src ) . '" data-fallback="' . esc_url( $fallback ) . '" alt="' . esc_attr( $alt ) . '"' . $loading . ' onerror="' . esc_attr( $onerror ) . '">';
	}
}

if ( ! function_exists( 'wps_histats_bootstrap' ) ) {
	function wps_histats_bootstrap() {
		if ( is_admin() || is_feed() || is_robots() || wp_doing_ajax() ) {
			return;
		}

		$histats_code = (string) get_theme_mod( 'google_analytics_code', '' ) . "\n" . (string) get_theme_mod( 'other_script_codes', '' );
		if ( false === stripos( $histats_code, 'histats' ) && false === stripos( $histats_code, '_Hasync' ) ) {
			return;
		}
		?>
		<script id="wps-histats-pageview-bridge" data-no-optimize="1" data-cfasync="false">
		(function(w,d){
			'use strict';
			if(w.__wpsHistatsBridgeLoaded){return;}
			w.__wpsHistatsBridgeLoaded=true;
			var lastUrl=w.location.href;
			var timer=0;
			function queuePageview(force){
				var currentUrl=w.location.href;
				if(!force&&currentUrl===lastUrl){return;}
				lastUrl=currentUrl;
				w.clearTimeout(timer);
				timer=w.setTimeout(function(){
					w._Hasync=w._Hasync||[];
					w._Hasync.push(['Histats.track_hits','']);
				},0);
			}
			function wrapHistory(method){
				var original=w.history&&w.history[method];
				if(typeof original!=='function'){return;}
				w.history[method]=function(){
					var result=original.apply(this,arguments);
					queuePageview(false);
					return result;
				};
			}
			wrapHistory('pushState');
			w.addEventListener('popstate',function(){queuePageview(false);},false);
			w.addEventListener('hashchange',function(){queuePageview(false);},false);
			['wps:pageview','wps:video-loaded','vdohide:pageview','vdohide:video-loaded'].forEach(function(eventName){
				d.addEventListener(eventName,function(){queuePageview(true);},false);
			});
		})(window,document);
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'wps_histats_bootstrap', 9999 );

require_once WPS_PATH . '/inc/theme-activation.php';
require_once WPS_PATH . '/inc/class-wps-framework.php';

WPS_Framework::instance()->boot();

/**
 * Option A: native KolorTube fallback for the ads module.
 *
 * If the AV Framework ads class is ever unavailable (module or file missing),
 * every template keeps working by reading the exact same slot keys from the
 * theme customizer (theme mods) instead of fataling on a missing class.
 * When the framework module loads normally, this fallback is never defined.
 */
if ( ! class_exists( 'WPS_Ads' ) ) {
	final class WPS_Ads {
		/**
		 * Return the raw banner HTML stored in the matching theme mod.
		 *
		 * @param string $zone Ad slot key, identical to the theme-mod key.
		 *
		 * @return string
		 */
		public static function html( $zone ) {
			$zone = is_string( $zone ) ? sanitize_key( $zone ) : '';
			if ( '' === $zone ) {
				return '';
			}
			return (string) get_theme_mod( $zone, '' );
		}

		/**
		 * Return simple floating banners built from the legacy theme mods.
		 *
		 * @return string
		 */
		public static function floating_html() {
			$output = '';
			foreach ( array( 'ads_floating_left', 'ads_floating_right' ) as $floating_key ) {
				$banner = trim( (string) get_theme_mod( $floating_key, '' ) );
				if ( '' === $banner ) {
					continue;
				}
				$output .= '<div class="wps-native-floating-banner">' . wp_kses_post( $banner ) . '</div>';
			}
			return $output;
		}
	}
}

// Option A: the full-backup module is disconnected, so drop any leftover
// scheduled cron event registered by earlier versions of the theme.
wp_clear_scheduled_hook( 'wps_full_backup_cron' );

if ( ! function_exists( 'wps_default_gallery_link_to_media' ) ) {
	function wps_default_gallery_link_to_media( $args, $block_type ) {
		$block_name = is_object( $block_type ) && isset( $block_type->name )
			? (string) $block_type->name
			: (string) $block_type;

		if ( 'core/gallery' === $block_name ) {
			if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
				$args['attributes'] = array();
			}
			if ( ! isset( $args['attributes']['linkTo'] ) || ! is_array( $args['attributes']['linkTo'] ) ) {
				$args['attributes']['linkTo'] = array( 'type' => 'string' );
			}
			$args['attributes']['linkTo']['default'] = 'media';
		}

		if ( 'core/image' === $block_name ) {
			if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
				$args['attributes'] = array();
			}
			if ( ! isset( $args['attributes']['linkDestination'] ) || ! is_array( $args['attributes']['linkDestination'] ) ) {
				$args['attributes']['linkDestination'] = array( 'type' => 'string' );
			}
			$args['attributes']['linkDestination']['default'] = 'media';
		}

		return $args;
	}
	add_filter( 'register_block_type_args', 'wps_default_gallery_link_to_media', 20, 2 );
}

if ( ! function_exists( 'wps_gallery_block_full_image_links' ) ) {
	function wps_gallery_block_full_image_links( $content, $block ) {
		if ( ! is_singular( 'post' ) || empty( $block['blockName'] ) || 'core/gallery' !== $block['blockName'] ) {
			return $content;
		}
		return preg_replace_callback( '/(<figure\\b[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>)(<img\\b[^>]*\\bwp-image-(\\d+)[^>]*>)(.*?<\\/figure>)/is', function ( $match ) {
			$full_url = wp_get_attachment_image_url( absint( $match[3] ), 'full' );
			if ( ! $full_url || false !== stripos( $match[1], '<a ' ) ) {
				return $match[0];
			}
			return $match[1] . '<a class="gallery-full-link" href="' . esc_url( $full_url ) . '" target="_blank" rel="noopener">' . $match[2] . '</a>' . $match[4];
		}, $content );
	}
	add_filter( 'render_block_core/gallery', 'wps_gallery_block_full_image_links', 20, 2 );
}


/**
 * BOSSMASTER 4.3.64.3: Theme-native floating-banner assets only.
 *
 * WP MY BOSS is the single owner of frontend colors and portable custom CSS.
 * The Theme must not read wpmb_theme_colors or output duplicate :root rules.
 */
if ( ! function_exists( 'wps_wpmb_integrated_frontend_assets' ) ) {
    function wps_wpmb_integrated_frontend_assets() {
        if ( is_admin() || ! is_singular() ) {
            return;
        }

        $banner_css = WPS_PATH . '/assets/css/bossmaster-floating-banner.css';
        $banner_js  = WPS_PATH . '/assets/js/bossmaster-floating-banner.js';
        wp_enqueue_style(
            'wps-bossmaster-floating-banner',
            WPS_URI . '/assets/css/bossmaster-floating-banner.css',
            array( 'wpst-custom-style' ),
            is_file( $banner_css ) ? (string) filemtime( $banner_css ) : WPS_VERSION
        );
        wp_enqueue_script(
            'wps-bossmaster-floating-banner',
            WPS_URI . '/assets/js/bossmaster-floating-banner.js',
            array(),
            is_file( $banner_js ) ? (string) filemtime( $banner_js ) : WPS_VERSION,
            true
        );
    }
    add_action( 'wp_enqueue_scripts', 'wps_wpmb_integrated_frontend_assets', 120 );
}

if ( ! function_exists( 'wps_bossmaster_floating_body_class' ) ) {
    function wps_bossmaster_floating_body_class( $classes ) {
        if ( ! is_admin() && is_singular() ) {
            $classes[] = 'bmfb-floating-banner-display-active';
        }
        return array_values( array_unique( $classes ) );
    }
    add_filter( 'body_class', 'wps_bossmaster_floating_body_class' );
}

add_action( 'customize_controls_print_styles', function () {
	?>
	<style>
		/* WPS Framework > Customizer text input/textarea red only */
		#customize-theme-controls input[type="text"],
		#customize-theme-controls textarea,
		#customize-controls input[type="text"],
		#customize-controls textarea {
			color: #ff0000 !important;
			-webkit-text-fill-color: #ff0000 !important;
			caret-color: #ff0000 !important;
		}
	</style>
	<?php
} );
