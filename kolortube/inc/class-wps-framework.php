<?php
/**
 * KolorTube bootstrap and fault-tolerant module loader.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Framework {
	/** @var WPS_Framework|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	/** @var array<int,string> */
	private $load_errors = array();

	/** Singleton accessor. */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/** Boot framework and legacy compatibility modules. */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->load_framework_files();
		$this->prepare_wp_script_dependency();
		$this->load_legacy_theme_files();
		$this->register_modules();
		$this->load_custom_modules();

		if ( $this->load_errors ) {
			add_action( 'admin_notices', array( $this, 'render_load_error_notice' ) );
		}

		do_action( 'wps_loaded', $this );
	}

	/** Load framework classes in dependency order. */
	private function load_framework_files() {
		$files = array(
			'/inc/helpers/class-wps-query.php',
			'/inc/helpers/class-wps-media.php',
			'/inc/dashboard/class-wps-admin-ui.php',
			'/inc/cache/class-wps-litespeed.php',
			'/inc/cache/class-wps-cache-manager.php',
			'/inc/backup/class-wps-private-storage.php',
			'/inc/integrations/class-wps-secrets.php',
			'/inc/integrations/class-wps-google-drive.php',
			'/inc/tools/bulk-cover-generator.php',
			'/inc/helpers/smart-cover.php',
			'/inc/helpers/auto-upload.php',
			'/inc/system/class-wps-task-store.php',
			'/inc/system/class-wps-event-log.php',
			'/inc/search/class-wps-search-index.php',
			'/inc/media/class-wps-media-backup.php',
			'/inc/images/class-wps-webp-converter.php',
			'/inc/database/class-wps-cleanup.php',
			'/inc/database/class-wps-duplicates.php',
			'/inc/system/class-wps-task-runner.php',
			'/inc/system/class-wps-installer.php',
			'/inc/system/class-wps-system-status.php',
			'/inc/system/class-wps-health-check.php',
			'/inc/system/class-wps-rule-engine.php',
			'/inc/system/class-wps-self-healing.php',
			'/inc/redirect/class-wps-redirect.php',
			'/inc/layout/class-wps-layout.php',
			'/inc/player/class-wps-player.php',
			'/inc/video/class-wps-video-metadata.php',
			'/inc/video/class-wps-video.php',
			'/inc/performance/class-wps-performance.php',
			'/inc/security/class-wps-security.php',
			'/inc/security/class-wps-head-output-guard.php',
			'/inc/security/class-wps-bot-shield.php',
			'/inc/ads/class-wps-ads.php',
			'/inc/professional/class-wps-professional-suite.php',
			'/inc/professional/class-wps-home-sections.php',
			'/inc/seo/class-wps-seo.php',
			'/inc/seo/class-wps-taxonomy-robots.php',
			'/inc/analytics/class-wps-live-analytics.php',
			'/inc/helpers/compatibility.php',
			'/inc/customizer.php',
			'/inc/tools/class-wps-tools.php',
			'/inc/control-center-42/class-wps-control-center-42.php',
			'/inc/control-center-42/class-wps-post-labels.php',
			'/inc/control-center-42/class-wps-file-manager.php',
			'/inc/control-center-42/class-wps-code-media-matcher.php',
			'/inc/control-center-42/class-wps-selective-transfer.php',
			'/inc/control-center-42/class-wps-domain-proxy.php',
			'/inc/final/class-wps-theme-finalizer.php',
			'/inc/final/class-wps-taxonomy-cleaner.php',
		);

		foreach ( $files as $file ) {
			$this->require_framework_file( $file );
		}
	}

	/** Retain the original WP-Script Core installer/activation flow. */
	private function prepare_wp_script_dependency() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			$plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( file_exists( $plugin_file ) ) {
				require_once $plugin_file;
			}
		}

		$plugin = 'wp-script-core/wp-script-core.php';
		$active = function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin );
		if ( ! $active && is_multisite() && function_exists( 'is_plugin_active_for_network' ) ) {
			$active = is_plugin_active_for_network( $plugin );
		}
		if ( ! $active ) {
			$this->require_if_exists( '/tgmpa/class-tgm-plugin-activation.php' );
			$this->require_if_exists( '/tgmpa/config.php' );
		}
	}

	/** Load original theme modules without changing their public APIs. */
	private function load_legacy_theme_files() {
		$includes = array(
			'/inc/post-like.php',
			'/inc/video-functions.php',
			'/inc/actors.php',
			'/inc/studio.php',
			'/inc/class-wp-bootstrap-navwalker.php',
			'/inc/template-tags.php',
			'/inc/category-image.php',
			'/inc/actor-image.php',
			'/inc/pagination.php',
			'/inc/theme-settings.php',
			'/inc/setup.php',
			'/inc/widgets.php',
			'/inc/widget-video-filters.php',
			'/inc/widget-video-cats.php',
			'/inc/widget-video-tags.php',
			'/inc/hooks.php',
			'/inc/extras.php',
			'/inc/custom-comments.php',
			'/inc/jetpack.php',
			'/inc/woocommerce.php',
			'/inc/editor.php',
		);

		foreach ( $includes as $file ) {
			$this->require_if_exists( $file );
		}

		// CSS/JS must load even when WP-Script Core is absent or disconnected.
		$this->require_if_exists( '/inc/enqueue.php' );

		// AJAX endpoints required by the frontend must be available even when
		// WP-Script Core is not connected. require_once keeps this safe if Core
		// already loaded the same module.
		foreach ( array( '/ajax/post-like.php', '/ajax/post-views.php' ) as $file ) {
			$this->require_if_exists( $file );
		}

		if ( function_exists( 'WPSCORE' ) ) {
			foreach ( array( '/ajax/get-async-post-data.php', '/ajax/load-video-preview.php' ) as $file ) {
				$this->require_if_exists( $file );
			}
			foreach ( array( '/admin/options.php', '/admin/metabox.php' ) as $file ) {
				$this->require_if_exists( $file );
			}
		}
	}

	/** Register available framework modules without making a partial upload fatal. */
	private function register_modules() {
		// BOSSMASTER Option A (native-theme mode): external admin-tool modules are
		// disconnected at the registration level. Their class files remain
		// loadable for backward-compatible static calls, but no hooks are ever
		// registered, so they are completely inert on both admin and frontend.
		// Disconnected: CDN rewriter, Developer diagnostics, Backup, Full Backup,
		// Database Console, File Editor, Dashboard (AV Control Center).
		$modules = array(
			'WPS_Installer',
			'WPS_Event_Log',
			'WPS_LiteSpeed',
			'WPS_Cache_Manager',
			'WPS_Performance',
			'WPS_Security',
			'WPS_Head_Output_Guard',
			'WPS_Bot_Shield',
			// Option A: legacy CDN rewriter disconnected (LiteSpeed/QUIC.cloud stays authoritative).
			// 'WPS_CDN',
			'WPS_Ads',
			'WPS_Professional_Suite',
			'WPS_Home_Sections',
			'WPS_SEO',
			'WPS_Taxonomy_Robots',
			'WPS_Smart_Cover',
			'WPS_Auto_Upload',
			'WPS_Search_Index',
			'WPS_Live_Analytics',
			'WPS_Task_Runner',
			'WPS_Rule_Engine',
			'WPS_Self_Healing',
			// Option A: developer diagnostics disconnected.
			// 'WPS_Developer',
			// Option A: backup tools disconnected.
			// 'WPS_Backup',
			// 'WPS_Full_Backup',
			// Option A: database console disconnected.
			// 'WPS_Database_Console',
			// Option A: theme file editor disconnected.
			// 'WPS_File_Editor',
			'WPS_Redirect',
			'WPS_Layout',
			'WPS_Player',
			'WPS_Video',
			'WPS_Tools',
			// Option A: AV Control Center dashboard page disconnected.
			// 'WPS_Dashboard',
			'WPS_Control_Center_42',
			'WPS_Theme_Finalizer',
			'WPS_Taxonomy_Cleaner',
		);

		foreach ( $modules as $module ) {
			if ( is_callable( array( $module, 'register' ) ) ) {
				call_user_func( array( $module, 'register' ) );
			}
		}
	}


	/** Load validated optional modules from inc/custom without making one bad file fatal. */
	private function load_custom_modules() {
		$directory = WPS_PATH . '/inc/custom';
		if ( ! is_dir( $directory ) ) {
			return;
		}
		$files = glob( $directory . '/*.php', GLOB_NOSORT );
		foreach ( is_array( $files ) ? $files : array() as $path ) {
			try {
				require_once $path;
			} catch ( Throwable $error ) {
				$this->load_errors[] = '/inc/custom/' . basename( $path ) . ': ' . $error->getMessage();
			}
		}
	}

	/** Require a framework file and record a recoverable package error. */
	private function require_framework_file( $relative_path ) {
		$path = WPS_PATH . $relative_path;
		if ( ! is_readable( $path ) ) {
			$this->load_errors[] = $relative_path;
			return false;
		}
		require_once $path;
		return true;
	}

	/** Require an original theme file when present. */
	private function require_if_exists( $relative_path ) {
		$path = WPS_PATH . $relative_path;
		if ( is_readable( $path ) ) {
			require_once $path;
			return true;
		}
		return false;
	}

	/** Display missing modular files to administrators only. */
	public function render_load_error_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'KolorTube package is incomplete.', 'wpst' ),
			esc_html( implode( ', ', $this->load_errors ) )
		);
	}
}

