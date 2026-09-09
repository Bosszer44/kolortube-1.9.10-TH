<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WPS_Post_Labels {
    private static $instance = null;
    private $template_support = false;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
        add_action( 'admin_init', array( $this, 'save' ) );
        add_action( 'customize_register', array( $this, 'customizer' ) );
        add_action( 'wps_single_video_template_ready', array( $this, 'mark_template_support' ), 10, 1 );
        add_action( 'wps_single_video_labels_before', array( $this, 'output_before' ), 10, 1 );
        add_action( 'wps_single_video_labels_after', array( $this, 'output_after' ), 10, 1 );
        add_filter( 'the_content', array( $this, 'inject' ), 99 );
        add_action( 'wp_head', array( $this, 'hide_native_css' ), 100 );
    }

    public function admin_menu() {
        add_submenu_page( ( defined( 'WPMB_WEBSITE_MANAGER_READY' ) ? 'wpmb-dashboard' : 'av-control-center' ), 'ป้ายหน้าโพสต์', 'ป้ายหน้าโพสต์', 'manage_options', 'wps-post-labels', array( $this, 'page' ) );
    }

    public function save() {
        if ( empty( $_POST['avsora_labels_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( 'avsora_labels_save' );
        $order = isset( $_POST['labels_order'] ) ? sanitize_text_field( wp_unslash( $_POST['labels_order'] ) ) : 'categories,actors,studios,post_tags';
        $valid = array( 'categories', 'post_tags', 'actors', 'studios' );
        $order_parts = array_values( array_unique( array_intersect( array_map( 'sanitize_key', explode( ',', $order ) ), $valid ) ) );
        foreach ( $valid as $item ) {
            if ( ! in_array( $item, $order_parts, true ) ) {
                $order_parts[] = $item;
            }
        }
        WPS_Control_Center_42::update(
            array(
                'labels_enabled'         => WPS_Control_Center_42::bool_post( 'labels_enabled' ),
                'labels_position'        => in_array( ( $_POST['labels_position'] ?? 'after' ), array( 'before', 'after' ), true ) ? sanitize_key( $_POST['labels_position'] ) : 'after',
                'labels_hide_native'     => WPS_Control_Center_42::bool_post( 'labels_hide_native' ),
                'labels_categories'      => WPS_Control_Center_42::bool_post( 'labels_categories' ),
                'labels_post_tags'       => WPS_Control_Center_42::bool_post( 'labels_post_tags' ),
                'labels_actors'          => WPS_Control_Center_42::bool_post( 'labels_actors' ),
                'labels_studios'         => WPS_Control_Center_42::bool_post( 'labels_studios' ),
                'labels_order'           => implode( ',', $order_parts ),
                'labels_category_prefix' => sanitize_text_field( wp_unslash( $_POST['labels_category_prefix'] ?? '' ) ),
                'labels_tag_prefix'      => sanitize_text_field( wp_unslash( $_POST['labels_tag_prefix'] ?? '' ) ),
                'labels_actor_prefix'    => sanitize_text_field( wp_unslash( $_POST['labels_actor_prefix'] ?? '' ) ),
                'labels_studio_prefix'   => sanitize_text_field( wp_unslash( $_POST['labels_studio_prefix'] ?? '' ) ),
                'labels_max_each'        => max( 0, min( 100, absint( $_POST['labels_max_each'] ?? 0 ) ) ),
            )
        );
        wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-post-labels', array( 'avsora_notice' => 'saved' ) ) );
        exit;
    }

    public function page() {
        $o = WPS_Control_Center_42::options();
        ?>
        <div class="wrap avsora-cc-wrap"><h1>ป้ายหน้าโพสต์</h1><?php WPS_Control_Center_42::instance()->notice(); ?>
            <div class="avsora-card"><form method="post">
                <?php wp_nonce_field( 'avsora_labels_save' ); ?><input type="hidden" name="avsora_labels_action" value="save" />
                <table class="form-table">
                    <tr><th>การแสดงผล</th><td><label><input type="checkbox" name="labels_enabled" value="1" <?php checked( $o['labels_enabled'] ); ?> /> เปิดป้ายที่ควบคุมจากหน้านี้</label><br><label><input type="checkbox" name="labels_hide_native" value="1" <?php checked( $o['labels_hide_native'] ); ?> /> ซ่อนบล็อก .tags-list เดิมของธีมเพื่อไม่ให้ซ้ำ</label></td></tr>
                    <tr><th>ตำแหน่ง</th><td><select name="labels_position"><option value="after" <?php selected( $o['labels_position'], 'after' ); ?>>ท้ายเนื้อหา</option><option value="before" <?php selected( $o['labels_position'], 'before' ); ?>>ก่อนเนื้อหา</option></select></td></tr>
                    <tr><th>ข้อมูลที่ดึง</th><td>
                        <label><input type="checkbox" name="labels_categories" value="1" <?php checked( $o['labels_categories'] ); ?> /> หมวดหมู่</label><br>
                        <label><input type="checkbox" name="labels_post_tags" value="1" <?php checked( $o['labels_post_tags'] ); ?> /> ป้ายกำกับ</label><br>
                        <label><input type="checkbox" name="labels_actors" value="1" <?php checked( $o['labels_actors'] ); ?> /> นักแสดง</label><br>
                        <label><input type="checkbox" name="labels_studios" value="1" <?php checked( $o['labels_studios'] ); ?> /> ค่าย</label>
                    </td></tr>
                    <tr><th><label for="labels_order">ลำดับ</label></th><td><input class="large-text code" id="labels_order" name="labels_order" value="<?php echo esc_attr( $o['labels_order'] ); ?>" /><p class="description">categories,actors,studios,post_tags</p></td></tr>
                    <tr><th>คำนำหน้าลิงก์</th><td>
                        <input class="regular-text" name="labels_category_prefix" placeholder="หมวด: " value="<?php echo esc_attr( $o['labels_category_prefix'] ); ?>" /> หมวดหมู่<br>
                        <input class="regular-text" name="labels_tag_prefix" placeholder="แท็ก: " value="<?php echo esc_attr( $o['labels_tag_prefix'] ); ?>" /> ป้ายกำกับ<br>
                        <input class="regular-text" name="labels_actor_prefix" placeholder="นักแสดง: " value="<?php echo esc_attr( $o['labels_actor_prefix'] ); ?>" /> นักแสดง<br>
                        <input class="regular-text" name="labels_studio_prefix" placeholder="ค่าย: " value="<?php echo esc_attr( $o['labels_studio_prefix'] ); ?>" /> ค่าย
                    </td></tr>
                    <tr><th><label for="labels_max_each">จำนวนสูงสุดต่อกลุ่ม</label></th><td><input type="number" min="0" max="100" id="labels_max_each" name="labels_max_each" value="<?php echo esc_attr( $o['labels_max_each'] ); ?>" /><p class="description">0 = ไม่จำกัด</p></td></tr>
                </table>
                <?php submit_button( 'บันทึกป้ายหน้าโพสต์' ); ?>
            </form></div>
            <div class="avsora-card"><p>HTML ใช้คลาสเดิม <code>row tags-list</code> จึงรับ CSS เดิมของเว็บ และระบบไม่กำหนดสีใหม่</p></div>
        </div>
        <?php
    }

    public function customizer( $wp_customize ) {
        $wp_customize->add_section( 'wps_labels', array( 'title' => 'AV Framework: ป้ายหน้าโพสต์', 'priority' => 167 ) );
        $checkboxes = array(
            'labels_enabled'     => 'เปิดป้ายหน้าโพสต์',
            'labels_hide_native' => 'ซ่อนป้ายเดิมของธีม/Child Theme',
            'labels_categories'  => 'แสดงหมวดหมู่',
            'labels_post_tags'   => 'แสดงป้ายกำกับ',
            'labels_actors'      => 'แสดงนักแสดง',
            'labels_studios'     => 'แสดงค่าย',
        );
        foreach ( $checkboxes as $key => $label ) {
            $setting = 'wps_42_' . $key;
            $wp_customize->add_setting( $setting, array( 'default' => WPS_Control_Center_42::get( $key ), 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'type' => 'option' ) );
            $wp_customize->add_control( $setting, array( 'section' => 'wps_labels', 'label' => $label, 'type' => 'checkbox' ) );
        }
        $wp_customize->add_setting( 'wps_42_labels_position', array( 'default' => WPS_Control_Center_42::get( 'labels_position' ), 'sanitize_callback' => array( $this, 'sanitize_position' ), 'type' => 'option' ) );
        $wp_customize->add_control( 'wps_42_labels_position', array( 'section' => 'wps_labels', 'label' => 'ตำแหน่ง', 'type' => 'select', 'choices' => array( 'after' => 'ท้ายเนื้อหา', 'before' => 'ก่อนเนื้อหา' ) ) );

        $text_fields = array(
            'labels_order'           => array( 'ลำดับกลุ่ม', 'categories,actors,studios,post_tags' ),
            'labels_category_prefix' => array( 'หัวข้อหมวดหมู่', 'หมวดหมู่ :' ),
            'labels_actor_prefix'    => array( 'หัวข้อนักแสดง', 'นักแสดง :' ),
            'labels_studio_prefix'   => array( 'หัวข้อค่าย', 'สตูดิโอ :' ),
            'labels_tag_prefix'      => array( 'หัวข้อป้ายกำกับ', 'TAG :' ),
        );
        foreach ( $text_fields as $key => $field ) {
            $setting = 'wps_42_' . $key;
            $sanitize = 'labels_order' === $key ? array( $this, 'sanitize_order' ) : 'sanitize_text_field';
            $wp_customize->add_setting( $setting, array( 'default' => WPS_Control_Center_42::get( $key ), 'sanitize_callback' => $sanitize, 'type' => 'option' ) );
            $wp_customize->add_control( $setting, array( 'section' => 'wps_labels', 'label' => $field[0], 'type' => 'text', 'input_attrs' => array( 'placeholder' => $field[1] ) ) );
        }
        $wp_customize->add_setting( 'wps_42_labels_max_each', array( 'default' => WPS_Control_Center_42::get( 'labels_max_each' ), 'sanitize_callback' => array( $this, 'sanitize_max' ), 'type' => 'option' ) );
        $wp_customize->add_control( 'wps_42_labels_max_each', array( 'section' => 'wps_labels', 'label' => 'จำนวนสูงสุดต่อกลุ่ม (0 = ไม่จำกัด)', 'type' => 'number', 'input_attrs' => array( 'min' => 0, 'max' => 100 ) ) );
        add_action( 'customize_save_after', array( $this, 'sync_customizer_options' ) );
    }

    public function sanitize_checkbox( $value ) { return empty( $value ) ? 0 : 1; }
    public function sanitize_position( $value ) { return in_array( $value, array( 'before', 'after' ), true ) ? $value : 'after'; }
    public function sanitize_max( $value ) { return max( 0, min( 100, absint( $value ) ) ); }
    public function sanitize_order( $value ) {
        $valid = array( 'categories', 'post_tags', 'actors', 'studios' );
        $parts = array_values( array_unique( array_intersect( array_map( 'sanitize_key', explode( ',', (string) $value ) ), $valid ) ) );
        foreach ( $valid as $item ) {
            if ( ! in_array( $item, $parts, true ) ) {
                $parts[] = $item;
            }
        }
        return implode( ',', $parts );
    }

    public function sync_customizer_options() {
        $updates = array();
        $checkboxes = array( 'labels_enabled', 'labels_hide_native', 'labels_categories', 'labels_post_tags', 'labels_actors', 'labels_studios' );
        $texts = array( 'labels_category_prefix', 'labels_tag_prefix', 'labels_actor_prefix', 'labels_studio_prefix' );
        foreach ( array_merge( $checkboxes, $texts, array( 'labels_position', 'labels_order', 'labels_max_each' ) ) as $key ) {
            $option = 'wps_42_' . $key;
            $value = get_option( $option, null );
            if ( null === $value ) {
                continue;
            }
            if ( in_array( $key, $checkboxes, true ) ) {
                $updates[ $key ] = $this->sanitize_checkbox( $value );
            } elseif ( 'labels_position' === $key ) {
                $updates[ $key ] = $this->sanitize_position( $value );
            } elseif ( 'labels_order' === $key ) {
                $updates[ $key ] = $this->sanitize_order( $value );
            } elseif ( 'labels_max_each' === $key ) {
                $updates[ $key ] = $this->sanitize_max( $value );
            } else {
                $updates[ $key ] = sanitize_text_field( $value );
            }
            delete_option( $option );
        }
        if ( $updates ) {
            WPS_Control_Center_42::update( $updates );
        }
    }

    public function mark_template_support( $post_id ) {
        $this->template_support = absint( $post_id ) > 0;
    }

    public function output_before( $post_id ) {
        if ( 'before' === WPS_Control_Center_42::get( 'labels_position', 'after' ) ) {
            $this->output( $post_id );
        }
    }

    public function output_after( $post_id ) {
        if ( 'after' === WPS_Control_Center_42::get( 'labels_position', 'after' ) ) {
            $this->output( $post_id );
        }
    }

    private function output( $post_id ) {
        if ( ! WPS_Control_Center_42::get( 'labels_enabled' ) ) {
            return;
        }
        echo $this->render( absint( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes all dynamic values.
    }

    public function hide_native_css() {
        if ( WPS_Control_Center_42::get( 'labels_enabled' ) && WPS_Control_Center_42::get( 'labels_hide_native' ) && is_singular() ) {
            echo '<style id="avsora-hide-native-tags">.single .tags-list:not(.wps-custom-tags){display:none!important}</style>' . "\n";
        }
    }

    public function inject( $content ) {
        if ( $this->template_support || is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() || ! WPS_Control_Center_42::get( 'labels_enabled' ) ) {
            return $content;
        }
        $html = $this->render( get_the_ID() );
        if ( ! $html ) {
            return $content;
        }
        return 'before' === WPS_Control_Center_42::get( 'labels_position' ) ? $html . $content : $content . $html;
    }

    private function actor_taxonomy() {
        $configured = sanitize_key( WPS_Control_Center_42::get( 'actor_taxonomy', 'actors' ) );
        if ( taxonomy_exists( $configured ) ) {
            return $configured;
        }
        foreach ( array( 'actors', 'actor', 'actress' ) as $candidate ) {
            if ( taxonomy_exists( $candidate ) ) {
                return $candidate;
            }
        }
        return $configured ?: 'actors';
    }

    public function render( $post_id ) {
        $actor_taxonomy = $this->actor_taxonomy();
        $definitions = array(
            'categories' => array(
                'enabled'  => WPS_Control_Center_42::get( 'labels_categories' ),
                'terms'    => get_the_category( $post_id ),
                'prefix'   => WPS_Control_Center_42::get( 'labels_category_prefix' ),
                'taxonomy' => 'category',
                'class'    => 'category-list',
                'icon'     => 'fa-folder',
            ),
            'post_tags' => array(
                'enabled'  => WPS_Control_Center_42::get( 'labels_post_tags' ),
                'terms'    => get_the_tags( $post_id ),
                'prefix'   => WPS_Control_Center_42::get( 'labels_tag_prefix' ),
                'taxonomy' => 'post_tag',
                'class'    => 'tag-list',
                'icon'     => 'fa-tag',
            ),
            'actors' => array(
                'enabled'  => WPS_Control_Center_42::get( 'labels_actors' ),
                'terms'    => wp_get_post_terms( $post_id, $actor_taxonomy ),
                'prefix'   => WPS_Control_Center_42::get( 'labels_actor_prefix' ),
                'taxonomy' => $actor_taxonomy,
                'class'    => 'actor-list',
                'icon'     => 'fa-star',
            ),
            'studios' => array(
                'enabled'  => WPS_Control_Center_42::get( 'labels_studios' ),
                'terms'    => wp_get_post_terms( $post_id, 'studio' ),
                'prefix'   => WPS_Control_Center_42::get( 'labels_studio_prefix' ),
                'taxonomy' => 'studio',
                'class'    => 'studio-list',
                'icon'     => 'fa-video-camera',
            ),
        );
        $order = array_filter( array_map( 'sanitize_key', explode( ',', (string) WPS_Control_Center_42::get( 'labels_order' ) ) ) );
        $groups = array();
        foreach ( $order as $key ) {
            if ( empty( $definitions[ $key ]['enabled'] ) ) {
                continue;
            }
            $html = $this->render_group( $definitions[ $key ] );
            if ( $html ) {
                $groups[] = $html;
            }
        }
        if ( ! $groups ) {
            return '';
        }
        return '<div class="row tags-list wps-custom-tags"><div class="col-12"><div class="list text-start" style="text-align:left;">' . implode( '', $groups ) . '</div></div></div>';
    }

    private function render_group( array $group ) {
        $terms = $group['terms'];
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return '';
        }
        $links = array();
        $max = absint( WPS_Control_Center_42::get( 'labels_max_each', 0 ) );
        foreach ( (array) $terms as $term ) {
            if ( ! is_object( $term ) || empty( $term->term_id ) ) {
                continue;
            }
            $url = get_term_link( (int) $term->term_id, $group['taxonomy'] );
            if ( is_wp_error( $url ) ) {
                continue;
            }
            $links[] = sprintf(
                '<a href="%1$s" class="label" rel="tag" title="%2$s">%3$s</a> ',
                esc_url( $url ),
                esc_attr( $term->name ),
                esc_html( $term->name )
            );
            if ( $max && count( $links ) >= $max ) {
                break;
            }
        }
        if ( ! $links ) {
            return '';
        }
        $prefix = trim( (string) $group['prefix'] );
        if ( '' !== $prefix ) {
            $prefix = rtrim( $prefix, " \t\n\r\0\x0B:" );
            $heading = '<strong>' . esc_html( $prefix ) . ' <i class="fa ' . esc_attr( $group['icon'] ) . '" aria-hidden="true"></i> :</strong> ';
        } else {
            $heading = '';
        }
        return '<div class="' . esc_attr( $group['class'] ) . ' mb-2" style="text-align:left;">' . $heading . implode( '', $links ) . '</div>';
    }

}
