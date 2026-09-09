/**
 * File customizer.js
 *
 * Theme Customizer live-preview enhancements.
 *
 * The Theme Color system now derives every text color from its actual surface
 * using the same WCAG relative-luminance math as the server (customizer-output.php).
 * When the user drags a color picker, this script recomputes the whole token
 * palette in the preview so the visible colors always match what is saved.
 */

( function( $ ) {

	// Site title and description.
	wp.customize( 'blogname', function( value ) {
		value.bind( function( to ) {
			$( '.site-title a' ).text( to );
		} );
	} );
	wp.customize( 'blogdescription', function( value ) {
		value.bind( function( to ) {
			$( '.site-description' ).text( to );
		} );
	} );

	// ---- Color helpers (mirrors inc/customizer/customizer-output.php) ----

	function normalizeHex( hex ) {
		if ( ! hex ) { return ''; }
		var h = String( hex ).replace( /^#/, '' ).trim();
		if ( /^[0-9a-fA-F]{3}$/.test( h ) ) {
			h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
		}
		return /^[0-9a-fA-F]{6}$/.test( h ) ? '#' + h.toLowerCase() : '';
	}

	function luminance( hex ) {
		var h = normalizeHex( hex || '#000000' ).slice( 1 );
		var rgb = [ parseInt( h.substr( 0, 2 ), 16 ) / 255, parseInt( h.substr( 2, 2 ), 16 ) / 255, parseInt( h.substr( 4, 2 ), 16 ) / 255 ];
		var lin = rgb.map( function( c ) {
			return c <= 0.04045 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
	}

	function contrast( a, b ) {
		var la = luminance( a ), lb = luminance( b );
		return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
	}

	function contrastText( background, light, dark ) {
		var base = normalizeHex( background ) || '#000000';
		light = normalizeHex( light ) || '#ffffff';
		dark  = normalizeHex( dark ) || '#111111';
		return contrast( base, dark ) >= contrast( base, light ) ? dark : light;
	}

	function rgbFromHex( hex ) {
		var h = normalizeHex( hex ).slice( 1 );
		return [ parseInt( h.substr( 0, 2 ), 16 ), parseInt( h.substr( 2, 2 ), 16 ), parseInt( h.substr( 4, 2 ), 16 ) ];
	}

	function toHex( r, g, b ) {
		function p( v ) { v = Math.max( 0, Math.min( 255, Math.round( v ) ) ); return ( '0' + v.toString( 16 ) ).slice( -2 ); }
		return '#' + p( r ) + p( g ) + p( b );
	}

	function blendOver( topHex, alpha, bottomHex ) {
		var t = rgbFromHex( topHex ), b = rgbFromHex( bottomHex );
		alpha = Math.max( 0, Math.min( 1, alpha ) );
		return toHex( t[0] * alpha + b[0] * ( 1 - alpha ), t[1] * alpha + b[1] * ( 1 - alpha ), t[2] * alpha + b[2] * ( 1 - alpha ) );
	}

	function brightnessOf( hex, light, dark ) {
		var h = normalizeHex( hex || '#000000' ).slice( 1 );
		var r = parseInt( h.substr( 0, 2 ), 16 ), g = parseInt( h.substr( 2, 2 ), 16 ), b = parseInt( h.substr( 4, 2 ), 16 );
		return ( r * 299 + g * 587 + b * 114 ) / 1000 > 180 ? dark : light;
	}

	function masterVariant( hex, lighten, hueShift ) {
		var h = normalizeHex( hex || '#26adfe' ).slice( 1 );
		var rgb = [ parseInt( h.substr( 0, 2 ), 16 ), parseInt( h.substr( 2, 2 ), 16 ), parseInt( h.substr( 4, 2 ), 16 ) ];
		var max = Math.max.apply( null, rgb ), min = Math.min.apply( null, rgb ), d = max - min;
		var l = ( max + min ) / 510, s = 0, hue = 0;
		if ( d ) {
			s = d / ( 255 - Math.abs( 2 * l * 255 - 255 ) );
			if ( max === rgb[0] ) { hue = 60 * ( ( ( rgb[1] - rgb[2] ) / d ) % 6 ); }
			else if ( max === rgb[1] ) { hue = 60 * ( ( rgb[2] - rgb[0] ) / d + 2 ); }
			else { hue = 60 * ( ( rgb[0] - rgb[1] ) / d + 4 ); }
		}
		if ( hue < 0 ) { hue += 360; }
		hue = ( hue + ( hueShift || 0 ) + 360 ) % 360;
		l = Math.max( 0.12, Math.min( 0.82, l + ( lighten || 0 ) ) );
		var c = ( 1 - Math.abs( 2 * l - 1 ) ) * s;
		var x = c * ( 1 - Math.abs( ( ( hue / 60 ) % 2 ) - 1 ) );
		var m = l - c / 2, parts = [];
		if ( hue < 60 ) { parts = [ c, x, 0 ]; }
		else if ( hue < 120 ) { parts = [ x, c, 0 ]; }
		else if ( hue < 180 ) { parts = [ 0, c, x ]; }
		else if ( hue < 240 ) { parts = [ 0, x, c ]; }
		else if ( hue < 300 ) { parts = [ x, 0, c ]; }
		else { parts = [ c, 0, x ]; }
		return toHex( ( parts[0] + m ) * 255, ( parts[1] + m ) * 255, ( parts[2] + m ) * 255 );
	}
// ---- Registered defaults (single source of truth on the client too) ----

	var DEFAULT_MASTER = '#26adfe';
	var defaults = {
		'main_color': '#26adfe',
		'wps_site_background_color': '#010012',
		'link_color': '#26adfe',
		'body_background_color': '#010012',
		'wps_soft_surface_color': '#08071b',
		'wps_soft_border_color': '#26adfe',
		'wps_menu_background_color': '#020016',
		'wps_menu_hover_background': '#20104d',
		'wps_menu_active_bg': '#7b18ff',
		'wps_dropdown_background': '#07051a',
		'wps_dropdown_border': '#26adfe',
		'wps_dropdown_hover_background': '#24104d',
		'wps_button_start_color': '#26adfe',
		'wps_button_middle_color': '#7b18ff',
		'wps_button_end_color': '#c00bff',
		'wps_button_border_color': '#26adfe',
		'wps_button_hover_border_color': '#c00bff',
		'wps_button_shadow_color': '#26adfe',
		'wps_button_hover_shadow_color': '#c00bff',
		'wps_label_border_color': '#26adfe',
		'wps_label_shadow_color': '#7b18ff',
		'wps_card_surface_color': '#08071b',
		'wps_card_border_color': '#24154f',
		'wps_card_hover_border_color': '#26adfe',
		'wps_card_shadow_color': '#000000',
		'wps_card_hover_shadow_color': '#7b18ff',
		'wps_taxonomy_surface': '#100a25',
		'wps_taxonomy_border_color': '#26adfe',
		'wps_taxonomy_hover_border_color': '#c00bff',
		'wps_taxonomy_hover_background': '#24104d',
		'wps_taxonomy_shadow_color': '#000000',
		'wps_taxonomy_hover_shadow_color': '#7b18ff',
		'wps_featured_card_bg': '#08071b',
		'wps_featured_card_border_color': '#24154f',
		'wps_featured_card_shadow_color': '#000000',
		'wps_featured_card_hover_color': '#7b18ff',
		'wps_tag_card_bg': '#08071b',
		'wps_tag_card_border_color': '#24154f',
		'wps_tag_card_shadow_color': '#000000',
		'wps_tag_card_hover_color': '#7b18ff',
		'wps_view_all_background_color': '#7b18ff',
		'wps_view_all_shadow_color': '#7b18ff',
		'wps_input_background_color': '#07051a',
		'wps_input_border_color': '#24154f',
		'wps_input_focus_border_color': '#26adfe',
		'wps_single_poster_bg_color': '#000000',
		'wps_single_poster_border_color': '#24154f',
		'wps_single_poster_shadow_color': '#000000',
		'wps_footer_background_color': '',
		'wps_success_color': '#22c55e',
		'wps_warning_color': '#f59e0b',
		'wps_danger_color': '#ef4444',
		'wps_info_color': '#26adfe'
	};

	var legacyPink = [ '#ff2d80', '#ff2f8f', '#f42370', '#ff4d8d', '#ff6ba3', '#d93f89', '#a70742', '#c92b80', '#d10954', '#ee519e', '#f1379b' ];

	function getVar( id ) {
		var v = wp.customize( id );
		if ( ! v ) { return ''; }
		return v.get() || '';
	}

	function colorWithMaster( id, fallback ) {
		var stored = normalizeHex( getVar( id ) ) || '';
		var norm = typeof stored === 'string' ? stored.toLowerCase() : '';
		var dflt = ( defaults[ id ] || '' ).toLowerCase();
		if ( '' === norm || norm === dflt || legacyPink.indexOf( norm ) !== -1 ) {
			return normalizeHex( fallback ) || '';
		}
		return norm !== '' ? normalizeHex( stored ) : '';
	}
function applyThemePreview() {
		var main = normalizeHex( getVar( 'main_color' ) ) || DEFAULT_MASTER;
		var link = colorWithMaster( 'link_color', main ) || main;
		var siteBg = normalizeHex( getVar( 'wps_site_background_color' ) ) || defaults['wps_site_background_color'];
		var softSurface = normalizeHex( getVar( 'wps_soft_surface_color' ) ) || defaults['wps_soft_surface_color'];
		var softBorderHex = normalizeHex( getVar( 'wps_soft_border_color' ) ) || defaults['wps_soft_border_color'];

		var menuBg = normalizeHex( getVar( 'wps_menu_background_color' ) ) || defaults['wps_menu_background_color'];
		var menuHoverBg = colorWithMaster( 'wps_menu_hover_background', masterVariant( main, 0.00, 35 ) ) || menuBg;
		var menuActiveBg = colorWithMaster( 'wps_menu_active_bg', masterVariant( main, 0.02, 35 ) ) || menuBg;

		var btnStart = colorWithMaster( 'wps_button_start_color', masterVariant( main, 0.10, 0 ) ) || main;
		var btnMiddle = colorWithMaster( 'wps_button_middle_color', masterVariant( main, 0.00, 55 ) ) || main;
		var btnEnd = colorWithMaster( 'wps_button_end_color', masterVariant( main, -0.02, 75 ) ) || main;

		var uiText = contrastText( siteBg );
		var uiMutedHex = contrastText( siteBg, '#e7e7ef', '#4b4b5a' );
		var menuText = contrastText( menuBg );
		var menuHoverText = contrastText( menuHoverBg );
		var menuActiveText = contrastText( menuActiveBg );
		var btnText = contrastText( btnMiddle );
		var labelText = btnText;

		var taxSurface = normalizeHex( getVar( 'wps_taxonomy_surface' ) ) || defaults['wps_taxonomy_surface'];
		var taxText = contrastText( taxSurface );
		var taxMutedHex = contrastText( taxSurface, '#e7e7ef', '#4b4b5a' );
		var taxHoverBg = normalizeHex( getVar( 'wps_taxonomy_hover_background' ) ) || defaults['wps_taxonomy_hover_background'];
		var taxHoverText = contrastText( taxHoverBg );
		var featuredBg = normalizeHex( getVar( 'wps_featured_card_bg' ) ) || defaults['wps_featured_card_bg'];
		var featuredText = contrastText( featuredBg );
		var tagBg = normalizeHex( getVar( 'wps_tag_card_bg' ) ) || defaults['wps_tag_card_bg'];
		var tagText = contrastText( tagBg );
		var cardSurface = normalizeHex( getVar( 'wps_card_surface_color' ) ) || defaults['wps_card_surface_color'];
		var cardText = contrastText( cardSurface );
		var cardMuted = contrastText( cardSurface, '#d9d9e3', '#555563' );
		var ddBg = normalizeHex( getVar( 'wps_dropdown_background' ) ) || defaults['wps_dropdown_background'];
		var ddText = contrastText( ddBg );
		var ddHoverBg = normalizeHex( getVar( 'wps_dropdown_hover_background' ) ) || defaults['wps_dropdown_hover_background'];
		var ddHoverText = contrastText( ddHoverBg );
		var inputBg = normalizeHex( getVar( 'wps_input_background_color' ) ) || defaults['wps_input_background_color'];
		var inputText = contrastText( inputBg );
		var inputPlaceholderHex = contrastText( inputBg, '#e7e7ef', '#4b4b5a' );
		var viewAllBg = colorWithMaster( 'wps_view_all_background_color', defaults['wps_view_all_background_color'] ) || defaults['wps_view_all_background_color'];
		var viewAllText = contrastText( viewAllBg );

		var footerBg = normalizeHex( getVar( 'wps_footer_background_color' ) );
		if ( ! footerBg ) {
			footerBg = brightnessOf( siteBg, '#04040d', '#f4f5fb' );
		}
		var footerText = contrastText( footerBg );
		var footerMutedHex = contrastText( footerBg, '#e7e7ef', '#4b4b5a' );

		var mainContrastText = contrastText( main );
		var onLightSurface = blendOver( '#ffffff', 0.78, siteBg );
		var onLightText = contrastText( onLightSurface, '#ffffff', '#1d1d25' );

		/* Ghost / translucent buttons (Save, Share, generic .capsule-btn)
		 * sit directly on the site background with a 12% main-color overlay.
		 * Resolve contrast from that blended surface so text never disappears
		 * against a dark site background. */
		var ghostButtonSurface = blendOver( main, 0.12, siteBg );
		var ghostButtonText = contrastText( ghostButtonSurface, '#ffffff', '#1d1d25' );
		var ghostButtonHoverSurface = blendOver( ext['wps_button_hover_border_color'], 0.18, siteBg );
		var ghostButtonHoverText = contrastText( ghostButtonHoverSurface, '#ffffff', '#1d1d25' );
var ext = {};
		var extDefaults = {
			'wps_menu_hover_background': '#20104d', 'wps_dropdown_background': '#07051a',
			'wps_dropdown_border': '#26adfe', 'wps_dropdown_hover_background': '#24104d',
			'wps_button_border_color': '#26adfe', 'wps_button_hover_border_color': '#c00bff',
			'wps_button_shadow_color': '#26adfe', 'wps_button_hover_shadow_color': '#c00bff',
			'wps_label_border_color': '#26adfe', 'wps_label_shadow_color': '#7b18ff',
			'wps_card_surface_color': '#08071b', 'wps_card_border_color': '#24154f',
			'wps_card_hover_border_color': '#26adfe', 'wps_card_shadow_color': '#000000',
			'wps_card_hover_shadow_color': '#7b18ff', 'wps_taxonomy_border_color': '#26adfe',
			'wps_taxonomy_hover_border_color': '#c00bff', 'wps_taxonomy_hover_background': '#24104d',
			'wps_taxonomy_shadow_color': '#000000', 'wps_taxonomy_hover_shadow_color': '#7b18ff',
			'wps_featured_card_border_color': '#24154f', 'wps_featured_card_shadow_color': '#000000',
			'wps_featured_card_hover_color': '#7b18ff', 'wps_tag_card_border_color': '#24154f',
			'wps_tag_card_shadow_color': '#000000', 'wps_tag_card_hover_color': '#7b18ff',
			'wps_view_all_background_color': '#7b18ff', 'wps_view_all_shadow_color': '#7b18ff',
			'wps_input_background_color': '#07051a', 'wps_input_border_color': '#24154f',
			'wps_input_focus_border_color': '#26adfe', 'wps_single_poster_bg_color': '#000000',
			'wps_single_poster_border_color': '#24154f', 'wps_single_poster_shadow_color': '#000000',
			'wps_success_color': '#22c55e', 'wps_warning_color': '#f59e0b',
			'wps_danger_color': '#ef4444', 'wps_info_color': '#26adfe'
		};
		var followers = {
			'wps_menu_hover_background': true, 'wps_dropdown_border': true, 'wps_button_border_color': true,
			'wps_button_hover_border_color': true, 'wps_button_shadow_color': true, 'wps_button_hover_shadow_color': true,
			'wps_label_border_color': true, 'wps_label_shadow_color': true, 'wps_card_hover_border_color': true,
			'wps_card_hover_shadow_color': true, 'wps_taxonomy_border_color': true, 'wps_taxonomy_hover_border_color': true,
			'wps_taxonomy_hover_shadow_color': true, 'wps_featured_card_hover_color': true, 'wps_tag_card_hover_color': true,
			'wps_view_all_background_color': true, 'wps_view_all_shadow_color': true, 'wps_input_focus_border_color': true,
			'wps_info_color': true
		};
		Object.keys( extDefaults ).forEach( function( key ) {
			if ( followers[ key ] ) {
				ext[ key ] = colorWithMaster( key, extDefaults[ key ] ) || extDefaults[ key ];
			} else {
				ext[ key ] = normalizeHex( getVar( key ) ) || extDefaults[ key ];
			}
		} );

		var uiMuted = hexToRgbaLocal( uiMutedHex, 0.72 );
		var softBorder = hexToRgbaLocal( softBorderHex, 0.20 );
		var taxMuted = hexToRgbaLocal( taxMutedHex, 0.72 );
		var btnGradient = 'linear-gradient(90deg, ' + btnStart + ', ' + btnMiddle + ', ' + btnEnd + ')';

		function hexToRgbaLocal( hex, alpha ) {
			var r = rgbFromHex( normalizeHex( hex ) || '#000000' );
			return 'rgba(' + r[0] + ',' + r[1] + ',' + r[2] + ',' + ( alpha === undefined ? 1 : alpha ) + ')';
		}
var props = {
			'--wps-site-background': siteBg,
			'--wps-auto-text': uiText,
			'--wps-auto-muted': uiMutedHex,
			'--wps-footer-background': footerBg,
			'--wps-footer-text': footerText,
			'--wps-footer-muted': footerMutedHex,
			'--wps-on-light-surface': onLightSurface,
			'--wps-on-light-text': onLightText,
			'--wps-on-light-muted': hexToRgbaLocal( onLightText, 0.72 ),
			'--wps-main-contrast-text': mainContrastText,
			'--wps-main-color': main,
			'--wps-link-color': link,
			'--wps-menu-background': menuBg,
			'--wps-menu-text': menuText,
			'--wps-menu-hover-text': menuHoverText,
			'--wps-menu-active-bg': menuActiveBg,
			'--wps-menu-active-text': menuActiveText,
			'--wps-button-gradient': btnGradient,
			'--wps-button-hover-gradient': btnGradient,
			'--wps-button-text': btnText,
			'--wps-ghost-button-text': ghostButtonText,
			'--wps-ghost-button-hover-text': ghostButtonHoverText,
			'--wps-ui-text': uiText,
			'--wps-ui-muted': uiMuted,
			'--wps-soft-surface': softSurface,
			'--wps-soft-border': softBorder,
			'--wps-taxonomy-surface': taxSurface,
			'--wps-taxonomy-text': taxText,
			'--wps-taxonomy-muted': taxMuted,
			'--wps-featured-card-bg': featuredBg,
			'--wps-featured-card-text': featuredText,
			'--wps-tag-card-bg': tagBg,
			'--wps-tag-card-text': tagText,
			'--wps-menu-hover-background': menuHoverBg,
			'--wps-dropdown-background': ddBg,
			'--wps-dropdown-text': ddText,
			'--wps-dropdown-border': ext['wps_dropdown_border'],
			'--wps-dropdown-hover-background': ddHoverBg,
			'--wps-dropdown-hover-text': ddHoverText,
			'--wps-button-start': btnStart,
			'--wps-button-middle': btnMiddle,
			'--wps-button-end': btnEnd,
			'--wps-button-border': ext['wps_button_border_color'],
			'--wps-button-hover-border': ext['wps_button_hover_border_color'],
			'--wps-button-shadow': ext['wps_button_shadow_color'],
			'--wps-button-hover-shadow': ext['wps_button_hover_shadow_color'],
			'--wps-label-border': ext['wps_label_border_color'],
			'--wps-label-shadow': ext['wps_label_shadow_color'],
			'--wps-label-gradient': btnGradient,
			'--wps-card-surface': cardSurface,
			'--wps-card-text': cardText,
			'--wps-card-muted': cardMuted,
			'--wps-card-border': ext['wps_card_border_color'],
			'--wps-card-border-hover': ext['wps_card_hover_border_color'],
			'--wps-card-shadow': ext['wps_card_shadow_color'],
			'--wps-card-hover-shadow': ext['wps_card_hover_shadow_color'],
			'--wps-card-shadow-soft': ext['wps_card_shadow_color'],
			'--wps-card-hover-glow': ext['wps_card_hover_shadow_color'],
			'--wps-post-image-bg': ext['wps_card_surface_color'],
			'--wps-taxonomy-border': ext['wps_taxonomy_border_color'],
			'--wps-taxonomy-border-hover': ext['wps_taxonomy_hover_border_color'],
			'--wps-taxonomy-hover-background': taxHoverBg,
			'--wps-taxonomy-hover-text': taxHoverText,
			'--wps-taxonomy-shadow': ext['wps_taxonomy_shadow_color'],
			'--wps-taxonomy-hover-shadow': ext['wps_taxonomy_hover_shadow_color'],
			'--wps-featured-card-border': ext['wps_featured_card_border_color'],
			'--wps-featured-card-shadow': ext['wps_featured_card_shadow_color'],
			'--wps-featured-card-hover': ext['wps_featured_card_hover_color'],
			'--wps-tag-card-border': ext['wps_tag_card_border_color'],
			'--wps-tag-card-shadow': ext['wps_tag_card_shadow_color'],
			'--wps-tag-card-hover': ext['wps_tag_card_hover_color'],
			'--wps-view-all-background': ext['wps_view_all_background_color'],
			'--wps-view-all-shadow': ext['wps_view_all_shadow_color'],
			'--wps-input-background': inputBg,
			'--wps-input-text': inputText,
			'--wps-input-border': ext['wps_input_border_color'],
			'--wps-input-focus-border': ext['wps_input_focus_border_color'],
			'--wps-input-placeholder': hexToRgbaLocal( inputPlaceholderHex, 0.62 ),
			'--wps-single-poster-bg': ext['wps_single_poster_bg_color'],
			'--wps-single-poster-border': ext['wps_single_poster_border_color'],
			'--wps-single-poster-shadow': ext['wps_single_poster_shadow_color'],
			'--wps-success': ext['wps_success_color'],
			'--wps-warning': ext['wps_warning_color'],
			'--wps-danger': ext['wps_danger_color'],
			'--wps-info': ext['wps_info_color'],
			'--wps-menu-active-background': menuActiveBg,
			'--wps-primary-color': main,
			'--wps-body-text-color': uiText,
			'--wps-icon-color': uiText
		};
		Object.keys( props ).forEach( function( k ) {
			document.documentElement.style.setProperty( k, props[ k ] );
		} );
	}

	// Recompute whenever any Theme Color setting changes.
	var allColorIds = Object.keys( defaults );
	allColorIds.forEach( function( id ) {
		var setting = wp.customize( id );
		if ( ! setting ) { return; }
		setting.bind( applyThemePreview );
	} );

	if ( wp.customize.state && wp.customize.state( 'ready' ) && wp.customize.state( 'ready' ).get() ) {
		applyThemePreview();
	} else {
		wp.customize.bind( 'ready', applyThemePreview );
	}

} )( jQuery );