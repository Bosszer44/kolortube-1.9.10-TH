<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WPS_Selective_Transfer {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if ( wps_uni_owns_import_export() ) { return; }
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
        add_action( 'admin_init', array( $this, 'handle' ) );
    }

    public function admin_menu() {
        add_submenu_page( 'av-control-center', 'Import / Export', 'Import / Export', 'manage_options', 'wps-selective-transfer', array( $this, 'page' ) );
    }

    private function groups_from_post() {
        $allowed = array( 'seo', 'css', 'labels', 'media', 'proxy', 'framework', 'ads', 'appearance', 'theme_all' );
        $groups = isset( $_POST['groups'] ) ? (array) $_POST['groups'] : array();
        return array_values( array_intersect( array_map( 'sanitize_key', $groups ), $allowed ) );
    }

    public function handle() {
        if ( empty( $_POST['avsora_transfer_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( 'avsora_transfer' );
        $action = sanitize_key( wp_unslash( $_POST['avsora_transfer_action'] ) );
        $groups = $this->groups_from_post();
        if ( ! $groups ) {
            wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-selective-transfer', array( 'transfer_error' => rawurlencode( 'กรุณาเลือกอย่างน้อย 1 ชุด' ) ) ) );
            exit;
        }
        if ( 'export' === $action ) {
            $payload = $this->build_export( $groups );
            $filename = 'av-framework-4.2-settings-' . implode( '-', $groups ) . '-' . gmdate( 'Ymd-His' ) . '.json';
            nocache_headers();
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            exit;
        }
        if ( 'import' === $action ) {
            if ( empty( $_FILES['import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
                $this->error_redirect( 'ไม่พบไฟล์นำเข้า' );
            }
            if ( (int) $_FILES['import_file']['size'] > 5 * MB_IN_BYTES ) {
                $this->error_redirect( 'ไฟล์นำเข้าใหญ่เกิน 5 MB' );
            }
            $raw = file_get_contents( $_FILES['import_file']['tmp_name'] );
            $payload = json_decode( $raw, true );
            if ( ! is_array( $payload ) ) {
                $this->error_redirect( 'ไฟล์ JSON ไม่ถูกต้อง' );
            }
            if ( 'av-control-center' !== ( $payload['format'] ?? '' ) ) {
                if ( 'AV Framework PRO' === ( $payload['framework'] ?? '' ) ) {
                    $payload = $this->normalize_av_framework_export( $payload );
                } else {
                    $this->error_redirect( 'รูปแบบไฟล์ไม่ใช่ AV Framework PRO 4.2 หรือ AV Framework PRO' );
                }
            }
            $source = ! empty( $_POST['replace_domain'] ) ? esc_url_raw( $payload['site_url'] ?? '' ) : '';
            $target = trailingslashit( esc_url_raw( WPS_Control_Center_42::get( 'site_url', home_url( '/' ) ) ) );
            $result = $this->apply_import( $payload, $groups, $source, $target );
            update_option( 'wps_42_last_import_report', $result, false );
            wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-selective-transfer', array( 'transfer_notice' => 'imported' ) ) );
            exit;
        }
    }

    private function error_redirect( $message ) {
        wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-selective-transfer', array( 'transfer_error' => rawurlencode( $message ) ) ) );
        exit;
    }

    private function normalize_av_framework_export( array $legacy ) {
        $mods = isset( $legacy['theme_mods'] ) && is_array( $legacy['theme_mods'] ) ? $legacy['theme_mods'] : array();
        $framework_options = array();
        foreach ( array( 'framework_options', 'options', 'wps_options' ) as $key ) {
            if ( ! empty( $legacy[ $key ] ) && is_array( $legacy[ $key ] ) ) {
                $framework_options = array_merge( $framework_options, $legacy[ $key ] );
            }
        }
        $appearance_keys = array( 'main_color', 'link_color', 'custom_logo', 'mobile_columns', 'sidebar_position', 'wpst_sidebar_position', 'copyright_content', 'nav_menu_locations' );
        return array(
            'format'      => 'av-control-center',
            'version'     => WPS_FRAMEWORK_VERSION,
            'source_type' => 'AV Framework PRO',
            'site_url'    => $legacy['site_url'] ?? '',
            'stylesheet'  => $legacy['theme_stylesheet'] ?? '',
            'groups'      => array( 'seo', 'framework', 'ads', 'appearance', 'theme_all' ),
            'data'        => array(
                'seo' => array(
                    'plugin'     => array(),
                    'theme_mods' => $this->by_prefix( $mods, array( 'seo_' ) ),
                    'actor_terms'=> array(),
                ),
                'framework' => array(
                    'theme_mods' => $this->by_prefix( $mods, array( 'wps_' ) ),
                    'options'    => $framework_options,
                ),
                'ads'        => $this->by_prefix( $mods, array( 'ads_' ) ),
                'appearance' => $this->pick( $mods, $appearance_keys ),
                'theme_all'  => $mods,
                'css'        => array( 'custom_css' => isset( $legacy['custom_css'] ) ? (string) $legacy['custom_css'] : '' ),
            ),
        );
    }

    private function build_export( array $groups ) {
        $data = array();
        $options = WPS_Control_Center_42::options();
        $theme_mods = (array) get_theme_mods();
        if ( in_array( 'seo', $groups, true ) ) {
            $data['seo'] = array(
                'plugin'     => array(),
                'theme_mods' => $this->by_prefix( $theme_mods, array( 'seo_' ) ),
                'actor_terms'=> array(),
            );
        }
        if ( in_array( 'css', $groups, true ) ) {
            $data['css'] = array( 'custom_css' => wp_get_custom_css() );
        }
        if ( in_array( 'labels', $groups, true ) ) {
            $data['labels'] = $this->by_prefix( $options, array( 'labels_' ) );
        }
        if ( in_array( 'media', $groups, true ) ) {
            $data['media'] = $this->by_prefix( $options, array( 'media_' ) );
        }
        if ( in_array( 'proxy', $groups, true ) ) {
            $data['proxy'] = $this->by_prefix( $options, array( 'proxy_' ) );
        }
        if ( in_array( 'framework', $groups, true ) ) {
            $data['framework'] = array(
                'theme_mods' => $this->by_prefix( $theme_mods, array( 'wps_' ) ),
                'options'    => $this->framework_options_export(),
            );
        }
        if ( in_array( 'ads', $groups, true ) ) {
            $data['ads'] = $this->by_prefix( $theme_mods, array( 'ads_' ) );
        }
        if ( in_array( 'appearance', $groups, true ) ) {
            $keys = array( 'main_color', 'link_color', 'custom_logo', 'mobile_columns', 'sidebar_position', 'wpst_sidebar_position', 'copyright_content', 'nav_menu_locations' );
            $data['appearance'] = $this->pick( $theme_mods, $keys );
        }
        if ( in_array( 'theme_all', $groups, true ) ) {
            $data['theme_all'] = $theme_mods;
        }
        return array(
            'format'       => 'av-control-center',
            'version'      => WPS_FRAMEWORK_VERSION,
            'exported_at'  => gmdate( 'c' ),
            'site_url'     => home_url( '/' ),
            'target_url'   => WPS_Control_Center_42::get( 'site_url', home_url( '/' ) ),
            'stylesheet'   => get_stylesheet(),
            'groups'       => $groups,
            'data'         => $data,
        );
    }

    private function framework_options_export() {
        global $wpdb;
        $names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wps\\_%' OR option_name LIKE 'av_framework\\_%' ORDER BY option_name ASC" );
        $out = array();
        foreach ( (array) $names as $name ) {
            if ( WPS_Control_Center_42::OPTION === $name || 0 === strpos( $name, '_transient_' ) || 0 === strpos( $name, '_site_transient_' ) ) { continue; }
            $out[ $name ] = get_option( $name );
        }
        return $out;
    }

    private function framework_options_import( array $options ) {
        foreach ( $options as $name => $value ) {
            if ( WPS_Control_Center_42::OPTION !== $name && preg_match( '/^(wps_|av_framework_)[a-zA-Z0-9_\\-]+$/', (string) $name ) ) {
                update_option( $name, $value, false );
            }
        }
    }

    private function pick( array $source, array $keys ) {
        return array_intersect_key( $source, array_flip( $keys ) );
    }

    private function by_prefix( array $source, array $prefixes ) {
        $out = array();
        foreach ( $source as $key => $value ) {
            foreach ( $prefixes as $prefix ) {
                if ( 0 === strpos( (string) $key, $prefix ) ) {
                    $out[ $key ] = $value;
                    break;
                }
            }
        }
        return $out;
    }

    private function actor_term_export() {
        return array();
    }

    private function replace_domain_recursive( $value, $source, $target ) {
        if ( ! $source || ! $target ) {
            return $value;
        }
        $host = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
        $plain_host = 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
        $source_variants = array();
        if ( $plain_host ) {
            foreach ( array( 'http://', 'https://' ) as $scheme ) {
                $source_variants[] = $scheme . $plain_host;
                $source_variants[] = $scheme . 'www.' . $plain_host;
            }
        } else {
            $source_variants[] = untrailingslashit( $source );
        }
        $source_variants = array_values( array_unique( array_filter( $source_variants ) ) );
        $target_base = untrailingslashit( $target );
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $item ) {
                $value[ $key ] = $this->replace_domain_recursive( $item, $source, $target );
            }
            return $value;
        }
        if ( is_string( $value ) ) {
            foreach ( $source_variants as $variant ) {
                if ( $variant ) { $value = str_replace( untrailingslashit( $variant ), $target_base, $value ); }
            }
        }
        return $value;
    }

    private function apply_import( array $payload, array $groups, $source, $target ) {
        $data = $payload['data'] ?? array();
        $result = array( 'time' => current_time( 'mysql' ), 'groups' => array(), 'source' => $source, 'target' => $target );
        $options = WPS_Control_Center_42::options();
        foreach ( $groups as $group ) {
            if ( ! isset( $data[ $group ] ) ) {
                $result['groups'][ $group ] = 'ไม่มีในไฟล์';
                continue;
            }
            $value = $this->replace_domain_recursive( $data[ $group ], $source, $target );
            if ( 'seo' === $group ) {
                if ( ! empty( $value['theme_mods'] ) ) { $this->set_theme_mods( $value['theme_mods'] ); }
            } elseif ( 'css' === $group ) {
                if ( isset( $value['custom_css'] ) ) { wp_update_custom_css_post( (string) $value['custom_css'] ); }
            } elseif ( in_array( $group, array( 'labels', 'media', 'proxy' ), true ) ) {
                if ( is_array( $value ) ) { $options = array_merge( $options, $value ); }
            } elseif ( 'framework' === $group ) {
                if ( ! empty( $value['theme_mods'] ) && is_array( $value['theme_mods'] ) ) { $this->set_theme_mods( $value['theme_mods'] ); }
                if ( ! empty( $value['options'] ) && is_array( $value['options'] ) ) { $this->framework_options_import( $value['options'] ); }
            } elseif ( in_array( $group, array( 'ads', 'appearance', 'theme_all' ), true ) ) {
                if ( is_array( $value ) ) { $this->set_theme_mods( $value ); }
            }
            $result['groups'][ $group ] = 'นำเข้าแล้ว';
        }
        $options['site_url'] = $target ?: ( $options['site_url'] ?? home_url( '/' ) );
        update_option( WPS_Control_Center_42::OPTION, wp_parse_args( $options, WPS_Control_Center_42::defaults() ), false );
        return $result;
    }

    private function set_theme_mods( array $mods ) {
        foreach ( $mods as $key => $value ) {
            if ( is_string( $key ) && preg_match( '/^[a-zA-Z0-9_\-]+$/', $key ) ) {
                set_theme_mod( $key, $value );
            }
        }
    }

    private function actor_term_import( array $items ) {
        return;
    }

    public function page() {
        $report = get_option( 'wps_42_last_import_report', array() );
        ?>
        <div class="wrap avsora-cc-wrap"><h1>Import / Export แบบเลือกชุด</h1>
            <?php if ( ! empty( $_GET['transfer_error'] ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( rawurldecode( wp_unslash( $_GET['transfer_error'] ) ) ); ?></p></div><?php endif; ?>
            <?php if ( ! empty( $_GET['transfer_notice'] ) ) : ?><div class="notice notice-success is-dismissible"><p>นำเข้าเรียบร้อย</p></div><?php endif; ?>
            <div class="avsora-card"><p><strong>รองรับ:</strong> JSON จากหน้านี้และ JSON เดิมของ AV Framework PRO<br><strong>ชุดด่วน:</strong> กด CSS only, SEO only หรือ All แล้วระบบจะติ๊กชุดให้ โดย Appearance แยกออกเพราะมีสี โลโก้ เมนู และเลย์เอาต์</p>
                <p><button type="button" class="button avsora-preset" data-preset="css">CSS only</button> <button type="button" class="button avsora-preset" data-preset="seo">SEO only</button> <button type="button" class="button avsora-preset" data-preset="all">All (รวม Appearance)</button> <button type="button" class="button avsora-preset" data-preset="safe">All แบบไม่แตะ Appearance</button></p>
            </div>
            <div class="avsora-grid">
                <div class="avsora-card"><h2>Export</h2><form method="post"><?php wp_nonce_field( 'avsora_transfer' ); ?><?php $this->group_checkboxes(); ?><p><button class="button button-primary" name="avsora_transfer_action" value="export">ดาวน์โหลด JSON</button></p></form></div>
                <div class="avsora-card"><h2>Import</h2><form method="post" enctype="multipart/form-data"><?php wp_nonce_field( 'avsora_transfer' ); ?><?php $this->group_checkboxes(); ?><p><input type="file" name="import_file" accept="application/json,.json" required></p><p><label><input type="checkbox" name="replace_domain" value="1" checked> เปลี่ยนโดเมนต้นทางในข้อความ/ลิงก์เป็น <code><?php echo esc_html( WPS_Control_Center_42::get( 'site_url' ) ); ?></code></label></p><p><button class="button button-primary avsora-confirm-button" data-confirm="ยืนยันนำเข้าเฉพาะชุดที่ติ๊ก?" name="avsora_transfer_action" value="import">นำเข้า</button></p></form></div>
            </div>
            <?php if ( $report ) : ?><div class="avsora-card"><h2>รายงาน Import ล่าสุด</h2><pre><?php echo esc_html( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre></div><?php endif; ?>
        </div>
        <?php
    }

    private function group_checkboxes() {
        $labels = array( 'seo' => 'SEO', 'css' => 'Additional CSS', 'labels' => 'ป้ายหน้าโพสต์', 'media' => 'ตั้งค่าจับคู่ภาพ', 'proxy' => 'Media Proxy', 'framework' => 'AV Framework (wps_*)', 'ads' => 'โฆษณา (ads_*)', 'appearance' => 'Appearance: สี/โลโก้/เมนู/เลย์เอาต์', 'theme_all' => 'Theme Mods ทั้งหมด (ใช้กับ Export/Import ทั้งหมดจริง)' );
        echo '<div class="avsora-group-checkboxes">';
        foreach ( $labels as $key => $label ) {
            echo '<label><input type="checkbox" name="groups[]" value="' . esc_attr( $key ) . '"> ' . esc_html( $label ) . '</label><br>';
        }
        echo '</div>';
    }
}
