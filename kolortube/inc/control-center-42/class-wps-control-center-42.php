<?php
/** KolorTube 4.2 integrated control-center modules. */
defined( 'ABSPATH' ) || exit;

final class WPS_Control_Center_42 {
    private static $instance = null;
    private static $enabled = false;
    const OPTION = 'wps_42_options';
    const LEGACY_OPTION = 'avsora_cc_options';

    public static function register() {
        if ( defined( 'AVSORA_CC_VERSION' ) || class_exists( 'AVSORA_CC_Core', false ) ) {
            add_action( 'admin_notices', array( __CLASS__, 'standalone_notice' ) );
            return;
        }
        self::$enabled = true;
        self::ensure_defaults();
        self::instance();
        WPS_Post_Labels::instance();
        // WP MY BOSS owns File Manager, Media maintenance and all website tools.
        // UNI-IM-PORT is the only Import/Export owner.
        if ( ! defined( 'WPMB_WEBSITE_MANAGER_READY' ) ) {
            WPS_File_Manager::instance();
            WPS_Code_Media_Matcher::instance();
        }
        if ( ! wps_uni_owns_import_export() ) {
            WPS_Selective_Transfer::instance();
        }
        WPS_Domain_Proxy::instance();
    }

    public static function is_enabled() {
        return self::$enabled;
    }

