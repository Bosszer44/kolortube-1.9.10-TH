<?php
/** Redirect module boundary for legacy and framework redirects. */
defined( 'ABSPATH' ) || exit;

final class WPS_Redirect {
	public static function register() {
		do_action( 'wps_redirect_module_loaded' );
	}
}
