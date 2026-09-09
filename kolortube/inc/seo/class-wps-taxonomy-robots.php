<?php
/**
 * Selective taxonomy index/noindex rules with per-term overrides.
 *
 * @package WPS_Framework
 */

defined( 'ABSPATH' ) || exit;

final class WPS_Taxonomy_Robots {
	const OPTION = 'wps_taxonomy_robots';
	const META   = '_wps_robots_rule';

	/** Register archive filters, term fields and dashboard save endpoint. */
	public static function register() {
		if ( defined( 'WPMB_MAINTENANCE_OWNER' ) ) { return; }
		add_filter( 'wp_robots', array( __CLASS__, 'filter_wp_robots' ), 30 );
		add_filter( 'wpseo_robots_array', array( __CLASS__, 'filter_wpseo_robots_array' ), 30 );
		add_filter( 'wpseo_robots', array( __CLASS__, 'filter_wpseo_robots_string' ), 30 );
		add_action( 'admin_post_wps_save_taxonomy_robots', array( __CLASS__, 'save_dashboard_rules' ) );

		foreach ( self::taxonomies() as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'add_term_field' ) );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'edit_term_field' ), 10, 2 );
			add_action( 'created_' . $taxonomy, array( __CLASS__, 'save_term_field' ) );
			add_action( 'edited_' . $taxonomy, array( __CLASS__, 'save_term_field' ) );
		}
	}

	/** Apply the resolved term archive rule to core robots directives. */
	public static function filter_wp_robots( $robots ) {
		$rule = self::current_rule();
		if ( 'noindex' === $rule ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
			$robots['follow'] = true;
		} elseif ( 'index' === $rule ) {
			$robots['index']  = true;
			$robots['follow'] = true;
			unset( $robots['noindex'] );
		}
		return $robots;
	}

	/** Apply rules through Yoast's array filter when available. */
	public static function filter_wpseo_robots_array( $robots ) {
		if ( ! is_array( $robots ) ) {
			return $robots;
		}
		return self::filter_wp_robots( $robots );
	}

	/** Backward-compatible Yoast string filter. */
	public static function filter_wpseo_robots_string( $robots ) {
		$rule = self::current_rule();
		if ( 'noindex' === $rule ) {
			return 'noindex, follow';
		}
		if ( 'index' === $rule ) {
			return 'index, follow';
		}
		return $robots;
	}

	/** Resolve the archive rule for the queried term. */
	public static function current_rule() {
		if ( ! get_theme_mod( 'wps_taxonomy_robots_enabled', true ) ) {
			return 'inherit';
		}
		if ( ! is_category() && ! is_tag() && ! is_tax( array( 'actors', 'studio' ) ) ) {
			return 'inherit';
		}
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, self::taxonomies(), true ) ) {
			return 'inherit';
		}
		$override = sanitize_key( (string) get_term_meta( $term->term_id, self::META, true ) );
		if ( in_array( $override, array( 'index', 'noindex' ), true ) ) {
			return $override;
		}
		$rules = self::rules();
		$rule  = isset( $rules[ $term->taxonomy ] ) ? $rules[ $term->taxonomy ] : array( 'rule' => 'inherit', 'min_count' => 1 );
		if ( absint( $term->count ) < max( 1, absint( $rule['min_count'] ) ) ) {
			return 'noindex';
		}
		return in_array( $rule['rule'], array( 'index', 'noindex' ), true ) ? $rule['rule'] : 'inherit';
	}

	/** Return sanitized defaults and saved taxonomy rules. */
	public static function rules() {
		$saved = get_option( self::OPTION, array() );
		$out   = array();
		foreach ( self::taxonomies() as $taxonomy ) {
			$row = isset( $saved[ $taxonomy ] ) && is_array( $saved[ $taxonomy ] ) ? $saved[ $taxonomy ] : array();
			$rule = sanitize_key( $row['rule'] ?? 'inherit' );
			$out[ $taxonomy ] = array(
				'rule'      => in_array( $rule, array( 'inherit', 'index', 'noindex' ), true ) ? $rule : 'inherit',
				'min_count' => max( 1, absint( $row['min_count'] ?? 1 ) ),
			);
		}
		return $out;
	}

	/** Save dashboard-wide rules. */
	public static function save_dashboard_rules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage robots rules.', 'wpst' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( WPS_Tools::NONCE_ACTION, '_wps_nonce' );
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$rule     = isset( $_POST['rule'] ) ? sanitize_key( wp_unslash( $_POST['rule'] ) ) : 'inherit';
		$min      = isset( $_POST['min_count'] ) ? max( 1, absint( wp_unslash( $_POST['min_count'] ) ) ) : 1;
		if ( ! in_array( $taxonomy, self::taxonomies(), true ) || ! in_array( $rule, array( 'inherit', 'index', 'noindex' ), true ) ) {
			wp_die( esc_html__( 'Invalid taxonomy robots rule.', 'wpst' ), '', array( 'response' => 400 ) );
		}
		$rules = self::rules();
		$rules[ $taxonomy ] = array( 'rule' => $rule, 'min_count' => $min );
		update_option( self::OPTION, $rules, false );
		self::redirect_notice( 'taxonomy_robots_saved' );
	}

	/** Add the per-term override field. */
	public static function add_term_field() {
		wp_nonce_field( 'wps_term_robots', '_wps_term_robots_nonce' );
		?>
		<div class="form-field term-wps-robots-wrap">
			<label for="wps-robots-rule"><?php esc_html_e( 'AV Framework robots rule', 'wpst' ); ?></label>
			<select name="wps_robots_rule" id="wps-robots-rule">
				<option value="inherit"><?php esc_html_e( 'Use taxonomy default', 'wpst' ); ?></option>
				<option value="index"><?php esc_html_e( 'Index, follow', 'wpst' ); ?></option>
				<option value="noindex"><?php esc_html_e( 'Noindex, follow', 'wpst' ); ?></option>
			</select>
		</div>
		<?php
	}

	/** Edit the per-term override field. */
	public static function edit_term_field( $term ) {
		$value = sanitize_key( (string) get_term_meta( $term->term_id, self::META, true ) );
		wp_nonce_field( 'wps_term_robots', '_wps_term_robots_nonce' );
		?>
		<tr class="form-field term-wps-robots-wrap">
			<th scope="row"><label for="wps-robots-rule"><?php esc_html_e( 'AV Framework robots rule', 'wpst' ); ?></label></th>
			<td><select name="wps_robots_rule" id="wps-robots-rule">
				<option value="inherit" <?php selected( $value, 'inherit' ); ?>><?php esc_html_e( 'Use taxonomy default', 'wpst' ); ?></option>
				<option value="index" <?php selected( $value, 'index' ); ?>><?php esc_html_e( 'Index, follow', 'wpst' ); ?></option>
				<option value="noindex" <?php selected( $value, 'noindex' ); ?>><?php esc_html_e( 'Noindex, follow', 'wpst' ); ?></option>
			</select></td>
		</tr>
		<?php
	}

	/** Save a per-term override after nonce and capability checks. */
	public static function save_term_field( $term_id ) {
		if ( empty( $_POST['_wps_term_robots_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wps_term_robots_nonce'] ) ), 'wps_term_robots' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$value = isset( $_POST['wps_robots_rule'] ) ? sanitize_key( wp_unslash( $_POST['wps_robots_rule'] ) ) : 'inherit';
		if ( ! in_array( $value, array( 'index', 'noindex' ), true ) ) {
			delete_term_meta( $term_id, self::META );
			return;
		}
		update_term_meta( $term_id, self::META, $value );
	}

	/** Supported taxonomies. */
	private static function taxonomies() {
		return array( 'category', 'post_tag', 'actors', 'studio' );
	}

	/** Redirect to the dashboard with a standard notice. */
	private static function redirect_notice( $code ) {
		set_transient( 'wps_notice_' . get_current_user_id(), sanitize_key( $code ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=av-control-center' ) );
		exit;
	}
}
