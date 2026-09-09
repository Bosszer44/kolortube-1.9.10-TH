<?php
/** Customizer front-end output, color data endpoints and compatibility helpers. */

defined( 'ABSPATH' ) || exit;

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
if ( ! function_exists( 'wpst_customize_preview_js' ) ) {
	/**
	 * Setup JS integration for live previewing.
	 */
	function wpst_customize_preview_js() {
		wp_enqueue_script(
			'wpst_customizer',
			get_template_directory_uri() . '/js/customizer.js',
			array( 'customize-preview' ),
			'1.1.0',
			true
		);
	}
}
add_action( 'customize_preview_init', 'wpst_customize_preview_js' );

if ( ! function_exists( 'wpst_get_brightness' ) ) {
	function wpst_get_brightness( $hex, $light_color = '#ffffff', $dark_color = '#000000' ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( ! preg_match( '/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $hex ) ) {
			return $dark_color;
		}
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$c_r = hexdec( substr( $hex, 0, 2 ) );
		$c_g = hexdec( substr( $hex, 2, 2 ) );
		$c_b = hexdec( substr( $hex, 4, 2 ) );
		return ( ( $c_r * 299 ) + ( $c_g * 587 ) + ( $c_b * 114 ) ) / 1000 > 180 ? $dark_color : $light_color;
	}
}

if ( ! function_exists( 'wpst_hex2rgba' ) ) {
	function wpst_hex2rgba( $color, $opacity = false ) {
		$default = 'rgb(0,0,0)';
		if ( empty( $color ) ) {
			return $default;
		}
		if ( '#' === $color[0] ) {
			$color = substr( $color, 1 );
		}
		if ( 6 === strlen( $color ) ) {
			$hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
		} elseif ( 3 === strlen( $color ) ) {
			$hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
		} else {
			return $default;
		}
		$rgb = array_map( 'hexdec', $hex );
		if ( $opacity ) {
			if ( abs( $opacity ) > 1 ) {
				$opacity = 1.0;
			}
			return 'rgba(' . implode( ',', $rgb ) . ',' . $opacity . ')';
		}
		return 'rgb(' . implode( ',', $rgb ) . ')';
	}
}
if ( ! function_exists( 'wpst_color_blend_over' ) ) {
	/**
	 * Composite a semi-transparent top color over an opaque base color and
	 * return the resulting opaque hex. Used to derive the effective surface
	 * color of translucent chips (e.g. rgba(255,255,255,.78)) so their text
	 * is resolved with real WCAG contrast instead of guessing.
	 *
	 * @param string $top_hex    Top color (opaque hex).
	 * @param float  $alpha      Top opacity 0..1.
	 * @param string $bottom_hex Base color (opaque hex).
	 * @return string Opaque hex color.
	 */
	function wpst_color_blend_over( $top_hex, $alpha, $bottom_hex ) {
		$top_hex    = sanitize_hex_color( $top_hex );
		$bottom_hex = sanitize_hex_color( $bottom_hex );
		if ( ! $top_hex || ! $bottom_hex ) {
			return $top_hex ? $top_hex : ( $bottom_hex ? $bottom_hex : '#000000' );
		}
		$alpha = max( 0.0, min( 1.0, (float) $alpha ) );
		$blend = static function ( $hex ) {
			$hex = ltrim( $hex, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		};
		$t = $blend( $top_hex );
		$b = $blend( $bottom_hex );
		$out = array();
		foreach ( array( 0, 1, 2 ) as $i ) {
			$out[ $i ] = (int) round( $t[ $i ] * $alpha + $b[ $i ] * ( 1 - $alpha ) );
		}
		return sprintf( '#%02x%02x%02x', $out[0], $out[1], $out[2] );
	}
}

if ( ! function_exists( 'wps_color_with_master' ) ) {
	/**
	 * Resolve a secondary Theme Color. If the user has not explicitly changed
	 * the secondary control, it follows the original KolorTube Main Color.
	 * The stored value is read through the central registry so the Customizer
	 * value, the persisted theme_mod and the frontend output always match.
	 *
	 * @param string $setting_id Setting ID.
	 * @param string $registered_default Previous/default value of the setting.
	 * @param string $master_color Master Theme Color.
	 * @param string $fallback Fallback color.
	 * @return string
	 */
	function wps_color_with_master( $setting_id, $registered_default, $master_color, $fallback = '' ) {
		$stored             = wps_get_color_setting( $setting_id, '' );
		$stored_normalized  = is_string( $stored ) ? strtolower( trim( $stored ) ) : '';
		$default_normalized = strtolower( trim( (string) $registered_default ) );

		// A missing value, the registered default, or one of the old pink
		// palette values from previous theme builds means "follow Main Color".
		// This also migrates old saved values instead of leaving the site stuck
		// on the previous pink palette after Main Color is changed.
		$legacy_linked_values = array(
			'#ff2d80', '#ff2f8f', '#f42370', '#ff4d8d', '#ff6ba3',
			'#d93f89', '#a70742', '#c92b80', '#d10954', '#ee519e', '#f1379b',
		);

		if ( '' === $stored_normalized || $stored_normalized === $default_normalized || in_array( $stored_normalized, $legacy_linked_values, true ) ) {
			return sanitize_hex_color( $fallback ) ?: ( sanitize_hex_color( $master_color ) ?: '' );
		}

		return sanitize_hex_color( $stored ) ?: ( sanitize_hex_color( $fallback ) ?: ( sanitize_hex_color( $master_color ) ?: '' ) );
	}
}

if ( ! function_exists( 'wps_master_variant' ) ) {
	/** Create a readable accent variant from the master color. */
	function wps_master_variant( $hex, $lighten = 0, $hue_shift = 0 ) {
		$hex = sanitize_hex_color( $hex );
		if ( ! $hex ) {
			return '#26adfe';
		}
		$rgb = array_map( 'hexdec', array( substr( $hex, 1, 2 ), substr( $hex, 3, 2 ), substr( $hex, 5, 2 ) ) );
		$max = max( $rgb ); $min = min( $rgb ); $d = $max - $min;
		$l = ( $max + $min ) / 510;
		$s = 0;
		$h = 0;
		if ( $d ) {
			$s = $d / ( 255 - abs( 2 * $l * 255 - 255 ) );
			if ( $max === $rgb[0] ) { $h = 60 * fmod( ( $rgb[1] - $rgb[2] ) / $d, 6 ); }
			elseif ( $max === $rgb[1] ) { $h = 60 * ( ( $rgb[2] - $rgb[0] ) / $d + 2 ); }
			else { $h = 60 * ( ( $rgb[0] - $rgb[1] ) / $d + 4 ); }
		}
		if ( $h < 0 ) { $h += 360; }
		$h = fmod( $h + $hue_shift + 360, 360 );
		$l = max( 0.12, min( 0.82, $l + $lighten ) );
		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;
		if ( $h < 60 ) { $parts = array( $c, $x, 0 ); }
		elseif ( $h < 120 ) { $parts = array( $x, $c, 0 ); }
		elseif ( $h < 180 ) { $parts = array( 0, $c, $x ); }
		elseif ( $h < 240 ) { $parts = array( 0, $x, $c ); }
		elseif ( $h < 300 ) { $parts = array( $x, 0, $c ); }
		else { $parts = array( $c, 0, $x ); }
		return sprintf( '#%02x%02x%02x', round( ( $parts[0] + $m ) * 255 ), round( ( $parts[1] + $m ) * 255 ), round( ( $parts[2] + $m ) * 255 ) );
	}
}
if ( ! function_exists( 'wps_resolve_theme_colors' ) ) {
	function wps_resolve_theme_colors() {
		$main_color = wps_get_color_setting( 'main_color', '#26adfe' ) ?: '#26adfe';
		$link_color = wps_color_with_master( 'link_color', '#26adfe', $main_color, $main_color );
		return array( $main_color, $link_color );
	}
}

if ( ! function_exists( 'wps_resolved_color_tokens' ) ) {
	/**
	 * Resolve the complete Theme Color system.
	 *
	 * Reads every value exactly as stored in WordPress meta (theme_mod) via
	 * the central registry, derives all automatic text colors with real WCAG
	 * contrast against their actual surface, and returns everything the
	 * frontend CSS and the persisted palette record need.
	 *
	 * @return array<string,mixed>
	 */
	function wps_resolved_color_tokens() {
		$registry = wps_get_color_registry();

		$main_color      = wps_get_color_setting( 'main_color' ) ?: '#26adfe';
		$link_color      = wps_color_with_master( 'link_color', $registry['link_color']['default'], $main_color, $main_color );
		$site_background = wps_get_color_setting( 'wps_site_background_color' ) ?: $registry['wps_site_background_color']['default'];
		$soft_surface    = wps_get_color_setting( 'wps_soft_surface_color' ) ?: $registry['wps_soft_surface_color']['default'];
		$soft_border_hex = wps_get_color_setting( 'wps_soft_border_color' ) ?: $registry['wps_soft_border_color']['default'];

		$menu_background = wps_get_color_setting( 'wps_menu_background_color' ) ?: $registry['wps_menu_background_color']['default'];
		$menu_hover_bg   = wps_color_with_master( 'wps_menu_hover_background', $registry['wps_menu_hover_background']['default'], $main_color, wps_master_variant( $main_color, 0.00, 35 ) );
		$menu_active_bg  = wps_color_with_master( 'wps_menu_active_bg', $registry['wps_menu_active_bg']['default'], $main_color, wps_master_variant( $main_color, 0.02, 35 ) );

		$button_start  = wps_color_with_master( 'wps_button_start_color', $registry['wps_button_start_color']['default'], $main_color, wps_master_variant( $main_color, 0.10, 0 ) );
		$button_middle = wps_color_with_master( 'wps_button_middle_color', $registry['wps_button_middle_color']['default'], $main_color, wps_master_variant( $main_color, 0.00, 55 ) );
		$button_end    = wps_color_with_master( 'wps_button_end_color', $registry['wps_button_end_color']['default'], $main_color, wps_master_variant( $main_color, -0.02, 75 ) );

		/* Derive every text color from its actual surface using WCAG contrast. */
		$ui_text             = wpst_get_contrast_text_color( $site_background );
		$ui_muted_hex        = wpst_get_contrast_text_color( $site_background, '#e7e7ef', '#4b4b5a' );
		$menu_text           = wpst_get_contrast_text_color( $menu_background );
		$menu_hover_text     = wpst_get_contrast_text_color( $menu_hover_bg );
		$menu_active_text    = wpst_get_contrast_text_color( $menu_active_bg );
		$button_text         = wpst_get_contrast_text_color( $button_middle );
		$label_text          = $button_text;

		/*
		 * Ghost / translucent buttons (Save, Share, generic .capsule-btn)
		 * sit directly on the Site Background, not on the button gradient.
		 * Their surface is the site background blended with the 12% main-color
		 * overlay, so contrast must be resolved from that blended surface —
		 * otherwise white text disappears against a dark site background.
		 */
		$ghost_button_surface = wpst_color_blend_over( $main_color, 0.12, $site_background );
		$ghost_button_text    = wpst_get_contrast_text_color( $ghost_button_surface, '#ffffff', '#1d1d25' );
		$ghost_button_hover_color = wps_get_color_setting( 'wps_button_hover_border_color' ) ?: '#c00bff';
		$ghost_button_hover_surface = wpst_color_blend_over( $ghost_button_hover_color, 0.18, $site_background );
		$ghost_button_hover_text    = wpst_get_contrast_text_color( $ghost_button_hover_surface, '#ffffff', '#1d1d25' );
		$taxonomy_surface    = wps_get_color_setting( 'wps_taxonomy_surface' ) ?: $registry['wps_taxonomy_surface']['default'];
		$taxonomy_text       = wpst_get_contrast_text_color( $taxonomy_surface );
		$taxonomy_muted_hex  = wpst_get_contrast_text_color( $taxonomy_surface, '#e7e7ef', '#4b4b5a' );
		$taxonomy_hover_bg   = wps_get_color_setting( 'wps_taxonomy_hover_background' ) ?: $registry['wps_taxonomy_hover_background']['default'];
		$taxonomy_hover_text = wpst_get_contrast_text_color( $taxonomy_hover_bg );
		$featured_card_bg    = wps_get_color_setting( 'wps_featured_card_bg' ) ?: $registry['wps_featured_card_bg']['default'];
		$featured_card_text  = wpst_get_contrast_text_color( $featured_card_bg );
		$tag_card_bg         = wps_get_color_setting( 'wps_tag_card_bg' ) ?: $registry['wps_tag_card_bg']['default'];
		$tag_card_text       = wpst_get_contrast_text_color( $tag_card_bg );
		$card_surface_for_text = wps_get_color_setting( 'wps_card_surface_color' ) ?: $registry['wps_card_surface_color']['default'];
		$card_text_color       = wpst_get_contrast_text_color( $card_surface_for_text );
		$card_muted_color      = wpst_get_contrast_text_color( $card_surface_for_text, '#d9d9e3', '#555563' );
		$dropdown_background   = wps_get_color_setting( 'wps_dropdown_background' ) ?: $registry['wps_dropdown_background']['default'];
		$dropdown_text         = wpst_get_contrast_text_color( $dropdown_background );
		$dropdown_hover_bg     = wps_get_color_setting( 'wps_dropdown_hover_background' ) ?: $registry['wps_dropdown_hover_background']['default'];
		$dropdown_hover_text   = wpst_get_contrast_text_color( $dropdown_hover_bg );
$input_background      = wps_get_color_setting( 'wps_input_background_color' ) ?: $registry['wps_input_background_color']['default'];
		$input_text            = wpst_get_contrast_text_color( $input_background );
		$input_placeholder     = wpst_hex2rgba( wpst_get_contrast_text_color( $input_background, '#e7e7ef', '#4b4b5a' ), 0.62 );
		$view_all_background   = wps_color_with_master( 'wps_view_all_background_color', $registry['wps_view_all_background_color']['default'], $main_color, $registry['wps_view_all_background_color']['default'] );
		$view_all_text         = wpst_get_contrast_text_color( $view_all_background );

		/*
		 * Footer follows its own setting; when unset it is derived from the
		 * Site Background so footer text always has a guaranteed surface.
		 */
		$footer_background = wps_get_color_setting( 'wps_footer_background_color' );
		if ( '' === $footer_background ) {
			$footer_background = wpst_get_brightness( $site_background, '#04040d', '#f4f5fb' );
		}
		$footer_text      = wpst_get_contrast_text_color( $footer_background );
		$footer_muted_hex = wpst_get_contrast_text_color( $footer_background, '#e7e7ef', '#4b4b5a' );

		/* Text on the Main Color surface and on the translucent light chips. */
		$main_contrast_text = wpst_get_contrast_text_color( $main_color );
		$on_light_surface   = wpst_color_blend_over( '#ffffff', 0.78, $site_background );
		$on_light_text      = wpst_get_contrast_text_color( $on_light_surface, '#ffffff', '#1d1d25' );
		$on_light_muted     = wpst_hex2rgba( $on_light_text, 0.72 );
/*
		 * Extended semantic color system.
		 * Every value below is a Theme Mod so the existing KolorTube Customizer
		 * remains the single source of truth for frontend colors.
		 */
		$color_defaults = array(
			'wps_menu_hover_background'      => '#20104d',
			'wps_dropdown_background'        => '#07051a',
			'wps_dropdown_border'            => '#26adfe',
			'wps_dropdown_hover_background'  => '#24104d',
			'wps_button_border_color'        => '#26adfe',
			'wps_button_hover_border_color'  => '#c00bff',
			'wps_button_shadow_color'        => '#26adfe',
			'wps_button_hover_shadow_color'  => '#c00bff',
			'wps_label_border_color'         => '#26adfe',
			'wps_label_shadow_color'         => '#7b18ff',
			'wps_card_surface_color'         => '#08071b',
			'wps_card_border_color'          => '#24154f',
			'wps_card_hover_border_color'    => '#26adfe',
			'wps_card_shadow_color'          => '#000000',
			'wps_card_hover_shadow_color'    => '#7b18ff',
			'wps_taxonomy_border_color'      => '#26adfe',
			'wps_taxonomy_hover_border_color'=> '#c00bff',
			'wps_taxonomy_hover_background'  => '#24104d',
			'wps_taxonomy_shadow_color'      => '#000000',
			'wps_taxonomy_hover_shadow_color'=> '#7b18ff',
			'wps_featured_card_border_color' => '#24154f',
			'wps_featured_card_shadow_color' => '#000000',
			'wps_featured_card_hover_color'  => '#7b18ff',
			'wps_tag_card_border_color'      => '#24154f',
			'wps_tag_card_shadow_color'      => '#000000',
			'wps_tag_card_hover_color'       => '#7b18ff',
			'wps_view_all_background_color'  => '#7b18ff',
			'wps_view_all_shadow_color'      => '#7b18ff',
			'wps_input_background_color'     => '#07051a',
			'wps_input_border_color'         => '#24154f',
			'wps_input_focus_border_color'   => '#26adfe',
			'wps_single_poster_bg_color'     => '#000000',
			'wps_single_poster_border_color' => '#24154f',
			'wps_single_poster_shadow_color' => '#000000',
			'wps_success_color'              => '#22c55e',
			'wps_warning_color'              => '#f59e0b',
			'wps_danger_color'               => '#ef4444',
			'wps_info_color'                 => '#26adfe',
		);
		$extended_colors = array();
		$master_followers = array(
			'wps_menu_hover_background', 'wps_dropdown_border', 'wps_button_border_color',
			'wps_button_hover_border_color', 'wps_button_shadow_color', 'wps_button_hover_shadow_color',
			'wps_label_border_color', 'wps_label_shadow_color', 'wps_card_hover_border_color',
			'wps_card_hover_shadow_color', 'wps_taxonomy_border_color', 'wps_taxonomy_hover_border_color',
			'wps_taxonomy_hover_shadow_color', 'wps_featured_card_hover_color', 'wps_tag_card_hover_color',
			'wps_view_all_background_color', 'wps_view_all_shadow_color', 'wps_input_focus_border_color',
			'wps_info_color'
		);
		foreach ( $color_defaults as $setting_id => $default ) {
			if ( in_array( $setting_id, $master_followers, true ) ) {
				$value = wps_color_with_master( $setting_id, $default, $main_color, $default );
			} else {
				$value = wps_get_color_setting( $setting_id, $default ) ?: $default;
			}
			$extended_colors[ $setting_id ] = $value;
		}
/* Derived text values intentionally override any legacy saved text mods. */
		$extended_colors['wps_menu_hover_background'] = $menu_hover_bg;
		$extended_colors['wps_dropdown_background'] = $dropdown_background;
		$extended_colors['wps_dropdown_text'] = $dropdown_text;
		$extended_colors['wps_dropdown_hover_background'] = $dropdown_hover_bg;
		$extended_colors['wps_dropdown_hover_text'] = $dropdown_hover_text;
		$extended_colors['wps_card_muted_color'] = $card_muted_color;
		$extended_colors['wps_taxonomy_hover_background'] = $taxonomy_hover_bg;
		$extended_colors['wps_taxonomy_hover_text'] = $taxonomy_hover_text;
		$extended_colors['wps_view_all_text_color'] = $view_all_text;
		$extended_colors['wps_input_background_color'] = $input_background;
		$extended_colors['wps_input_text_color'] = $input_text;
		$extended_colors['wps_input_placeholder_color'] = $input_placeholder;

		// Existing settings with clearer semantic aliases.
		$extended_colors['wps_primary_color']       = $main_color;
		$extended_colors['wps_link_color']          = $link_color;
		$extended_colors['wps_body_text_color']     = $ui_text;
		$extended_colors['wps_card_text_color']     = $card_text_color;
		$extended_colors['wps_taxonomy_surface']    = $taxonomy_surface;
		$extended_colors['wps_taxonomy_text']       = $taxonomy_text;
		$extended_colors['wps_taxonomy_muted']      = $taxonomy_muted_hex;
		$extended_colors['wps_featured_card_bg']   = $featured_card_bg;
		$extended_colors['wps_featured_card_text'] = $featured_card_text;
		$extended_colors['wps_tag_card_bg']        = $tag_card_bg;
		$extended_colors['wps_tag_card_text']      = $tag_card_text;

		$ui_muted       = function_exists( 'wpst_hex2rgba' ) ? wpst_hex2rgba( $ui_muted_hex, 0.72 ) : $ui_muted_hex;
		$soft_border    = function_exists( 'wpst_hex2rgba' ) ? wpst_hex2rgba( $soft_border_hex, 0.20 ) : $soft_border_hex;
		$taxonomy_muted = function_exists( 'wpst_hex2rgba' ) ? wpst_hex2rgba( $taxonomy_muted_hex, 0.72 ) : $taxonomy_muted_hex;

		$button_gradient = 'linear-gradient(90deg, ' . $button_start . ', ' . $button_middle . ', ' . $button_end . ')';
		$button_hover_gradient = $button_gradient;

		return compact(
			'main_color', 'link_color', 'site_background', 'soft_surface', 'soft_border_hex',
			'menu_background', 'menu_hover_bg', 'menu_active_bg', 'button_start', 'button_middle', 'button_end',
			'ui_text', 'ui_muted_hex', 'menu_text', 'menu_hover_text', 'menu_active_text', 'button_text', 'label_text',
			'taxonomy_surface', 'taxonomy_text', 'taxonomy_muted_hex', 'taxonomy_hover_bg', 'taxonomy_hover_text',
			'featured_card_bg', 'featured_card_text', 'tag_card_bg', 'tag_card_text',
			'card_surface_for_text', 'card_text_color', 'card_muted_color',
			'dropdown_background', 'dropdown_text', 'dropdown_hover_bg', 'dropdown_hover_text',
			'input_background', 'input_text', 'input_placeholder',
			'view_all_background', 'view_all_text',
			'footer_background', 'footer_text', 'footer_muted_hex',
			'main_contrast_text', 'on_light_surface', 'on_light_text', 'on_light_muted',
			'ghost_button_text', 'ghost_button_hover_text',
			'extended_colors', 'ui_muted', 'soft_border', 'taxonomy_muted',
			'button_gradient', 'button_hover_gradient'
		);
	}
}
if ( ! function_exists( 'wpst_customize_css' ) ) {
	function wpst_customize_css() {
		$t = wps_resolved_color_tokens();
		$extended = $t['extended_colors'];
		ob_start();
		?>
		<style type="text/css">
			/* Single-post text is derived from the saved site background; legacy text-color controls are not used. */
			html body.single-post .single-video-infos h1.single-video-title,
			html body.single-post .single-video-infos .video-description,
			html body.single-post .single-video-infos .video-description p,
			html body.single-post .single-video-infos .video-description li,
			html body.single-post .single-video-infos .video-taxonomy-title {
				color: <?php echo esc_attr( $t['ui_text'] ); ?> !important;
			}
			:root {
				--wps-site-background: <?php echo esc_attr( $t['site_background'] ); ?>;
				--wps-auto-text: <?php echo esc_attr( $t['ui_text'] ); ?>;
				--wps-auto-muted: <?php echo esc_attr( $t['ui_muted_hex'] ); ?>;
				--wps-footer-background: <?php echo esc_attr( $t['footer_background'] ); ?>;
				--wps-footer-text: <?php echo esc_attr( $t['footer_text'] ); ?>;
				--wps-footer-muted: <?php echo esc_attr( $t['footer_muted_hex'] ); ?>;
				--wps-on-light-surface: <?php echo esc_attr( $t['on_light_surface'] ); ?>;
				--wps-on-light-text: <?php echo esc_attr( $t['on_light_text'] ); ?>;
				--wps-on-light-muted: <?php echo esc_attr( $t['on_light_muted'] ); ?>;
				--wps-main-contrast-text: <?php echo esc_attr( $t['main_contrast_text'] ); ?>;
				/* Master: changing Main Color changes all linked accent controls unless manually overridden. */
				--wps-main-color: <?php echo esc_attr( $t['main_color'] ); ?>;
				--wps-link-color: <?php echo esc_attr( $t['link_color'] ); ?>;
				--wps-menu-background: <?php echo esc_attr( $t['menu_background'] ); ?>;
				--wps-menu-text: <?php echo esc_attr( $t['menu_text'] ); ?>;
				--wps-menu-hover-text: <?php echo esc_attr( $t['menu_hover_text'] ); ?>;
				--wps-menu-active-bg: <?php echo esc_attr( $t['menu_active_bg'] ); ?>;
				--wps-menu-active-text: <?php echo esc_attr( $t['menu_active_text'] ); ?>;
				--wps-button-gradient: <?php echo esc_attr( $t['button_gradient'] ); ?>;
				--wps-button-hover-gradient: <?php echo esc_attr( $t['button_hover_gradient'] ); ?>;
				--wps-button-text: <?php echo esc_attr( $t['button_text'] ); ?>;
				--wps-ghost-button-text: <?php echo esc_attr( $t['ghost_button_text'] ); ?>;
				--wps-ghost-button-hover-text: <?php echo esc_attr( $t['ghost_button_hover_text'] ); ?>;
				--wps-ui-text: <?php echo esc_attr( $t['ui_text'] ); ?>;
				--wps-ui-muted: <?php echo esc_attr( $t['ui_muted'] ); ?>;
				--wps-soft-surface: <?php echo esc_attr( $t['soft_surface'] ); ?>;
				--wps-soft-border: <?php echo esc_attr( $t['soft_border'] ); ?>;
				--wps-taxonomy-surface: <?php echo esc_attr( $t['taxonomy_surface'] ); ?>;
				--wps-taxonomy-text: <?php echo esc_attr( $t['taxonomy_text'] ); ?>;
				--wps-taxonomy-muted: <?php echo esc_attr( $t['taxonomy_muted'] ); ?>;
				--wps-featured-card-bg: <?php echo esc_attr( $t['featured_card_bg'] ); ?>;
				--wps-featured-card-text: <?php echo esc_attr( $t['featured_card_text'] ); ?>;
				--wps-tag-card-bg: <?php echo esc_attr( $t['tag_card_bg'] ); ?>;
				--wps-tag-card-text: <?php echo esc_attr( $t['tag_card_text'] ); ?>;
--wps-menu-hover-background: <?php echo esc_attr( $t['menu_hover_bg'] ); ?>;
				--wps-dropdown-background: <?php echo esc_attr( $t['dropdown_background'] ); ?>;
				--wps-dropdown-text: <?php echo esc_attr( $t['dropdown_text'] ); ?>;
				--wps-dropdown-border: <?php echo esc_attr( $extended['wps_dropdown_border'] ); ?>;
				--wps-dropdown-hover-background: <?php echo esc_attr( $t['dropdown_hover_bg'] ); ?>;
				--wps-dropdown-hover-text: <?php echo esc_attr( $t['dropdown_hover_text'] ); ?>;
				--wps-button-start: <?php echo esc_attr( $t['button_start'] ); ?>;
				--wps-button-middle: <?php echo esc_attr( $t['button_middle'] ); ?>;
				--wps-button-end: <?php echo esc_attr( $t['button_end'] ); ?>;
				--wps-button-border: <?php echo esc_attr( $extended['wps_button_border_color'] ); ?>;
				--wps-button-hover-border: <?php echo esc_attr( $extended['wps_button_hover_border_color'] ); ?>;
				--wps-button-shadow: <?php echo esc_attr( $extended['wps_button_shadow_color'] ); ?>;
				--wps-button-hover-shadow: <?php echo esc_attr( $extended['wps_button_hover_shadow_color'] ); ?>;
				--wps-label-border: <?php echo esc_attr( $extended['wps_label_border_color'] ); ?>;
				--wps-label-shadow: <?php echo esc_attr( $extended['wps_label_shadow_color'] ); ?>;
				--wps-label-gradient: <?php echo esc_attr( $t['button_gradient'] ); ?>;
				--wps-card-surface: <?php echo esc_attr( $t['card_surface_for_text'] ); ?>;
				--wps-card-text: <?php echo esc_attr( $t['card_text_color'] ); ?>;
				--wps-card-muted: <?php echo esc_attr( $t['card_muted_color'] ); ?>;
				--wps-card-border: <?php echo esc_attr( $extended['wps_card_border_color'] ); ?>;
				--wps-card-border-hover: <?php echo esc_attr( $extended['wps_card_hover_border_color'] ); ?>;
				--wps-card-shadow: <?php echo esc_attr( $extended['wps_card_shadow_color'] ); ?>;
				--wps-card-hover-shadow: <?php echo esc_attr( $extended['wps_card_hover_shadow_color'] ); ?>;
				--wps-card-shadow-soft: <?php echo esc_attr( $extended['wps_card_shadow_color'] ); ?>;
				--wps-card-hover-glow: <?php echo esc_attr( $extended['wps_card_hover_shadow_color'] ); ?>;
				--wps-post-image-bg: <?php echo esc_attr( $extended['wps_card_surface_color'] ); ?>;
				--wps-taxonomy-border: <?php echo esc_attr( $extended['wps_taxonomy_border_color'] ); ?>;
				--wps-taxonomy-border-hover: <?php echo esc_attr( $extended['wps_taxonomy_hover_border_color'] ); ?>;
				--wps-taxonomy-hover-background: <?php echo esc_attr( $t['taxonomy_hover_bg'] ); ?>;
				--wps-taxonomy-hover-text: <?php echo esc_attr( $t['taxonomy_hover_text'] ); ?>;
				--wps-taxonomy-shadow: <?php echo esc_attr( $extended['wps_taxonomy_shadow_color'] ); ?>;
				--wps-taxonomy-hover-shadow: <?php echo esc_attr( $extended['wps_taxonomy_hover_shadow_color'] ); ?>;
				--wps-featured-card-border: <?php echo esc_attr( $extended['wps_featured_card_border_color'] ); ?>;
				--wps-featured-card-shadow: <?php echo esc_attr( $extended['wps_featured_card_shadow_color'] ); ?>;
				--wps-featured-card-hover: <?php echo esc_attr( $extended['wps_featured_card_hover_color'] ); ?>;
				--wps-tag-card-border: <?php echo esc_attr( $extended['wps_tag_card_border_color'] ); ?>;
				--wps-tag-card-shadow: <?php echo esc_attr( $extended['wps_tag_card_shadow_color'] ); ?>;
				--wps-tag-card-hover: <?php echo esc_attr( $extended['wps_tag_card_hover_color'] ); ?>;
				--wps-view-all-background: <?php echo esc_attr( $extended['wps_view_all_background_color'] ); ?>;
				--wps-view-all-shadow: <?php echo esc_attr( $extended['wps_view_all_shadow_color'] ); ?>;
				--wps-input-background: <?php echo esc_attr( $t['input_background'] ); ?>;
				--wps-input-text: <?php echo esc_attr( $t['input_text'] ); ?>;
				--wps-input-border: <?php echo esc_attr( $extended['wps_input_border_color'] ); ?>;
				--wps-input-focus-border: <?php echo esc_attr( $extended['wps_input_focus_border_color'] ); ?>;
				--wps-input-placeholder: <?php echo esc_attr( $t['input_placeholder'] ); ?>;
				--wps-single-poster-bg: <?php echo esc_attr( $extended['wps_single_poster_bg_color'] ); ?>;
				--wps-single-poster-border: <?php echo esc_attr( $extended['wps_single_poster_border_color'] ); ?>;
				--wps-single-poster-shadow: <?php echo esc_attr( $extended['wps_single_poster_shadow_color'] ); ?>;
				--wps-success: <?php echo esc_attr( $extended['wps_success_color'] ); ?>;
				--wps-warning: <?php echo esc_attr( $extended['wps_warning_color'] ); ?>;
				--wps-danger: <?php echo esc_attr( $extended['wps_danger_color'] ); ?>;
				--wps-info: <?php echo esc_attr( $extended['wps_info_color'] ); ?>;
				--wps-menu-active-background: <?php echo esc_attr( $t['menu_active_bg'] ); ?>;
				--wps-primary-color: <?php echo esc_attr( $t['main_color'] ); ?>;
				--wps-body-text-color: <?php echo esc_attr( $t['ui_text'] ); ?>;
				--wps-icon-color: <?php echo esc_attr( $t['ui_text'] ); ?>;
			}
body,
			.navbar-expand-xl .navbar-nav .dropdown-menu {
				color: <?php echo wpst_get_brightness( $t['site_background'], 'rgba(255,255,255,0.75)', 'rgba(0,0,0,0.75)' ); ?>!important;
				background: <?php echo esc_attr( $t['site_background'] ); ?>!important;
			}
			body {
				background-color: var(--wps-site-background) !important;
			}
			#wrapper-footer .site-footer,
			#wrapper-footer .site-info {
				background: var(--wps-footer-background);
				color: var(--wps-footer-text);
			}
			body #wrapper-footer .footer-menu-list a,
			body #wrapper-footer .footer-link-list a,
			body #wrapper-footer .footer-site-tagline {
				color: var(--wps-footer-text);
			}
			#wrapper-navbar,
			.navbar,
			.navbar-collapse {
				background-color: var(--wps-menu-background) !important;
			}
			.body-gradient {
				background: -moz-linear-gradient(45deg, <?php echo wpst_get_brightness( $t['site_background'], 'rgba(0,0,0,0.50)', 'rgba(255,255,255,0)' ); ?> 0%, <?php echo wpst_get_brightness( $t['site_background'], 'rgba(0,0,0,0)', 'rgba(255,255,255,0.50)' ); ?> 100%);
				background: -webkit-linear-gradient(45deg, <?php echo wpst_get_brightness( $t['site_background'], 'rgba(0,0,0,0.50)', 'rgba(255,255,255,0)' ); ?> 0%, <?php echo wpst_get_brightness( $t['site_background'], 'rgba(0,0,0,0)', 'rgba(255,255,255,0.50)' ); ?> 100%);
			}
		</style>
		<?php
		return ob_get_clean();
	}
}

