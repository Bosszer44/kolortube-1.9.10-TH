<?php
/** Scheduled self-healing cover pass. */
defined( 'ABSPATH' ) || exit;

final class WPS_Self_Healing {
	const CRON_HOOK = 'wps_daily_self_heal';

	/** Register the daily scheduler. */
	public static function register() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'enqueue' ) );
		add_action( 'init', array( __CLASS__, 'sync_schedule' ), 30 );
	}

	/** Enable or remove the schedule based on a theme setting. */
	public static function sync_schedule() {
		$enabled = get_theme_mod( 'wps_self_healing_enabled', false );
		$next    = wp_next_scheduled( self::CRON_HOOK );
		if ( $enabled && ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		} elseif ( ! $enabled && $next ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/** Add a deduplicated self-healing job. */
	public static function enqueue() {
		return WPS_Task_Store::enqueue( 'self_heal', array( 'cursor' => 0, 'batch_size' => 30 ), array( 'unique_key' => 'scheduled-self-heal', 'priority' => 5 ) );
	}
}

if ( ! function_exists( 'av_system_heal' ) ) {
	function av_system_heal() {
		return WPS_Self_Healing::enqueue();
	}
}
