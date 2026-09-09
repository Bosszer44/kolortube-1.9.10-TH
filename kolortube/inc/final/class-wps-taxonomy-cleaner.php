<?php
/** Taxonomy duplicate/unused/wrong-type scanner and safe cleaner. */
defined( 'ABSPATH' ) || exit;

final class WPS_Taxonomy_Cleaner {
	const REPORT_OPTION = 'wps_taxonomy_cleaner_report';
	private static $taxonomies = array( 'category', 'post_tag', 'actors', 'studio' );

	public static function register() {
		if ( defined( 'WPMB_MAINTENANCE_OWNER' ) ) { return; }
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 31 );
		add_action( 'admin_post_wps_taxonomy_cleaner_scan', array( __CLASS__, 'scan_action' ) );
		add_action( 'admin_post_wps_taxonomy_cleaner_fix', array( __CLASS__, 'fix_action' ) );
	}

	public static function admin_menu() {
		add_submenu_page( ( defined( 'WPMB_WEBSITE_MANAGER_READY' ) ? 'wpmb-dashboard' : 'av-control-center' ), 'Taxonomy Cleaner', 'Taxonomy Cleaner', 'manage_options', 'wps-taxonomy-cleaner', array( __CLASS__, 'render' ) );
	}

	public static function scan_action() {
		self::require_admin();
		check_admin_referer( 'wps_taxonomy_cleaner_scan', '_wps_nonce' );
		$report = self::scan();
		update_option( self::REPORT_OPTION, $report, false );
		self::redirect( 'scan_done' );
	}

	public static function fix_action() {
		self::require_admin();
		check_admin_referer( 'wps_taxonomy_cleaner_fix', '_wps_nonce' );
		$mode = isset( $_POST['fix_mode'] ) ? sanitize_key( wp_unslash( $_POST['fix_mode'] ) ) : 'exact_duplicates';
		$report = self::scan();
		$changes = array();
		if ( 'exact_duplicates' === $mode || 'all_safe' === $mode ) {
			$changes['merged'] = self::merge_exact_duplicates( $report['duplicates_exact'] );
		}
		if ( 'unused' === $mode || 'all_safe' === $mode ) {
			$changes['deleted_unused'] = self::delete_unused_terms( $report['unused'] );
		}
		$report = self::scan();
		$report['last_changes'] = $changes;
		update_option( self::REPORT_OPTION, $report, false );
		self::redirect( 'fix_done' );
	}

	public static function scan() {
		$report = array(
			'created_at' => current_time( 'mysql' ),
			'duplicates_exact' => array(),
			'duplicates_suspect' => array(),
			'unused' => array(),
			'wrong_type' => array(),
			'summary' => array(),
		);
		foreach ( self::$taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			$report['summary'][ $taxonomy ] = count( $terms );
			$groups = array();
			foreach ( $terms as $term ) {
				$key = self::normalize( $term->name );
				$groups[ $key ][] = $term;
				if ( 0 === absint( $term->count ) ) {
					$report['unused'][] = self::term_row( $term, $taxonomy );
				}
				if ( 'actors' === $taxonomy && self::actor_is_wrong_type( $term->name ) ) {
					$report['wrong_type'][] = self::term_row( $term, $taxonomy, 'actor_not_person_name' );
				}
				if ( 'studio' === $taxonomy && self::studio_is_suspect( $term->name ) ) {
					$report['wrong_type'][] = self::term_row( $term, $taxonomy, 'studio_generic_or_tag' );
				}
			}
			foreach ( $groups as $key => $set ) {
				if ( count( $set ) > 1 ) {
					usort( $set, function( $a, $b ) { return absint( $b->count ) <=> absint( $a->count ); } );
					$report['duplicates_exact'][] = array(
						'taxonomy' => $taxonomy,
						'normalized' => $key,
						'keep' => self::term_row( $set[0], $taxonomy ),
						'duplicates' => array_map( function( $t ) use ( $taxonomy ) { return self::term_row( $t, $taxonomy ); }, array_slice( $set, 1 ) ),
					);
				}
			}
			if ( 'studio' === $taxonomy ) {
				$report['duplicates_suspect'] = array_merge( $report['duplicates_suspect'], self::studio_suspects( $terms ) );
			}
		}
		return $report;
	}

	private static function merge_exact_duplicates( array $groups ) {
		$done = array();
		foreach ( $groups as $group ) {
			$taxonomy = $group['taxonomy'];
			$keep_id = absint( $group['keep']['term_id'] ?? 0 );
			foreach ( $group['duplicates'] as $dupe ) {
				$source_id = absint( $dupe['term_id'] ?? 0 );
				if ( $keep_id && $source_id && $keep_id !== $source_id ) {
					$result = self::merge_term( $taxonomy, $source_id, $keep_id );
					$done[] = array( 'taxonomy' => $taxonomy, 'source' => $source_id, 'target' => $keep_id, 'result' => is_wp_error( $result ) ? $result->get_error_message() : 'merged' );
				}
			}
		}
		return $done;
	}

	private static function delete_unused_terms( array $terms ) {
		$done = array();
		foreach ( $terms as $term ) {
			$taxonomy = $term['taxonomy'];
			$term_id = absint( $term['term_id'] );
			if ( ! $term_id || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$current = get_term( $term_id, $taxonomy );
			if ( ! $current || is_wp_error( $current ) || absint( $current->count ) > 0 ) {
				continue;
			}
			$result = wp_delete_term( $term_id, $taxonomy );
			$done[] = array( 'taxonomy' => $taxonomy, 'term_id' => $term_id, 'result' => is_wp_error( $result ) ? $result->get_error_message() : ( $result ? 'deleted' : 'skipped' ) );
		}
		return $done;
	}

	private static function merge_term( $taxonomy, $source_id, $target_id ) {
		$objects = get_objects_in_term( $source_id, $taxonomy );
		if ( is_wp_error( $objects ) ) {
			return $objects;
		}
		foreach ( (array) $objects as $object_id ) {
			$current = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $current ) ) {
				continue;
			}
			$current = array_map( 'absint', (array) $current );
			$current = array_values( array_diff( $current, array( absint( $source_id ) ) ) );
			$current[] = absint( $target_id );
			wp_set_object_terms( $object_id, array_values( array_unique( $current ) ), $taxonomy, false );
		}
		self::merge_term_meta( $source_id, $target_id );
		return wp_delete_term( $source_id, $taxonomy );
	}

	private static function merge_term_meta( $source_id, $target_id ) {
		$meta = get_term_meta( $source_id );
		foreach ( (array) $meta as $key => $values ) {
			if ( '' !== get_term_meta( $target_id, $key, true ) ) {
				continue;
			}
			if ( isset( $values[0] ) ) {
				update_term_meta( $target_id, $key, maybe_unserialize( $values[0] ) );
			}
		}
	}

	private static function term_row( $term, $taxonomy, $reason = '' ) {
		return array( 'taxonomy' => $taxonomy, 'term_id' => absint( $term->term_id ), 'name' => $term->name, 'slug' => $term->slug, 'count' => absint( $term->count ), 'reason' => $reason );
	}

	private static function normalize( $value ) {
		$value = function_exists( 'normalizer_normalize' ) ? normalizer_normalize( $value, Normalizer::FORM_KC ) : $value;
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $value ), 'UTF-8' ) : strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[\s\-_.!★]+/u', '', $value );
		$value = str_replace( array( 'ญี่ปุ่น ', ' ญี่ปุ่น' ), 'ญี่ปุ่น', $value );
		return $value;
	}

	private static function actor_is_wrong_type( $name ) {
		$normalized = self::normalize( $name );
		$bad = array( 'jav', 'javhd', 'avjapan', 'avล่าสุด', 'หนังav', 'หนังเอวี', 'ความลับ', 'ความสัมพันธ์', 'ความคิดถึง', 'ดูหนังav', 'ดูหนังเอวี', 'avonline', 'javonline' );
		if ( in_array( $normalized, $bad, true ) ) {
			return true;
		}
		if ( preg_match( '/(av|jav|หนัง|ความ|clip|คลิป|online|hd)$/iu', $name ) && ! preg_match( '/^[A-Z][a-z]+\s+[A-Z][a-z]+$/', $name ) ) {
			return true;
		}
		return false;
	}

	private static function studio_is_suspect( $name ) {
		return self::actor_is_wrong_type( $name );
	}

	private static function studio_suspects( array $terms ) {
		$map = array();
		foreach ( $terms as $term ) {
			$key = preg_replace( '/[^a-z0-9]+/i', '', strtolower( $term->name ) );
			$map[ $key ][] = $term;
		}
		$out = array();
		foreach ( $map as $key => $set ) {
			if ( count( $set ) > 1 ) {
				$out[] = array( 'taxonomy' => 'studio', 'normalized' => $key, 'terms' => array_map( function( $t ) { return self::term_row( $t, 'studio', 'similar_studio_name' ); }, $set ) );
			}
		}
		return $out;
	}

	public static function render() {
		self::require_admin();
		$report = get_option( self::REPORT_OPTION, array() );
		$notice = isset( $_GET['wps_notice'] ) ? sanitize_key( wp_unslash( $_GET['wps_notice'] ) ) : '';
		?>
		<div class="wrap wps-finalizer-wrap">
			<h1>Taxonomy Cleaner</h1>
			<?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
			<div class="wps-finalizer-grid">
				<section class="wps-finalizer-card"><h2>สแกนหมวด / แท็ก / นักแสดง / ค่าย</h2><p>Dry Run ก่อนเสมอ ตรวจซ้ำ, ไม่ได้ใช้งาน, และคำที่อยู่ผิด taxonomy</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_taxonomy_cleaner_scan"><?php wp_nonce_field( 'wps_taxonomy_cleaner_scan', '_wps_nonce' ); ?><?php submit_button( 'สแกนทั้งหมด', 'primary', 'submit', false ); ?></form></section>
				<section class="wps-finalizer-card"><h2>แก้แบบปลอดภัย</h2><p>รวมเฉพาะซ้ำแน่นอน และลบเฉพาะ term count = 0 เท่านั้น</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wps_taxonomy_cleaner_fix"><?php wp_nonce_field( 'wps_taxonomy_cleaner_fix', '_wps_nonce' ); ?><select name="fix_mode"><option value="exact_duplicates">รวมซ้ำแน่นอน</option><option value="unused">ลบไม่ได้ใช้งาน</option><option value="all_safe">รวมซ้ำแน่นอน + ลบไม่ได้ใช้งาน</option></select> <?php submit_button( 'เริ่มแก้', 'secondary', 'submit', false ); ?></form></section>
			</div>
			<?php self::render_report( $report ); ?>
		</div>
		<?php
	}

	private static function render_report( $report ) {
		if ( empty( $report ) || ! is_array( $report ) ) {
			return;
		}
		echo '<h2>รายงานล่าสุด ' . esc_html( $report['created_at'] ?? '' ) . '</h2>';
		foreach ( array( 'duplicates_exact' => 'คำซ้ำแน่นอน', 'duplicates_suspect' => 'คำคล้ายกัน', 'unused' => 'ไม่ได้ใช้งาน', 'wrong_type' => 'อยู่ผิดประเภท' ) as $key => $title ) {
			$items = $report[ $key ] ?? array();
			echo '<h3>' . esc_html( $title ) . ' (' . esc_html( count( (array) $items ) ) . ')</h3>';
			if ( empty( $items ) ) {
				continue;
			}
			echo '<div class="wps-finalizer-report"><pre>' . esc_html( wp_json_encode( array_slice( $items, 0, 80 ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre></div>';
		}
		if ( ! empty( $report['last_changes'] ) ) {
			echo '<h3>การแก้ล่าสุด</h3><pre>' . esc_html( wp_json_encode( $report['last_changes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre>';
		}
	}

	private static function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage taxonomy tools.', 'wpst' ), '', array( 'response' => 403 ) );
		}
	}

	private static function redirect( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'wps-taxonomy-cleaner', 'wps_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
