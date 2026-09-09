<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WPS_Domain_Proxy {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
        add_action( 'admin_init', array( $this, 'save' ) );
        add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_url' ), 30 );
        add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset' ), 30 );
        add_filter( 'the_content', array( $this, 'rewrite_content' ), 30 );
    }

    public function admin_menu() {
        add_submenu_page( ( defined( 'WPMB_WEBSITE_MANAGER_READY' ) ? 'wpmb-dashboard' : 'av-control-center' ), 'Domain / IP Proxy', 'Domain / IP Proxy', 'manage_options', 'wps-domain-proxy', array( $this, 'page' ) );
    }

    public function save() {
        if ( empty( $_POST['avsora_proxy_action'] ) ) { return; }
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden' ); }
        check_admin_referer( 'avsora_proxy_save' );
        $action = sanitize_key( wp_unslash( $_POST['avsora_proxy_action'] ) );
        if ( in_array( $action, array( 'domain_scan', 'domain_run' ), true ) ) {
            $this->handle_domain_replace( 'domain_run' === $action );
        }
        $uploads = wp_upload_dir();
        $source = esc_url_raw( wp_unslash( $_POST['proxy_source'] ?? $uploads['baseurl'] ) );
        $target = esc_url_raw( wp_unslash( $_POST['proxy_target'] ?? '' ) );
        WPS_Control_Center_42::update(
            array(
                'proxy_enabled' => WPS_Control_Center_42::bool_post( 'proxy_enabled' ),
                'proxy_source'  => untrailingslashit( $source ),
                'proxy_target'  => untrailingslashit( $target ),
                'proxy_content' => WPS_Control_Center_42::bool_post( 'proxy_content' ),
                'proxy_srcset'  => WPS_Control_Center_42::bool_post( 'proxy_srcset' ),
            )
        );
        wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-domain-proxy', array( 'avsora_notice' => 'saved' ) ) );
        exit;
    }

    private function handle_domain_replace( $execute ) {
        global $wpdb;
        $source = untrailingslashit( esc_url_raw( wp_unslash( $_POST['domain_source'] ?? '' ) ) );
        $target = untrailingslashit( esc_url_raw( wp_unslash( $_POST['domain_target'] ?? WPS_Control_Center_42::get( 'site_url', home_url( '/' ) ) ) ) );
        if ( ! $source || ! $target || $source === $target ) {
            wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-domain-proxy', array( 'domain_error' => rawurlencode( 'โดเมนต้นทาง/ปลายทางไม่ถูกต้องหรือเหมือนกัน' ) ) ) );
            exit;
        }
        $source_variants = $this->domain_variants( $source );
        $source_host = preg_replace( '/^www\./i', '', (string) wp_parse_url( $source, PHP_URL_HOST ) );
        $scopes = isset( $_POST['domain_scopes'] ) ? array_map( 'sanitize_key', (array) $_POST['domain_scopes'] ) : array();
        $allowed = array( 'plugin', 'theme_mods', 'css', 'posts', 'postmeta' );
        $scopes = array_values( array_intersect( $scopes, $allowed ) );
        if ( ! $scopes ) {
            wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-domain-proxy', array( 'domain_error' => rawurlencode( 'กรุณาเลือกขอบเขต' ) ) ) );
            exit;
        }
        $limit = max( 1, min( 1000, absint( $_POST['domain_limit'] ?? 300 ) ) );
        $offset = max( 0, absint( $_POST['domain_offset'] ?? 0 ) );
        $report = array(
            'time' => current_time( 'mysql' ),
            'mode' => $execute ? 'run' : 'dry-run',
            'source' => $source,
            'target' => $target,
            'scopes' => $scopes,
            'matches' => array(),
            'changed' => array(),
            'samples' => array(),
        );

        if ( in_array( 'plugin', $scopes, true ) ) {
            $options = WPS_Control_Center_42::options();
            $count = $this->count_matches_recursive( $options, $source_variants );
            $report['matches']['plugin'] = $count;
            if ( $execute && $count ) {
                $new = $this->replace_recursive( $options, $source_variants, $target );
                update_option( WPS_Control_Center_42::OPTION, $new, false );
                $report['changed']['plugin'] = $count;
            }
        }

        if ( in_array( 'theme_mods', $scopes, true ) ) {
            $mods = (array) get_theme_mods();
            $count = $this->count_matches_recursive( $mods, $source_variants );
            $report['matches']['theme_mods'] = $count;
            if ( $execute && $count ) {
                $new_mods = $this->replace_recursive( $mods, $source_variants, $target );
                foreach ( $new_mods as $key => $value ) {
                    set_theme_mod( $key, $value );
                }
                $report['changed']['theme_mods'] = $count;
            }
        }

        if ( in_array( 'css', $scopes, true ) ) {
            $css = wp_get_custom_css();
            $count = $this->count_matches_recursive( (string) $css, $source_variants );
            $report['matches']['css'] = $count;
            if ( $execute && $count ) {
                wp_update_custom_css_post( $this->replace_recursive( (string) $css, $source_variants, $target ) );
                $report['changed']['css'] = $count;
            }
        }

        if ( in_array( 'posts', $scopes, true ) ) {
            $like = '%' . $wpdb->esc_like( $source_host ?: $source ) . '%';
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE (post_content LIKE %s OR post_excerpt LIKE %s) AND post_type NOT IN ('revision','attachment') ORDER BY ID ASC LIMIT %d OFFSET %d",
                    $like,
                    $like,
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
            $matches = 0;
            foreach ( $rows as $row ) {
                $row_count = $this->count_matches_recursive( (string) $row['post_content'], $source_variants ) + $this->count_matches_recursive( (string) $row['post_excerpt'], $source_variants );
                $matches += $row_count;
                if ( count( $report['samples'] ) < 20 ) {
                    $report['samples'][] = array( 'type' => 'post', 'id' => (int) $row['ID'], 'title' => $row['post_title'], 'matches' => $row_count );
                }
                if ( $execute && $row_count ) {
                    wp_update_post(
                        array(
                            'ID' => (int) $row['ID'],
                            'post_content' => $this->replace_recursive( $row['post_content'], $source_variants, $target ),
                            'post_excerpt' => $this->replace_recursive( $row['post_excerpt'], $source_variants, $target ),
                        )
                    );
                }
            }
            $report['matches']['posts'] = $matches;
            if ( $execute ) { $report['changed']['posts'] = $matches; }
        }

        if ( in_array( 'postmeta', $scopes, true ) ) {
            $like = '%' . $wpdb->esc_like( $source_host ?: $source ) . '%';
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s ORDER BY meta_id ASC LIMIT %d OFFSET %d",
                    $like,
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
            $matches = 0;
            foreach ( $rows as $row ) {
                $value = maybe_unserialize( $row['meta_value'] );
                $row_count = $this->count_matches_recursive( $value, $source_variants );
                $matches += $row_count;
                if ( count( $report['samples'] ) < 20 ) {
                    $report['samples'][] = array( 'type' => 'postmeta', 'post_id' => (int) $row['post_id'], 'meta_key' => $row['meta_key'], 'matches' => $row_count );
                }
                if ( $execute && $row_count ) {
                    update_metadata_by_mid( 'post', (int) $row['meta_id'], $this->replace_recursive( $value, $source_variants, $target ) );
                }
            }
            $report['matches']['postmeta'] = $matches;
            if ( $execute ) { $report['changed']['postmeta'] = $matches; }
        }

        update_option( 'wps_42_last_domain_report', $report, false );
        wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-domain-proxy', array( 'domain_notice' => $execute ? 'run' : 'dry-run' ) ) );
        exit;
    }

    private function domain_variants( $source ) {
        $host = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );
        if ( ! $host ) { return array( untrailingslashit( $source ) ); }
        $plain = 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
        $hosts = array_unique( array( $plain, 'www.' . $plain ) );
        $variants = array();
        foreach ( array( 'http://', 'https://' ) as $scheme ) {
            foreach ( $hosts as $item ) { $variants[] = $scheme . $item; }
        }
        return array_values( array_unique( array_filter( $variants ) ) );
    }

    private function count_matches_recursive( $value, $sources ) {
        $sources = (array) $sources;
        if ( is_array( $value ) ) {
            $count = 0;
            foreach ( $value as $item ) { $count += $this->count_matches_recursive( $item, $sources ); }
            return $count;
        }
        if ( is_object( $value ) ) {
            return $this->count_matches_recursive( get_object_vars( $value ), $sources );
        }
        if ( ! is_string( $value ) ) { return 0; }
        $count = 0;
        foreach ( $sources as $source ) { $count += substr_count( $value, $source ); }
        return $count;
    }

    private function replace_recursive( $value, $sources, $target ) {
        $sources = (array) $sources;
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $item ) { $value[ $key ] = $this->replace_recursive( $item, $sources, $target ); }
            return $value;
        }
        if ( is_object( $value ) ) {
            foreach ( get_object_vars( $value ) as $key => $item ) { $value->{$key} = $this->replace_recursive( $item, $sources, $target ); }
            return $value;
        }
        return is_string( $value ) ? str_replace( $sources, array_fill( 0, count( $sources ), untrailingslashit( $target ) ), $value ) : $value;
    }

    private function active() {
        return WPS_Control_Center_42::get( 'proxy_enabled' ) && WPS_Control_Center_42::get( 'proxy_source' ) && WPS_Control_Center_42::get( 'proxy_target' );
    }

    public function rewrite_url( $url ) {
        if ( ! $this->active() ) { return $url; }
        return str_replace( untrailingslashit( WPS_Control_Center_42::get( 'proxy_source' ) ), untrailingslashit( WPS_Control_Center_42::get( 'proxy_target' ) ), $url );
    }

    public function rewrite_srcset( $sources ) {
        if ( ! $this->active() || ! WPS_Control_Center_42::get( 'proxy_srcset' ) || ! is_array( $sources ) ) { return $sources; }
        foreach ( $sources as $width => $source ) {
            if ( isset( $source['url'] ) ) { $sources[ $width ]['url'] = $this->rewrite_url( $source['url'] ); }
        }
        return $sources;
    }

    public function rewrite_content( $content ) {
        if ( is_admin() || ! $this->active() || ! WPS_Control_Center_42::get( 'proxy_content' ) ) { return $content; }
        return str_replace( untrailingslashit( WPS_Control_Center_42::get( 'proxy_source' ) ), untrailingslashit( WPS_Control_Center_42::get( 'proxy_target' ) ), $content );
    }

    public function page() {
        $o = WPS_Control_Center_42::options();
        $uploads = wp_upload_dir();
        $domain_report = get_option( 'wps_42_last_domain_report', array() );
        ?>
        <div class="wrap avsora-cc-wrap"><h1>Domain / IP Proxy</h1><?php WPS_Control_Center_42::instance()->notice(); ?>
            <?php if ( ! empty( $_GET['domain_error'] ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( rawurldecode( wp_unslash( $_GET['domain_error'] ) ) ); ?></p></div><?php endif; ?>
            <?php if ( ! empty( $_GET['domain_notice'] ) ) : ?><div class="notice notice-success is-dismissible"><p>ตรวจ/เปลี่ยนโดเมนเสร็จ: <?php echo esc_html( sanitize_key( $_GET['domain_notice'] ) ); ?></p></div><?php endif; ?>
            <div class="avsora-card avsora-warning"><strong>สำคัญ:</strong> การเปลี่ยน URL รูปไปยัง CDN/Reverse Proxy ช่วยไม่เปิดเผย URL ต้นทางในหน้าเว็บ แต่การปกปิด Origin IP จริงต้องตั้งค่า Cloudflare/Reverse Proxy และ Firewall ที่โฮสติ้งด้วย ปลั๊กอิน WordPress เพียงอย่างเดียวบล็อกการเข้าถึง IP ตรงไม่ได้</div>
            <div class="avsora-card"><form method="post"><?php wp_nonce_field( 'avsora_proxy_save' ); ?><input type="hidden" name="avsora_proxy_action" value="save" />
                <table class="form-table">
                    <tr><th>เปิดใช้งาน</th><td><label><input type="checkbox" name="proxy_enabled" value="1" <?php checked( $o['proxy_enabled'] ); ?>> Rewrite URL สื่อ</label></td></tr>
                    <tr><th><label for="proxy_source">URL ต้นทาง</label></th><td><input class="large-text code" id="proxy_source" type="url" name="proxy_source" value="<?php echo esc_attr( $o['proxy_source'] ?: $uploads['baseurl'] ); ?>"><p class="description">ตัวอย่าง: ใช้ URL uploads ของเว็บปัจจุบัน</p></td></tr>
                    <tr><th><label for="proxy_target">ชี้ไปที่</label></th><td><input class="large-text code" id="proxy_target" type="url" name="proxy_target" value="<?php echo esc_attr( $o['proxy_target'] ); ?>" placeholder="https://cdn.example.com/wp-content/uploads"><p class="description">เลือก CDN, Reverse Proxy หรือโดเมนสื่อที่คุณตั้งค่าไว้</p></td></tr>
                    <tr><th>ขอบเขต</th><td><label><input type="checkbox" name="proxy_srcset" value="1" <?php checked( $o['proxy_srcset'] ); ?>> srcset</label><br><label><input type="checkbox" name="proxy_content" value="1" <?php checked( $o['proxy_content'] ); ?>> URL รูปในเนื้อหาโพสต์ตอนแสดงผล</label></td></tr>
                </table>
                <?php submit_button( 'บันทึก Proxy' ); ?>
            </form></div>
            <div class="avsora-card"><h2>เปลี่ยนลิงก์โดเมนเก่าเป็นโดเมนปัจจุบัน</h2>
                <form method="post"><?php wp_nonce_field( 'avsora_proxy_save' ); ?>
                    <table class="form-table">
                        <tr><th><label for="domain_source">โดเมนเก่า</label></th><td><input class="large-text code" type="url" id="domain_source" name="domain_source" value="https://old-domain.example" required></td></tr>
                        <tr><th><label for="domain_target">โดเมนใหม่</label></th><td><input class="large-text code" type="url" id="domain_target" name="domain_target" value="<?php echo esc_attr( untrailingslashit( WPS_Control_Center_42::get( 'site_url', home_url( '/' ) ) ) ); ?>" required></td></tr>
                        <tr><th>ขอบเขต</th><td>
                            <label><input type="checkbox" name="domain_scopes[]" value="plugin" checked> ตั้งค่าปลั๊กอิน</label><br>
                            <label><input type="checkbox" name="domain_scopes[]" value="theme_mods" checked> ตั้งค่าธีม/โฆษณา/SEO</label><br>
                            <label><input type="checkbox" name="domain_scopes[]" value="css" checked> Additional CSS</label><br>
                            <label><input type="checkbox" name="domain_scopes[]" value="posts"> เนื้อหาโพสต์ (ทำเป็นรอบ)</label><br>
                            <label><input type="checkbox" name="domain_scopes[]" value="postmeta"> Post Meta (ทำเป็นรอบ)</label>
                        </td></tr>
                        <tr><th>Batch</th><td>Offset <input type="number" name="domain_offset" min="0" value="0"> Limit <input type="number" name="domain_limit" min="1" max="1000" value="300"></td></tr>
                    </table>
                    <button class="button" name="avsora_proxy_action" value="domain_scan">Dry Run ตรวจอย่างเดียว</button>
                    <button class="button button-primary avsora-confirm-button" data-confirm="ยืนยันแทนที่ข้อความโดเมนในขอบเขตที่เลือก? ควรสำรองฐานข้อมูลก่อน" name="avsora_proxy_action" value="domain_run">เปลี่ยนโดเมนจริง</button>
                </form>
                <?php if ( $domain_report ) : ?><h3>รายงานล่าสุด</h3><pre><?php echo esc_html( wp_json_encode( $domain_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre><?php endif; ?>
            </div>
        </div>
        <?php
    }
}
