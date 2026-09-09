<?php
/** Video integration boundary. */
defined( 'ABSPATH' ) || exit;

final class WPS_Video {
	public static function register() {
		do_action( 'wps_video_module_loaded' );
	}
}