    public static function standalone_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>KolorTube 4.2:</strong> ตรวจพบปลั๊กอิน Control Center แบบแยกติดตั้งเดิม กรุณาปิดปลั๊กอินนั้นเพื่อใช้โมดูลที่รวมอยู่ในธีมและป้องกันการทำงานซ้ำ</p></div>';
    }

    public static function defaults() {
        return array(
            'site_url'                   => function_exists( 'home_url' ) ? trailingslashit( home_url( '/' ) ) : '',
            'promo_right_link_url'       => '',
            'promo_right_image_url'      => '',
            'actor_taxonomy'             => 'actors',
            'actor_index'                => 'index',
            'actor_follow'               => 'follow',
            'actor_title_template'       => '%%actor%% | ผลงานนักแสดง AV บน %%sitename%%',
            'actor_description_template' => 'รวมผลงานของ %%actor%% ทั้งหมด %%count%% เรื่องบน %%sitename%% อัปเดตรายการล่าสุดและค้นหาผลงานที่เกี่ยวข้องได้จากหน้านี้',
            'actor_og_title_template'    => '%%actor%% | %%sitename%%',
            'actor_og_desc_template'     => 'รวมผลงานของ %%actor%% บน %%sitename%%',
            'labels_enabled'             => 1,
            'labels_position'            => 'after',
            'labels_hide_native'         => 1,
            'labels_categories'          => 1,
            'labels_post_tags'           => 1,
            'labels_actors'              => 1,
            'labels_studios'             => 1,
            'labels_order'               => 'categories,actors,studios,post_tags',
            'labels_category_prefix'     => 'หมวดหมู่ :',
            'labels_tag_prefix'          => 'TAG :',
            'labels_actor_prefix'        => 'นักแสดง :',
            'labels_studio_prefix'       => 'สตูดิโอ :',
            'labels_max_each'            => 0,
            'media_post_type'            => 'post',
            'media_overwrite_featured'   => 0,
            'media_gallery_mode'         => 'content',
            'media_overwrite_gallery'    => 0,
            'proxy_enabled'              => 0,
            'proxy_target'               => '',
            'proxy_source'               => '',
            'proxy_content'              => 1,
            'proxy_srcset'               => 1,
        );
    }

    public static function ensure_defaults() {
        $current = get_option( self::OPTION, null );
        if ( ! is_array( $current ) ) {
            $legacy = get_option( self::LEGACY_OPTION, array() );
            $current = is_array( $legacy ) ? $legacy : array();
        }
        $merged = wp_parse_args( $current, self::defaults() );
        if ( $merged !== $current ) {
            update_option( self::OPTION, $merged, false );
        }
    }

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function options() {
        $stored = get_option( self::OPTION, array() );
        return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
    }

    public static function get( $key, $fallback = null ) {
        $options = self::options();
        return array_key_exists( $key, $options ) ? $options[ $key ] : $fallback;
    }

    public static function update( array $values ) {
        $options = array_merge( self::options(), $values );
        update_option( self::OPTION, $options, false );
        return $options;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'admin_init', array( $this, 'save_general' ) );
        add_action( 'admin_init', array( $this, 'save_floating_promo' ) );
    }

    public function admin_menu() {
        add_submenu_page(
            ( defined( 'WPMB_WEBSITE_MANAGER_READY' ) ? 'wpmb-dashboard' : 'av-control-center' ),
            'KolorTube 4.2.1',
            'เครื่องมือ 4.2',
            'manage_options',
            'wps-42-tools',
            array( $this, 'dashboard' )
        );
        // Floating-ad single page removed in 4.3.2; use AV Framework > ธีมจบงาน or All Banners instead.
    }

    public function admin_assets( $hook ) {
        if ( false === strpos( (string) $hook, 'wps-' ) ) {
            return;
        }
        $css = WPS_PATH . '/assets/css/admin-wps42.css';
        $js  = WPS_PATH . '/assets/js/admin-wps42.js';
        wp_enqueue_style( 'wps-42-admin', WPS_URI . '/assets/css/admin-wps42.css', array(), is_file( $css ) ? (string) filemtime( $css ) : WPS_FRAMEWORK_VERSION );
        wp_enqueue_script( 'wps-42-admin', WPS_URI . '/assets/js/admin-wps42.js', array( 'jquery' ), is_file( $js ) ? (string) filemtime( $js ) : WPS_FRAMEWORK_VERSION, true );
    }

    public function save_general() {
        if ( empty( $_POST['avsora_cc_general_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'คุณไม่มีสิทธิ์ดำเนินการนี้', 'wpst' ) );
        }
        check_admin_referer( 'avsora_cc_general_save' );
        $site_url = isset( $_POST['site_url'] ) ? esc_url_raw( wp_unslash( $_POST['site_url'] ) ) : home_url( '/' );
        self::update( array( 'site_url' => trailingslashit( $site_url ?: home_url( '/' ) ) ) );
        wp_safe_redirect( self::admin_page_url( 'wps-42-tools', array( 'avsora_notice' => 'saved' ) ) );
        exit;
    }

    public function save_floating_promo() {
        if ( empty( $_POST['wps_floating_promo_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'คุณไม่มีสิทธิ์ดำเนินการนี้', 'wpst' ) );
        }
        check_admin_referer( 'wps_floating_promo_save' );

        $link_url  = isset( $_POST['promo_right_link_url'] ) ? esc_url_raw( wp_unslash( $_POST['promo_right_link_url'] ) ) : '';
        $image_url = isset( $_POST['promo_right_image_url'] ) ? esc_url_raw( wp_unslash( $_POST['promo_right_image_url'] ) ) : '';

        if ( '' === $link_url || '' === $image_url ) {
            wp_safe_redirect(
                self::admin_page_url(
                    'wps-floating-promo',
                    array( 'avsora_notice' => 'promo_invalid' )
                )
            );
            exit;
        }

        self::update(
            array(
                'promo_right_link_url'  => $link_url,
                'promo_right_image_url' => $image_url,
            )
        );

        wp_safe_redirect(
            self::admin_page_url(
                'wps-floating-promo',
                array( 'avsora_notice' => 'promo_saved' )
            )
        );
        exit;
    }

    public function floating_promo_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $options = self::options();
        $notice  = isset( $_GET['avsora_notice'] ) ? sanitize_key( wp_unslash( $_GET['avsora_notice'] ) ) : '';
        ?>
        <div class="wrap avsora-cc-wrap">
            <h1>โฆษณาลอยด้านขวา</h1>
            <?php if ( 'promo_saved' === $notice ) : ?>
                <div class="notice notice-success is-dismissible"><p>บันทึกลิงก์โฆษณาเรียบร้อย</p></div>
            <?php elseif ( 'promo_invalid' === $notice ) : ?>
                <div class="notice notice-error"><p>กรุณากรอก URL ปลายทางและ URL รูปภาพให้ครบ</p></div>
            <?php endif; ?>
            <div class="avsora-card">
                <form method="post">
                    <?php wp_nonce_field( 'wps_floating_promo_save' ); ?>
                    <input type="hidden" name="wps_floating_promo_action" value="save" />
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="promo_right_link_url">ลิงก์เมื่อคลิกรูป</label></th>
                            <td>
                                <input class="large-text code" type="url" id="promo_right_link_url" name="promo_right_link_url" value="<?php echo esc_attr( $options['promo_right_link_url'] ); ?>" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="promo_right_image_url">URL รูปโฆษณา</label></th>
                            <td>
                                <input class="large-text code" type="url" id="promo_right_image_url" name="promo_right_image_url" value="<?php echo esc_attr( $options['promo_right_image_url'] ); ?>" required />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'บันทึกลิงก์โฆษณา' ); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $options = self::options();
        ?>
        <div class="wrap avsora-cc-wrap">
            <h1>KolorTube 4.2.1</h1>
            <?php $this->notice(); ?>
            <div class="avsora-grid">
                <div class="avsora-card">
                    <h2>โมดูลที่เพิ่มใน 4.2</h2>
                    <table class="widefat striped"><tbody>
                        <tr><td>ป้ายหน้าโพสต์ / Customizer</td><td><?php echo ! empty( $options['labels_enabled'] ) ? '<strong>เปิด</strong>' : 'ปิด'; ?></td></tr>
                        <tr><td>จับคู่ภาพตาม CODE</td><td><strong>มี Dry Run และ Rollback</strong></td></tr>
                        <tr><td>File Manager</td><td><strong>สำรองก่อนแก้ไข</strong></td></tr>
                        <tr><td>Import / Export แยกชุด</td><td><strong>CSS / SEO / Safe All / All</strong></td></tr>
                        <tr><td>Domain / Media Proxy</td><td><?php echo ! empty( $options['proxy_enabled'] ) ? '<strong>เปิด</strong>' : 'ปิด'; ?></td></tr>
                    </tbody></table>
                </div>
                <div class="avsora-card">
                    <h2>โดเมนปัจจุบัน</h2>
                    <form method="post">
                        <?php wp_nonce_field( 'avsora_cc_general_save' ); ?>
                        <input type="hidden" name="avsora_cc_general_action" value="save" />
                        <p><input class="regular-text code" type="url" name="site_url" value="<?php echo esc_attr( $options['site_url'] ); ?>" required /></p>
                        <p class="description">ใช้เป็นปลายทางเมื่อนำเข้าค่าจากเว็บอื่น ค่าเริ่มต้น: โดเมนปัจจุบันของเว็บ</p>
                        <?php submit_button( 'บันทึกโดเมน', 'primary', 'submit', false ); ?>
                    </form>
                </div>
            </div>
            <div class="avsora-card">
                <h2>ข้อกำหนดของชุด 4.2</h2>
                <p>ไม่เปลี่ยนสี โลโก้ เมนู หรือ Additional CSS อัตโนมัติ การนำเข้า Appearance ต้องเลือกเองอย่างชัดเจน</p>
            </div>
        </div>
        <?php
    }

    public function notice() {
        $notice = isset( $_GET['avsora_notice'] ) ? sanitize_key( wp_unslash( $_GET['avsora_notice'] ) ) : '';
        if ( 'saved' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>บันทึกเรียบร้อย</p></div>';
        }
    }

    public static function admin_page_url( $slug, array $args = array() ) {
        return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
    }

    public static function clean_textarea( $value ) {
        return sanitize_textarea_field( wp_unslash( $value ) );
    }

    public static function bool_post( $key ) {
        return empty( $_POST[ $key ] ) ? 0 : 1;
    }
}