if ( ! function_exists( 'wpst_render_customize_css' ) ) {
	/**
	 * Echo the Theme Color CSS on wp_head.
	 *
	 * wpst_customize_css() returns the CSS string for compatibility; hooks
	 * must echo it or the browser never receives the --wps-* variables and
	 * the frontend silently falls back to hard-coded colors.
	 */
	function wpst_render_customize_css() {
		echo wpst_customize_css(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
add_action( 'wp_head', 'wpst_render_customize_css', 999 );
if ( ! function_exists( 'wps_get_theme_palette' ) ) {
	/**
	 * Build the complete Theme Color palette record.
	 *
	 * Includes every resolved front-end value (with auto-contrast text) and
	 * every stored theme_mod value with its registered default. This is the
	 * record persisted into WordPress meta (wp_options) on each Customizer
	 * save and exposed through the front-end AJAX endpoint.
	 *
	 * @return array<string,mixed>
	 */
	function wps_get_theme_palette() {
		$registry = wps_get_color_registry();
		$stored   = array();
		foreach ( $registry as $id => $meta ) {
			$stored[ $id ] = array(
				'saved'   => function_exists( 'get_theme_mod' ) ? get_theme_mod( $id ) : null,
				'default' => $meta['default'],
				'label'   => $meta['label'],
			);
		}

		return array(
			'version'   => '1',
			'theme'     => get_stylesheet(),
			'generated' => current_time( 'mysql' ),
			'resolved'  => wps_resolved_color_tokens(),
			'stored'    => $stored,
		);
	}
}

if ( ! function_exists( 'wps_persist_palette_meta' ) ) {
	/**
	 * Store the resolved Theme Color palette into an actual WordPress option
	 * (meta storage) every time the Customizer is saved.
	 *
	 * @return void
	 */
	function wps_persist_palette_meta() {
		$option = 'theme_palette_' . sanitize_key( get_stylesheet() );
		update_option( $option, wps_get_theme_palette(), false );
	}
}

if ( ! function_exists( 'wps_register_palette_persist' ) ) {
	function wps_register_palette_persist( $manager ) {
		wps_persist_palette_meta();
	}
}
add_action( 'customize_save_after', 'wps_register_palette_persist', 20 );

if ( ! function_exists( 'wps_ajax_theme_palette' ) ) {
	/**
	 * Expose the resolved Theme Color palette to the frontend.
	 */
	function wps_ajax_theme_palette() {
		wp_send_json( wps_get_theme_palette() );
	}
}
add_action( 'wp_ajax_nopriv_wps_theme_palette', 'wps_ajax_theme_palette' );
add_action( 'wp_ajax_wps_theme_palette', 'wps_ajax_theme_palette' );

if ( ! function_exists( 'wpst_add_variables' ) ) {
	function wpst_add_variables( $variables ) {
		$output  = '<p style="margin: 10px 0;">Available variable:</p>';
		$output .= '<ul style="padding: 0; margin: 0;">';
		foreach ( $variables as $key => $description ) {
			$output .= '<li style="list-style: none;"><code>' . $key . '</code>: ' . $description . '</li>';
		}
		$output .= '</ul>';
		return $output;
	}
}

/**
 * Sanitize mobile columns selection.
 *
 * @param mixed $input The input value.
 * @return string '1' or '2'
 */
if ( ! function_exists( 'wpst_sanitize_mobile_columns' ) ) {
	function wpst_sanitize_mobile_columns( $input ) {
		$valid = array( '1', '2' );
		$val   = (string) $input;
		return in_array( $val, $valid, true ) ? $val : '2';
	}
}
/**
 * KolorTube 1.9.10 — Pink Palette frontend output.
 *
 * Runs only through the theme WP-Script core (next to the main Customizer
 * output, hooked after it). Every color below is a real theme_mod from the
 * central color registry, so the values can be adjusted from the Customizer.
 *
 * Applied to: category / tag / actor / studio labels, capsule buttons and the
 * active Like button. Text is always white.
 */
if ( ! function_exists( 'wps_render_pink_palette_css' ) ) {
	function wps_render_pink_palette_css() {
		$label_start = wps_get_color_setting( 'wps_pink_label_gradient_start', '#ff6b9d' );
		$label_end   = wps_get_color_setting( 'wps_pink_label_gradient_end', '#ff4f81' );
		$hover_start = wps_get_color_setting( 'wps_pink_label_hover_start', '#ff4f81' );
		$hover_end   = wps_get_color_setting( 'wps_pink_label_hover_end', '#e91e63' );
		$like_start  = wps_get_color_setting( 'wps_pink_like_active_start', '#e91e63' );
		$like_end    = wps_get_color_setting( 'wps_pink_like_active_end', '#c2185b' );

		if ( ! $label_start ) { $label_start = '#ff6b9d'; }
		if ( ! $label_end )   { $label_end   = '#ff4f81'; }
		if ( ! $hover_start ) { $hover_start = '#ff4f81'; }
		if ( ! $hover_end )   { $hover_end   = '#e91e63'; }
		if ( ! $like_start )  { $like_start  = '#e91e63'; }
		if ( ! $like_end )    { $like_end    = '#c2185b'; }

		$label_gradient = 'linear-gradient(135deg, ' . $label_start . ' 0%, ' . $label_end . ' 100%)';
		$hover_gradient = 'linear-gradient(135deg, ' . $hover_start . ' 0%, ' . $hover_end . ' 100%)';
		$like_gradient  = 'linear-gradient(135deg, ' . $like_start . ' 0%, ' . $like_end . ' 100%)';

		$label_selectors = array(
			'.single-video-infos .tags-list a.label',
			'.single-video-infos .category-list a.label',
			'.single-video-infos .actor-list a.label',
			'.single-video-infos .studio-list a.label',
			'.single-video-infos .tag-list a.label',
			'.tags-list a.label',
			'.category-list a.label',
			'.actor-list a.label',
			'.studio-list a.label',
			'.tag-list a.label',
			'.wps-category-label',
			'.wps-tag-label',
			'.wps-actor-label',
			'.wps-studio-label',
		);
		$capsule_selectors = array(
			'.btn-capsule',
			'.wps-btn-pink',
			'.video-buttons-row .capsule-btn',
			'.video-buttons-row .capsule-btn.is-active',
			'.video-buttons-row .capsule-btn-share.active',
			'.video-buttons-row .capsule-btn-share[aria-expanded="true"]',
			'.wps-like-btn',
			'.wps-save-btn',
			'.wps-share-btn',
		);
		$like_selectors = array(
			'.video-buttons-row .capsule-btn.capsule-btn-like.is-active',
			'.video-buttons-row .capsule-btn-like.is-active',
			'.video-buttons-row .capsule-btn-like.active',
			'.video-buttons-row .capsule-btn-like.already-voted',
			'.video-buttons-row .capsule-btn-like[aria-pressed="true"]',
			'.wps-like-btn.active',
			'.wps-like-btn.liked',
		);
		?>
		<style type="text/css" id="wps-pink-palette-css">
			<?php echo implode( ',', $label_selectors ); ?>,
			<?php echo implode( ',', $capsule_selectors ); ?> {
				background: <?php echo esc_attr( $label_gradient ); ?> !important;
				background-image: <?php echo esc_attr( $label_gradient ); ?> !important;
				color: #ffffff !important;
				border: none !important;
			}
			<?php echo implode( ',', $label_selectors ); ?>:hover,
			<?php echo implode( ',', $capsule_selectors ); ?>:hover,
			<?php echo implode( ',', $capsule_selectors ); ?>:focus-visible {
				background: <?php echo esc_attr( $hover_gradient ); ?> !important;
				background-image: <?php echo esc_attr( $hover_gradient ); ?> !important;
				color: #ffffff !important;
				border: none !important;
			}
			<?php echo implode( ',', $like_selectors ); ?> {
				background: <?php echo esc_attr( $like_gradient ); ?> !important;
				background-image: <?php echo esc_attr( $like_gradient ); ?> !important;
				color: #ffffff !important;
				border: none !important;
			}
		</style>
		<?php
	}
}
add_action( 'wp_head', 'wps_render_pink_palette_css', 1000 );
