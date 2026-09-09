<?php
/** Query and template helpers. */
defined( 'ABSPATH' ) || exit;

final class WPS_Query {
	public static function posts_filter( $query ) {
		if ( is_admin() || ! $query->is_main_query() || $query->is_single() ) {
			return $query;
		}

		$filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
		$query->set( 'posts_per_page', 36 );

		switch ( $filter ) {
			case 'latest':
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'DESC' );
				break;
			case 'most-viewed':
				$query->set( 'meta_key', 'post_views_count' );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', 'DESC' );
				break;
			case 'longest':
				$query->set( 'meta_key', 'duration' );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', 'DESC' );
				break;
			case 'popular':
				$query->set( 'orderby', 'meta_value_num' );
				$query->set(
					'meta_query',
					array(
						'relation' => 'OR',
						array( 'key' => 'rate', 'compare' => 'NOT EXISTS' ),
						array( 'key' => 'rate', 'compare' => 'EXISTS' ),
					)
				);
				$query->set( 'order', 'DESC' );
				break;
			case 'random':
				$query->set( 'orderby', 'rand' );
				$query->set( 'order', 'DESC' );
				break;
		}

		$query->set( 'post_status', 'publish' );
		return $query;
	}

	public static function selected_filter( $filter ) {
		$current = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
		return $current === $filter ? 'active' : false;
	}

	public static function get_filter_title() {
		$filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
		if ( '' === $filter && function_exists( 'xbox_get_field_value' ) ) {
			$filter = sanitize_key( (string) xbox_get_field_value( 'wpst-options', 'show-videos-homepage' ) );
		}

		$titles = array(
			'latest'      => __( 'Latest videos', 'wpst' ),
			'most-viewed' => __( 'Most viewed videos', 'wpst' ),
			'longest'     => __( 'Longest videos', 'wpst' ),
			'popular'     => __( 'Popular videos', 'wpst' ),
			'random'      => __( 'Random videos', 'wpst' ),
		);

		return isset( $titles[ $filter ] ) ? $titles[ $filter ] : $titles['latest'];
	}

	public static function get_nopaging_url() {
		global $wp;
		$current_url = home_url( isset( $wp->request ) ? $wp->request : '' );
		$position    = strpos( $current_url, '/page' );
		return trailingslashit( false !== $position ? substr( $current_url, 0, $position ) : $current_url );
	}

	public static function duration_custom_field( $updated, $field ) {
		unset( $updated );
		$hours   = isset( $_POST['duration_hh'] ) ? absint( wp_unslash( $_POST['duration_hh'] ) ) : 0;
		$minutes = isset( $_POST['duration_mm'] ) ? absint( wp_unslash( $_POST['duration_mm'] ) ) : 0;
		$seconds = isset( $_POST['duration_ss'] ) ? absint( wp_unslash( $_POST['duration_ss'] ) ) : 0;
		$field->save( ( $hours * HOUR_IN_SECONDS ) + ( $minutes * MINUTE_IN_SECONDS ) + $seconds );
	}

	public static function render_shortcodes( $content ) {
		if ( false === strpos( $content, '[' ) ) {
			return $content;
		}
		$pattern = get_shortcode_regex();
		if ( preg_match( '/' . $pattern . '/s', $content, $match ) && ! empty( $match[2] ) && shortcode_exists( $match[2] ) ) {
			return do_shortcode( $match[0] );
		}
		return $content;
	}
}
