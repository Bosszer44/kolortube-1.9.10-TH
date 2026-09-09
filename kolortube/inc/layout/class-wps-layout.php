<?php
/** Layout module boundary preserving legacy template APIs. */
defined( 'ABSPATH' ) || exit;

final class WPS_Layout {
	public static function register() {
		add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
	}
	public static function body_classes( $classes ) {
		$classes[] = 'av-framework-pro';
		return array_values( array_unique( $classes ) );
	}
}
