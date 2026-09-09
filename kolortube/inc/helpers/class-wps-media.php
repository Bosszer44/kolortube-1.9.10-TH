<?php
/** Video preview and remote media helpers. */
defined( 'ABSPATH' ) || exit;

final class WPS_Media {
	public static function get_video_preview( $post_id = null ) {
		if ( null === $post_id ) {
			$post_id = get_the_ID();
		}
		$post_id = absint( $post_id );
		$url     = esc_url_raw( (string) get_post_meta( $post_id, 'trailer_url', true ) );
		if ( '' === $url ) {
			return null;
		}
		$path   = (string) wp_parse_url( $url, PHP_URL_PATH );
		$format = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$format = $format ? $format : 'mp4';

		return sprintf(
			'<video width="100%%" height="100%%" autoplay loop muted playsinline preload="none"><source src="%1$s" type="video/%2$s">%3$s</video>',
			esc_url( $url ),
			esc_attr( $format ),
			esc_html__( 'Your browser does not support the video tag.', 'wpst' )
		);
	}

	public static function get_sources_from_hls( $hls_url ) {
		$hls_url = esc_url_raw( $hls_url );
		$parsed  = wp_parse_url( $hls_url );
		if ( ! is_array( $parsed ) || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return '';
		}
		$referer = $parsed['scheme'] . '://' . $parsed['host'];
		$body    = self::request( $hls_url, $referer );
		if ( ! is_string( $body ) || '' === $body ) {
			return '';
		}

		$base_url = trailingslashit( dirname( $hls_url ) );
		preg_match_all( '/RESOLUTION=\d+x(\d+).*?[\r\n]+([^\r\n]+\.m3u8[^\r\n]*)/i', $body, $matches, PREG_SET_ORDER );
		$resolutions = array();
		foreach ( $matches as $match ) {
			$height = absint( $match[1] );
			$file   = trim( $match[2] );
			$url    = preg_match( '#^https?://#i', $file ) ? $file : $base_url . ltrim( $file, '/' );
			$resolutions[ $height ] = esc_url_raw( $url );
		}
		krsort( $resolutions, SORT_NUMERIC );

		$output = array();
		foreach ( $resolutions as $height => $url ) {
			$label    = $height > 3000 ? '4K' : $height . 'p';
			$output[] = sprintf( '<source src="%1$s" label="%2$s" type="application/x-mpegURL"/>', esc_url( $url ), esc_attr( $label ) );
		}
		return implode( '', $output );
	}

	public static function request( $url, $referer, $type = null ) {
		$user_agent = null !== $type
			? 'Mozilla/5.0 (Linux; Android 4.0; Mobile) AppleWebKit/537.36'
			: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout'     => 10,
				'redirection' => 5,
				'sslverify'   => (bool) apply_filters( 'wps_remote_sslverify', true, $url ),
				'user-agent'  => $user_agent,
				'headers'     => array( 'Referer' => esc_url_raw( $referer ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		return wp_remote_retrieve_body( $response );
	}
}
