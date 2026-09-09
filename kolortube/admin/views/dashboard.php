<?php
/** KolorTube single control center. */
defined( 'ABSPATH' ) || exit;

$litespeed_active    = ! empty( $litespeed['active'] );
$litespeed_primary   = ! empty( $litespeed['primary'] );
$litespeed_conflicts = ! empty( $litespeed['conflicts'] ) && is_array( $litespeed['conflicts'] ) ? $litespeed['conflicts'] : array();
$post_counts         = wp_count_posts( 'post' );
$published_posts     = isset( $post_counts->publish ) ? absint( $post_counts->publish ) : 0;
$media_counts        = wp_count_attachments();
$media_total         = is_object( $media_counts ) ? array_sum( array_map( 'absint', (array) $media_counts ) ) : 0;
global $wpdb;
$database_placeholder = 'SELECT ID, post_title FROM ' . $wpdb->posts . " WHERE post_type='post' LIMIT 50";
$edit_file           = isset( $_GET['wps_edit_file'] ) ? ltrim( wp_normalize_path( sanitize_text_field( wp_unslash( $_GET['wps_edit_file'] ) ) ), '/' ) : '';
$edit_content        = '';
$edit_error          = '';
if ( $edit_file && class_exists( 'WPS_File_Editor' ) ) {
	$read = WPS_File_Editor::read_file( $edit_file );
	if ( is_wp_error( $read ) ) {
		$edit_error = $read->get_error_message();
	} else {
		$edit_content = $read;
	}
}
$task_cards = array(
	array( 'cover_dry_run', 'visibility', __( 'Cover Dry Run', 'wpst' ), __( 'Preview assignable, fallback-only and missing covers without changing posts or metadata.', 'wpst' ), __( 'Queue Dry Run', 'wpst' ) ),
	array( 'bulk_cover', 'format-image', __( 'Bulk Cover Generator', 'wpst' ), __( 'Match covers by Post ID and slug while preserving existing featured images.', 'wpst' ), __( 'Queue Generator', 'wpst' ) ),
	array( 'missing_scan', 'search', __( 'Missing Cover Scanner', 'wpst' ), __( 'Build a persistent missing-cover index in bounded batches.', 'wpst' ), __( 'Queue Scan', 'wpst' ) ),
	array( 'auto_media', 'admin-media', __( 'Auto Media System', 'wpst' ), __( 'Match attachments to parent posts, numeric IDs and exact slugs.', 'wpst' ), __( 'Queue Media Scan', 'wpst' ) ),
	array( 'rebuild', 'update', __( 'Full Rebuild', 'wpst' ), __( 'Create a rollback point, then queue media matching, covers and a final scan.', 'wpst' ), __( 'Backup & Queue Rebuild', 'wpst' ) ),
	array( 'self_heal', 'admin-tools', __( 'Self Healing', 'wpst' ), __( 'Run a repair pass now without loading the entire site in one request.', 'wpst' ), __( 'Queue Healing', 'wpst' ) ),
	array( 'media_backup', 'backup', __( 'Media Backup', 'wpst' ), __( 'Snapshot featured images and legacy media mappings to protected storage.', 'wpst' ), __( 'Queue Backup', 'wpst' ) ),
	array( 'media_rollback', 'undo', __( 'Rollback Media', 'wpst' ), __( 'Restore media state from the latest completed protected snapshot.', 'wpst' ), __( 'Queue Rollback', 'wpst' ) ),
	array( 'rebuild_index', 'database', __( 'Search Index', 'wpst' ), __( 'Rebuild the compact title, code, slug, taxonomy and video index.', 'wpst' ), __( 'Rebuild Index', 'wpst' ) ),
	array( 'video_metadata', 'clock', __( 'Video Duration', 'wpst' ), __( 'Read duration from legacy metadata, API cache and local media metadata.', 'wpst' ), __( 'Scan Durations', 'wpst' ) ),
	array( 'player_api_scan', 'controls-play', __( 'Player API Sync', 'wpst' ), __( 'Refresh the optional compatibility cache in tiny batches; legacy player remains fallback.', 'wpst' ), __( 'Sync All in Queue', 'wpst' ) ),
	array( 'webp_convert', 'images-alt2', __( 'WebP Converter', 'wpst' ), __( 'Create linked WebP derivatives while retaining original JPEG and PNG files.', 'wpst' ), __( 'Convert Images', 'wpst' ) ),
	array( 'cleanup_scan', 'search', __( 'Cleanup Dry Scan', 'wpst' ), __( 'Count records covered by conservative cleanup rules without deleting them.', 'wpst' ), __( 'Scan Database', 'wpst' ) ),
	array( 'cleanup_run', 'trash', __( 'Safe Cleanup', 'wpst' ), __( 'Remove only old trash, revisions, spam, expired transients and orphan metadata.', 'wpst' ), __( 'Run Safe Cleanup', 'wpst' ) ),
	array( 'duplicates_scan', 'admin-page', __( 'Duplicate Scan', 'wpst' ), __( 'Find shared video URLs and classify only matching title/content as exact duplicates.', 'wpst' ), __( 'Scan Duplicates', 'wpst' ) ),
	array( 'duplicates_trash', 'dismiss', __( 'Trash Exact Duplicates', 'wpst' ), __( 'Keep the oldest canonical post and move only exact matches to WordPress Trash.', 'wpst' ), __( 'Trash Exact Duplicates', 'wpst' ) ),
	array( 'full_backup', 'cloud-upload', __( 'Full Backup', 'wpst' ), __( 'Create a protected SQL dump, manifest and optional current-theme archive.', 'wpst' ), __( 'Queue Full Backup', 'wpst' ) ),
);
if ( defined( 'WPMB_FULL_BACKUP_READY' ) && WPMB_FULL_BACKUP_READY ) {
	$task_cards = array_values( array_filter( $task_cards, static function ( $card ) { return 'full_backup' !== ( $card[0] ?? '' ); } ) );
}
if ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) {
	$task_cards = array_values( array_filter( $task_cards, static function ( $card ) { return ! in_array( $card[0] ?? '', array( 'cleanup_scan', 'cleanup_run', 'health_check' ), true ); } ) );
}
$wpmb_repair_owner = defined( 'WPMB_REPAIR_ENGINE_READY' ) && WPMB_REPAIR_ENGINE_READY;
if ( $wpmb_repair_owner ) {
	$repair_types = array( 'cover_dry_run', 'bulk_cover', 'self_heal', 'missing_scan', 'auto_media', 'media_backup', 'media_rollback', 'rebuild', 'search_match', 'rebuild_index', 'duplicates_scan', 'duplicates_trash', 'video_metadata', 'player_api_scan', 'player_api_test', 'webp_convert', 'cache_clear', 'flush_rewrite' );
	$task_cards = array_values( array_filter( $task_cards, static function ( $card ) use ( $repair_types ) { return ! in_array( $card[0] ?? '', $repair_types, true ); } ) );
}
?>
<div class="wrap wps-dashboard" id="wps-control-center">
	<div class="wps-hero">
		<div><h1><?php esc_html_e( 'AV Control Center', 'wpst' ); ?></h1><p><?php esc_html_e( 'Complete version-3 systems merged with the LiteSpeed-first production framework.', 'wpst' ); ?></p></div>
		<div class="wps-hero-badges"><span><?php echo esc_html( 'Framework ' . $status['framework_version'] ); ?></span><span><?php echo esc_html( 'Theme ' . $status['theme_version'] ); ?></span><span><?php echo esc_html( $litespeed_primary ? __( 'LiteSpeed Primary', 'wpst' ) : __( 'Safe Fallback', 'wpst' ) ); ?></span></div>
	</div>

	<?php if ( $notice && WPS_Dashboard::notice_message( $notice ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( WPS_Dashboard::notice_message( $notice ) ); ?></p></div>
	<?php endif; ?>
	<div id="wps-live-notice" class="notice notice-info inline wps-hidden"><p></p></div>
	<?php if ( ! $litespeed_active ) : ?><div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'LiteSpeed Cache is not active.', 'wpst' ); ?></strong> <?php esc_html_e( 'Framework fallbacks remain safe, but LiteSpeed is the intended production owner.', 'wpst' ); ?></p></div><?php endif; ?>
	<?php if ( $litespeed_conflicts ) : ?><div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Overlapping optimization plugins:', 'wpst' ); ?></strong> <?php echo esc_html( implode( ', ', $litespeed_conflicts ) ); ?></p></div><?php endif; ?>

	<?php if ( $wpmb_repair_owner ) : ?>
	<section class="wps-panel"><h2>Repair / Media / Search Ownership</h2><p><strong>WP MY BOSS</strong> เป็นเจ้าของ Repair Center และคิวซ่อมหลัง Import เพื่อไม่ให้ Theme Queue ทำงานซ้ำ ข้อมูลและ Provider เดิมของ Theme ยังเก็บไว้เป็น Emergency Fallback เมื่อปิดปลั๊กอิน</p><p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpmb-repair-center' ) ); ?>">เปิด WP MY BOSS Repair Center</a></p></section>
	<?php endif; ?>

	<section class="wps-section">
		<div class="wps-section-heading"><div><h2><?php esc_html_e( 'Task Engine', 'wpst' ); ?></h2><p><?php esc_html_e( 'Resumable batches continue through WP-Cron and can be advanced manually.', 'wpst' ); ?></p></div><div class="wps-actions"><button class="button" id="wps-run-worker"><?php esc_html_e( 'Run Worker', 'wpst' ); ?></button><button class="button" id="wps-cancel-queue"><?php esc_html_e( 'Cancel Queue', 'wpst' ); ?></button></div></div>
		<div class="wps-queue-summary" id="wps-queue-summary" data-status='<?php echo esc_attr( wp_json_encode( $queue ) ); ?>'></div>
		<div class="wps-progress-shell"><div class="wps-progress-bar" id="wps-overall-progress" style="width:0%"></div></div>
		<div class="wps-task-list" id="wps-task-list"></div>
	</section>

	<section class="wps-panel wps-litespeed-panel">
		<div class="wps-section-heading"><div><h2><?php esc_html_e( 'LiteSpeed Cache Integration', 'wpst' ); ?></h2><p><?php esc_html_e( 'LiteSpeed owns cache, lazy loading, assets, images, CDN and database optimization; overlapping framework layers are suppressed.', 'wpst' ); ?></p></div><span class="wps-state <?php echo $litespeed_primary ? 'is-active' : 'is-warning'; ?>"><?php echo esc_html( $litespeed_primary ? __( 'Primary', 'wpst' ) : __( 'Fallback', 'wpst' ) ); ?></span></div>
		<div class="wps-status-grid wps-litespeed-grid">
			<div class="wps-stat"><span><?php esc_html_e( 'Plugin', 'wpst' ); ?></span><strong><?php echo esc_html( $litespeed_active ? __( 'Active', 'wpst' ) : __( 'Not detected', 'wpst' ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Version', 'wpst' ); ?></span><strong><?php echo esc_html( $litespeed['version'] ?? __( 'Unavailable', 'wpst' ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Server', 'wpst' ); ?></span><strong><?php echo esc_html( $litespeed['server'] ?? __( 'Unavailable', 'wpst' ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Object cache', 'wpst' ); ?></span><strong><?php echo ! empty( $litespeed['object_cache'] ) ? esc_html__( 'Active', 'wpst' ) : esc_html__( 'Inactive', 'wpst' ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'CDN', 'wpst' ); ?></span><strong><?php echo esc_html( $status['cdn_status'] ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Conflicts', 'wpst' ); ?></span><strong><?php echo esc_html( $litespeed_conflicts ? implode( ', ', $litespeed_conflicts ) : __( 'None', 'wpst' ) ); ?></strong></div>
		</div>
		<div class="wps-tool-grid wps-litespeed-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_apply_litespeed_mode"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><?php submit_button( __( 'Apply LiteSpeed Safe Mode', 'wpst' ), 'primary', 'submit', false ); ?></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_purge_litespeed"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><?php submit_button( __( 'Purge All LiteSpeed Cache', 'wpst' ), 'secondary', 'submit', false ); ?></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_purge_litespeed_object"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><?php submit_button( __( 'Purge Object Cache', 'wpst' ), 'secondary', 'submit', false ); ?></form>
		</div>
	</section>

	<section class="wps-card-grid">
		<?php foreach ( $task_cards as $card ) : ?>
			<article class="wps-card wps-feature-card"><span class="dashicons dashicons-<?php echo esc_attr( $card[1] ); ?>"></span><h2><?php echo esc_html( $card[2] ); ?></h2><p><?php echo esc_html( $card[3] ); ?></p><button class="button button-primary wps-task-button" data-task="<?php echo esc_attr( $card[0] ); ?>"><?php echo esc_html( $card[4] ); ?></button></article>
		<?php endforeach; ?>
		<?php if ( ! $wpmb_repair_owner ) : ?>
		<article class="wps-card wps-feature-card"><span class="dashicons dashicons-search"></span><h2><?php esc_html_e( 'Search & Match Cover', 'wpst' ); ?></h2><p><?php esc_html_e( 'Queue a precise cover match for one WordPress Post ID.', 'wpst' ); ?></p><div class="wps-inline-control"><input type="number" min="1" id="wps-search-post-id" placeholder="Post ID"><button class="button button-primary wps-task-button" data-task="search_match"><?php esc_html_e( 'Match', 'wpst' ); ?></button></div></article>
		<article class="wps-card wps-feature-card"><span class="dashicons dashicons-controls-play"></span><h2><?php esc_html_e( 'Test Player API', 'wpst' ); ?></h2><p><?php esc_html_e( 'Test one post before queueing a site-wide optional API synchronization.', 'wpst' ); ?></p><div class="wps-inline-control"><input type="number" min="1" id="wps-player-post-id" placeholder="Post ID"><button class="button button-primary wps-task-button" data-task="player_api_test"><?php esc_html_e( 'Test Post', 'wpst' ); ?></button></div></article>
		<?php endif; ?>
	</section>

	<section class="wps-two-column">
		<article class="wps-panel"><h2><?php esc_html_e( 'System Overview', 'wpst' ); ?></h2><div class="wps-status-grid">
			<?php foreach ( array(
				__( 'PHP Version', 'wpst' ) => $status['php_version'], __( 'WordPress Version', 'wpst' ) => $status['wordpress_version'], __( 'MySQL Version', 'wpst' ) => $status['mysql_version'], __( 'Theme Version', 'wpst' ) => $status['theme_version'], __( 'Framework Version', 'wpst' ) => $status['framework_version'], __( 'Memory Limit', 'wpst' ) => $status['memory_limit'], __( 'Memory Usage', 'wpst' ) => $status['memory_usage'], __( 'Upload Limit', 'wpst' ) => $status['upload_limit'], __( 'Cache Status', 'wpst' ) => $status['cache_status'], __( 'LiteSpeed', 'wpst' ) => $status['litespeed_status'], __( 'CDN Status', 'wpst' ) => $status['cdn_status'], __( 'SSL Status', 'wpst' ) => $status['ssl_status'], __( 'Cron Status', 'wpst' ) => $status['cron_status'], __( 'Debug Status', 'wpst' ) => $status['debug_status'], __( 'WP Debug', 'wpst' ) => $status['wp_debug'], __( 'WP-Script Core', 'wpst' ) => $status['wp_script_core'], __( 'Published Posts', 'wpst' ) => $status['published_posts'], __( 'Featured Posts', 'wpst' ) => $status['featured_posts'], __( 'Without Featured', 'wpst' ) => $status['without_featured'], __( 'Media Attachments', 'wpst' ) => $status['media_attachments'], __( 'Indexed Posts', 'wpst' ) => $status['indexed_posts'], __( 'Last Task', 'wpst' ) => $status['last_task'], __( 'Latest Backup', 'wpst' ) => $status['latest_backup'], __( 'Conflict Notices', 'wpst' ) => $status['conflict_notices'],
			) as $label => $value ) : ?><div class="wps-stat"><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $value ); ?></strong></div><?php endforeach; ?>
		</div></article>
		<article class="wps-panel"><h2><?php esc_html_e( 'Health Check', 'wpst' ); ?></h2><ul class="wps-health-list"><?php foreach ( $health as $test ) : ?><li class="<?php echo ! empty( $test['ok'] ) ? 'is-ok' : 'is-warning'; ?>"><span class="dashicons <?php echo ! empty( $test['ok'] ) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span><div><strong><?php echo esc_html( $test['label'] ); ?></strong><small><?php echo esc_html( $test['detail'] ); ?></small></div></li><?php endforeach; ?></ul><?php if ( defined( 'WPMB_MAINTENANCE_ENGINE_READY' ) && WPMB_MAINTENANCE_ENGINE_READY ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wpmb-health-check' ) ); ?>"><?php esc_html_e( 'Open WP MY BOSS Health Check', 'wpst' ); ?></a><?php else : ?><button class="button wps-task-button" data-task="health_check"><?php esc_html_e( 'Refresh in Background', 'wpst' ); ?></button><?php endif; ?></article>
	</section>

	<section class="wps-two-column">
		<article class="wps-panel"><h2><?php esc_html_e( 'Media & Index Reports', 'wpst' ); ?></h2><div class="wps-status-grid">
			<div class="wps-stat"><span><?php esc_html_e( 'Dry-run processed', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $cover_dry['processed'] ?? 0 ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Would assign', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $cover_dry['would_assign'] ?? 0 ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Fallback only', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $cover_dry['fallback_only'] ?? 0 ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Missing', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $queue['media']['missing'] ?? 0 ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Search failures', 'wpst' ); ?></span><strong><?php echo esc_html( count( $search_failures ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Exact duplicates', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $duplicate_report['exact_count'] ?? 0 ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'WebP converted', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $webp_report['converted'] ?? 0 ) ); ?></strong></div>
			<div class="wps-stat"><span><?php esc_html_e( 'Cleanup eligible', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $cleanup_report['total'] ?? 0 ) ); ?></strong></div>
		</div>
		<?php if ( $search_failures ) : ?><h3><?php esc_html_e( 'Index Failures', 'wpst' ); ?></h3><ul class="wps-compact-list"><?php foreach ( array_slice( $search_failures, 0, 10 ) as $failure ) : ?><li><a href="<?php echo esc_url( get_edit_post_link( absint( $failure['post_id'] ?? 0 ) ) ); ?>">#<?php echo esc_html( absint( $failure['post_id'] ?? 0 ) ); ?></a> — <?php echo esc_html( $failure['message'] ?? '' ); ?></li><?php endforeach; ?></ul><?php endif; ?>
		</article>
		<article class="wps-panel"><h2><?php esc_html_e( 'Missing Covers', 'wpst' ); ?></h2><?php if ( $missing ) : ?><ul class="wps-missing-list"><?php foreach ( $missing as $row ) : ?><li><a href="<?php echo esc_url( get_edit_post_link( $row['post_id'] ) ); ?>"><?php echo esc_html( '#' . $row['post_id'] . ' — ' . get_the_title( $row['post_id'] ) ); ?></a><small><?php echo esc_html( $row['checked_at'] ); ?></small></li><?php endforeach; ?></ul><?php else : ?><p><?php esc_html_e( 'No missing covers are currently indexed.', 'wpst' ); ?></p><?php endif; ?>
		<h3><?php esc_html_e( 'Latest Media Rollback Point', 'wpst' ); ?></h3><?php if ( $media_backup ) : ?><p><strong><?php echo esc_html( $media_backup['created_at'] ?? '' ); ?></strong><br><?php echo esc_html( sprintf( __( '%1$d records · %2$s', 'wpst' ), absint( $media_backup['records'] ?? 0 ), size_format( absint( $media_backup['size'] ?? 0 ) ) ) ); ?></p><?php else : ?><p><?php esc_html_e( 'No media snapshot has been created yet.', 'wpst' ); ?></p><?php endif; ?></article>
	</section>

	<section class="wps-two-column">
		<article class="wps-panel"><h2><?php esc_html_e( 'Search Index', 'wpst' ); ?></h2><div class="wps-inline-control"><input type="search" id="wps-index-query" placeholder="<?php esc_attr_e( 'Search title, code, actress, studio, slug…', 'wpst' ); ?>"><button class="button" id="wps-index-search"><?php esc_html_e( 'Search Index', 'wpst' ); ?></button></div><div id="wps-index-results" class="wps-result-box"></div></article>
		<article class="wps-panel"><h2><?php esc_html_e( 'Player & Security Status', 'wpst' ); ?></h2><div class="wps-status-grid"><div class="wps-stat"><span><?php esc_html_e( 'Player API', 'wpst' ); ?></span><strong><?php echo ! empty( $player_api['configured'] ) ? esc_html__( 'Enabled (cached)', 'wpst' ) : esc_html__( 'Disabled / legacy fallback', 'wpst' ); ?></strong></div><div class="wps-stat"><span><?php esc_html_e( 'API Circuit', 'wpst' ); ?></span><strong><?php echo ! empty( $player_api['circuit_open'] ) ? esc_html__( 'Temporarily open', 'wpst' ) : esc_html__( 'Closed', 'wpst' ); ?></strong></div><div class="wps-stat"><span><?php esc_html_e( 'Bot Shield', 'wpst' ); ?></span><strong><?php echo ! empty( $bot_status['enabled'] ) ? esc_html__( 'Enabled', 'wpst' ) : esc_html__( 'Disabled', 'wpst' ); ?></strong></div><div class="wps-stat"><span><?php esc_html_e( 'Fake Googlebot Block', 'wpst' ); ?></span><strong><?php echo ! empty( $bot_status['fake_googlebot_block'] ) ? esc_html__( 'Enabled', 'wpst' ) : esc_html__( 'Disabled', 'wpst' ); ?></strong></div></div><?php if ( ! empty( $player_api['last_error']['message'] ) ) : ?><p class="wps-warning-text"><?php echo esc_html( $player_api['last_error']['message'] ); ?></p><?php endif; ?></article>
	</section>

	<section class="wps-two-column">
		<article class="wps-panel"><h2><?php esc_html_e( 'Real-time Visitors', 'wpst' ); ?></h2><p><?php esc_html_e( 'First-party estimates from visitors active during the last five minutes. No raw IP address is stored.', 'wpst' ); ?></p><div class="wps-status-grid"><div class="wps-stat"><span><?php esc_html_e( 'Total active', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $live['total'] ?? 0 ) ); ?></strong></div><div class="wps-stat"><span><?php esc_html_e( 'People', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $live['people'] ?? 0 ) ); ?></strong></div><div class="wps-stat"><span><?php esc_html_e( 'Bots', 'wpst' ); ?></span><strong><?php echo esc_html( absint( $live['bots'] ?? 0 ) ); ?></strong></div></div></article>
		<article class="wps-panel"><h2><?php esc_html_e( 'Recent Framework Events', 'wpst' ); ?></h2><?php if ( $events ) : ?><ul class="wps-event-list"><?php foreach ( $events as $event ) : ?><li class="event-<?php echo esc_attr( $event['level'] ); ?>"><strong><?php echo esc_html( strtoupper( $event['level'] ) . ' · ' . $event['code'] ); ?></strong><span><?php echo esc_html( $event['message'] ); ?></span><small><?php echo esc_html( $event['created_at'] ); ?></small></li><?php endforeach; ?></ul><?php else : ?><p><?php esc_html_e( 'No framework events recorded yet.', 'wpst' ); ?></p><?php endif; ?></article>
	</section>

	<section class="wps-two-column">
		<article class="wps-panel"><h2><?php esc_html_e( 'Selective Index / Noindex', 'wpst' ); ?></h2><?php if ( defined( 'WPMB_MAINTENANCE_OWNER' ) ) : ?><p><strong>WP MY BOSS</strong> เป็นเจ้าของ Taxonomy / Index เพียงตัวเดียว เพื่อไม่ให้ Robots ซ้ำกัน</p><p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpmb-taxonomy' ) ); ?>">เปิด Taxonomy / Index</a></p><?php else : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_save_taxonomy_robots"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><p><label><?php esc_html_e( 'Taxonomy', 'wpst' ); ?><select name="taxonomy"><?php foreach ( array( 'category', 'post_tag', 'actors', 'studio' ) as $taxonomy ) : ?><option value="<?php echo esc_attr( $taxonomy ); ?>"><?php echo esc_html( $taxonomy ); ?></option><?php endforeach; ?></select></label></p><p><label><?php esc_html_e( 'Rule', 'wpst' ); ?><select name="rule"><option value="inherit"><?php esc_html_e( 'Inherit', 'wpst' ); ?></option><option value="index"><?php esc_html_e( 'Index, follow', 'wpst' ); ?></option><option value="noindex"><?php esc_html_e( 'Noindex, follow', 'wpst' ); ?></option></select></label></p><p><label><?php esc_html_e( 'Minimum post count', 'wpst' ); ?><input type="number" name="min_count" min="1" value="1"></label></p><?php submit_button( __( 'Save Taxonomy Rule', 'wpst' ), 'secondary', 'submit', false ); ?></form><details><summary><?php esc_html_e( 'Current rules', 'wpst' ); ?></summary><pre><?php echo esc_html( wp_json_encode( $robots_rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre></details><?php endif; ?></article>
		<?php if ( defined( 'WPMB_FULL_BACKUP_READY' ) && WPMB_FULL_BACKUP_READY ) : ?><article class="wps-panel"><h2>Full Backup</h2><p><strong>WP MY BOSS</strong> เป็นเจ้าของ Full Backup และ Google Drive เพียงตัวเดียว เพื่อไม่ให้ Cron และไฟล์สำรองทำงานซ้ำกัน</p><p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpmb-backup' ) ); ?>">เปิด Full Backup</a></p></article><?php else : ?><article class="wps-panel"><h2><?php esc_html_e( 'Automatic Full Backup', 'wpst' ); ?></h2><?php if ( $backup_latest ) : ?><p><strong><?php echo esc_html( $backup_latest['completed_at'] ?? '' ); ?></strong><br><?php echo esc_html( size_format( absint( $backup_latest['size'] ?? 0 ) ) ); ?> · <?php echo esc_html( absint( $backup_latest['tables'] ?? 0 ) ); ?> <?php esc_html_e( 'tables', 'wpst' ); ?></p><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_save_backup_settings"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><label><input type="checkbox" name="backup_enabled" value="1" <?php checked( ! empty( $backup_settings['enabled'] ) ); ?>> <?php esc_html_e( 'Enable daily automatic backup', 'wpst' ); ?></label><br><label><input type="checkbox" name="include_theme" value="1" <?php checked( ! empty( $backup_settings['include_theme'] ) ); ?>> <?php esc_html_e( 'Include current theme', 'wpst' ); ?></label><p><label><?php esc_html_e( 'Retention', 'wpst' ); ?><input type="number" name="retention" min="1" max="30" value="<?php echo esc_attr( absint( $backup_settings['retention'] ?? 5 ) ); ?>"></label></p><label><input type="checkbox" name="drive_enabled" value="1" <?php checked( ! empty( $backup_settings['drive_enabled'] ) ); ?>> <?php esc_html_e( 'Upload to Google Drive', 'wpst' ); ?></label><p><input type="text" class="regular-text" name="drive_folder_id" placeholder="Drive folder ID" value="<?php echo esc_attr( $backup_settings['drive_folder_id'] ?? '' ); ?>"></p><p><textarea class="large-text" rows="4" name="service_account_json" placeholder="<?php esc_attr_e( 'Service account JSON — leave empty to keep saved credentials', 'wpst' ); ?>"></textarea></p><label><input type="checkbox" name="clear_drive_credentials" value="1"> <?php esc_html_e( 'Clear saved Drive credentials', 'wpst' ); ?></label><?php submit_button( __( 'Save Backup Settings', 'wpst' ), 'secondary', 'submit', false ); ?></form><p><small><?php echo esc_html( ! empty( $drive_status['configured'] ) ? __( 'Drive credentials configured.', 'wpst' ) : __( 'Local backup only.', 'wpst' ) ); ?></small></p></article><?php endif; ?>
	</section>

	<?php if ( get_theme_mod( 'wps_tools_enabled', true ) && ! defined( 'WPMB_MAINTENANCE_OWNER' ) ) : ?>
	<section class="wps-panel" id="wps-developer-tools"><h2><?php esc_html_e( 'Guarded Database Console', 'wpst' ); ?></h2><?php if ( $db_result ) : ?><div class="wps-result-box"><pre><?php echo esc_html( wp_json_encode( $db_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></div><?php endif; ?><div class="wps-two-column"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_database_search"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><label><strong><?php esc_html_e( 'Search database text', 'wpst' ); ?></strong><input class="large-text" type="text" name="database_search" required></label><?php submit_button( __( 'Search', 'wpst' ), 'secondary', 'submit', false ); ?></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_database_query"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><label><strong><?php esc_html_e( 'Single SQL statement', 'wpst' ); ?></strong><textarea class="large-text code" rows="5" name="sql_statement" placeholder="<?php echo esc_attr( $database_placeholder ); ?>" required></textarea></label><label><?php esc_html_e( 'Write confirmation', 'wpst' ); ?><input class="regular-text" name="write_confirmation" placeholder="WRITE DATABASE"></label><?php submit_button( __( 'Run Guarded Query', 'wpst' ), 'secondary', 'submit', false ); ?></form></div></section>

	<section class="wps-panel"><h2><?php esc_html_e( 'Protected Theme File Search & Editor', 'wpst' ); ?></h2><p><?php echo WPS_File_Editor::editing_available() ? esc_html__( 'Existing files use the WordPress fatal-error rollback API and are backed up first.', 'wpst' ) : esc_html__( 'File editing is disabled by WordPress configuration or user capability.', 'wpst' ); ?></p><?php if ( $file_result ) : ?><div class="wps-result-box"><pre><?php echo esc_html( wp_json_encode( $file_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></div><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_theme_file_search"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><div class="wps-inline-control"><input type="search" name="file_search" placeholder="<?php esc_attr_e( 'Search current-theme source files', 'wpst' ); ?>" required><button class="button"><?php esc_html_e( 'Search Files', 'wpst' ); ?></button></div></form><?php if ( 'search' === ( $file_result['type'] ?? '' ) ) : ?><ul class="wps-compact-list"><?php foreach ( $file_result['rows'] ?? array() as $row ) : ?><li><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'av-control-center', 'wps_edit_file' => $row['file'] ), admin_url( 'admin.php' ) ) . '#wps-developer-tools' ); ?>"><?php echo esc_html( $row['file'] ); ?></a><?php if ( $row['line'] ) : ?>:<?php echo esc_html( $row['line'] ); ?><?php endif; ?> — <?php echo esc_html( $row['preview'] ); ?></li><?php endforeach; ?></ul><?php endif; ?><?php if ( $edit_error ) : ?><p class="wps-warning-text"><?php echo esc_html( $edit_error ); ?></p><?php elseif ( $edit_file && WPS_File_Editor::editing_available() ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_theme_file_save"><input type="hidden" name="theme_file" value="<?php echo esc_attr( $edit_file ); ?>"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><h3><?php echo esc_html( $edit_file ); ?></h3><textarea class="large-text code wps-code-editor" rows="20" name="theme_file_content"><?php echo esc_textarea( $edit_content ); ?></textarea><?php submit_button( __( 'Back Up, Validate & Save', 'wpst' ), 'primary', 'submit', false ); ?></form><?php endif; ?>
	<?php if ( WPS_File_Editor::editing_available() && get_theme_mod( 'wps_developer_advanced_mode', false ) && defined( 'WPS_ALLOW_FILE_CREATION' ) && WPS_ALLOW_FILE_CREATION ) : ?>
	<details><summary><?php esc_html_e( 'Create a validated custom PHP module', 'wpst' ); ?></summary><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_custom_module_create"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><p><label><strong><?php esc_html_e( 'Module name', 'wpst' ); ?></strong><input class="regular-text" name="module_name" placeholder="my-custom-module" required></label></p><p><label><strong><?php esc_html_e( 'PHP module content', 'wpst' ); ?></strong><textarea class="large-text code wps-code-editor" rows="14" name="module_content" required>&lt;?php
/** Custom AV Framework module. */
defined( 'ABSPATH' ) || exit;
</textarea></label></p><p class="description"><?php esc_html_e( 'The file is created only under inc/custom after an isolated WordPress loopback validation and a recent full backup.', 'wpst' ); ?></p><?php submit_button( __( 'Validate & Create Module', 'wpst' ), 'secondary', 'submit', false ); ?></form></details>
	<?php elseif ( get_theme_mod( 'wps_developer_advanced_mode', false ) ) : ?><p class="description"><?php esc_html_e( 'New module creation remains locked unless WPS_ALLOW_FILE_CREATION is explicitly enabled in wp-config.php. Existing theme behavior is unchanged.', 'wpst' ); ?></p><?php endif; ?></section>
	<?php endif; ?>

	<section class="wps-panel wps-tools">
		<h2><?php esc_html_e( 'Cache, Rewrite, Logs, Backup & Settings', 'wpst' ); ?></h2>
		<div class="wps-tool-grid">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_clear_cache"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><?php submit_button( __( 'Purge LiteSpeed + Framework Cache', 'wpst' ), 'secondary', 'submit', false ); ?></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_flush_rewrite"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><?php submit_button( __( 'Flush Rewrite', 'wpst' ), 'secondary', 'submit', false ); ?></form>
			<?php if ( ! wps_uni_owns_import_export() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_export_settings"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><?php submit_button( __( 'Export Settings JSON', 'wpst' ), 'primary', 'submit', false ); ?></form>
			<?php endif; ?>
			<button class="button" id="wps-cleanup-queue"><?php esc_html_e( 'Clean Queue History', 'wpst' ); ?></button>
		</div>
		<?php if ( wps_uni_owns_import_export() ) : ?>
			<div class="notice notice-info inline"><p><strong>UNI-IM-PORT</strong> เป็นเจ้าของ Import / Export เพียงตัวเดียว เมนู Import / Export ของ Theme ถูกปิดเพื่อไม่ให้ระบบทำงานซ้ำกัน</p></div>
		<?php endif; ?>
		<div class="wps-import-reset-grid">
			<?php if ( ! wps_uni_owns_import_export() ) : ?>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_import_settings"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><label><strong><?php esc_html_e( 'Import Settings', 'wpst' ); ?></strong><input type="file" name="settings_file" accept="application/json,.json" required></label><?php submit_button( __( 'Import JSON', 'wpst' ), 'secondary', 'submit', false ); ?></form>
			<?php endif; ?>
			<form method="post" class="wps-reset-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_reset_settings"><?php wp_nonce_field( WPS_Tools::NONCE_ACTION, '_wps_nonce' ); ?><label><strong><?php esc_html_e( 'Reset Settings', 'wpst' ); ?></strong></label><label><input type="radio" name="reset_scope" value="framework" checked> <?php esc_html_e( 'Framework options only', 'wpst' ); ?></label><label><input type="radio" name="reset_scope" value="all"> <?php esc_html_e( 'Framework and legacy theme options', 'wpst' ); ?></label><label><input type="checkbox" name="confirm_reset" value="1" required> <?php esc_html_e( 'I understand the selected settings will be removed.', 'wpst' ); ?></label><?php submit_button( __( 'Reset Selected Options', 'wpst' ), 'delete', 'submit', false ); ?></form>
		</div>
	</section>
</div>
