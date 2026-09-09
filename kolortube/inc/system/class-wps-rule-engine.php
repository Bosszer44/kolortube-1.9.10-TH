<?php
/** Automatic media rules for newly saved video posts. */
defined( 'ABSPATH' ) || exit;

final class WPS_Rule_Engine {
	/** Register optional automatic rules. */
	public static function register() {
		if ( get_theme_mod( 'wps_auto_rules_enabled', false ) ) {
			add_action( 'save_post_post', array( __CLASS__, 'on_save_post' ), 20, 3 );
		}
	}

	/** Queue a unique cover match only when no legacy or framework cover exists. */
	public static function on_save_post( $post_id, $post, $update ) {
		unset( $update );
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}
		if ( class_exists( 'WPS_Bulk_Cover_Generator' ) && WPS_Bulk_Cover_Generator::resolve_cover( $post_id, false ) ) {
			return;
		}
		if ( ! class_exists( 'WPS_Task_Store' ) ) {
			return;
		}
		WPS_Task_Store::enqueue(
			'search_match',
			array( 'post_id' => absint( $post_id ) ),
			array( 'unique_key' => 'auto-cover-' . absint( $post_id ), 'priority' => 20 )
		);
	}
}

if ( ! function_exists( 'av_auto_rules' ) ) {
	function av_auto_rules( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			WPS_Rule_Engine::on_save_post( $post_id, $post, true );
		}
	}
}
