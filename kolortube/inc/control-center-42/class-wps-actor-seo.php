<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WPS_Actor_SEO {
    private static $instance = null;
    private $fallback_printed = false;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
        add_action( 'admin_init', array( $this, 'save_settings' ) );
        add_action( 'init', array( $this, 'register_meta_and_term_hooks' ), 30 );
        add_action( 'customize_register', array( $this, 'customizer' ) );

        add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 40 );
        add_filter( 'wpseo_title', array( $this, 'yoast_title' ), 40 );
        add_filter( 'wpseo_metadesc', array( $this, 'yoast_description' ), 40 );
        add_filter( 'wpseo_canonical', array( $this, 'yoast_canonical' ), 40 );
        add_filter( 'wpseo_robots', array( $this, 'yoast_robots' ), 40 );
        add_filter( 'wpseo_robots_array', array( $this, 'yoast_robots_array' ), 40 );
        add_filter( 'wpseo_opengraph_title', array( $this, 'yoast_og_title' ), 40 );
        add_filter( 'wpseo_opengraph_desc', array( $this, 'yoast_og_description' ), 40 );
        add_filter( 'wpseo_opengraph_image', array( $this, 'yoast_og_image' ), 40 );
        add_action( 'wp_head', array( $this, 'fallback_meta' ), 2 );
    }

    public function admin_menu() {
        add_submenu_page(
            ( defined( 'WPMB_WEBSITE_MANAGER_READY' ) ? 'wpmb-dashboard' : 'av-control-center' ),
            'SEO นักแสดง',
            'SEO นักแสดง',
            'manage_options',
            'wps-actor-seo',
            array( $this, 'page' )
        );
    }

    public function taxonomy() {
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

    public function register_meta_and_term_hooks() {
        $taxonomy = $this->taxonomy();
        foreach ( $this->meta_keys() as $key ) {
            register_term_meta(
                $taxonomy,
                $key,
                array(
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => false,
                    'sanitize_callback' => ( '_avsora_actor_robots' === $key ) ? array( $this, 'sanitize_robots' ) : 'sanitize_text_field',
                    'auth_callback'     => static function () {
                        return current_user_can( 'manage_categories' );
                    },
                )
            );
        }
        add_action( $taxonomy . '_add_form_fields', array( $this, 'add_term_fields' ) );
        add_action( $taxonomy . '_edit_form_fields', array( $this, 'edit_term_fields' ) );
        add_action( 'created_' . $taxonomy, array( $this, 'save_term_fields' ) );
        add_action( 'edited_' . $taxonomy, array( $this, 'save_term_fields' ) );
    }

    private function meta_keys() {
        return array(
            '_avsora_actor_seo_title',
            '_avsora_actor_seo_description',
            '_avsora_actor_canonical',
            '_avsora_actor_robots',
            '_avsora_actor_og_title',
            '_avsora_actor_og_description',
            '_avsora_actor_og_image',
        );
    }

    public function sanitize_robots( $value ) {
        $value = strtolower( sanitize_text_field( $value ) );
        $allowed = array( 'index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow' );
        return in_array( $value, $allowed, true ) ? $value : 'index,follow';
    }

    public function add_term_fields() {
        wp_nonce_field( 'avsora_actor_term_save', 'avsora_actor_term_nonce' );
        ?>
        <div class="form-field"><label for="avsora_actor_seo_title">SEO Title</label><input type="text" name="avsora_actor_seo_title" id="avsora_actor_seo_title" /></div>
        <div class="form-field"><label for="avsora_actor_seo_description">Meta Description</label><textarea name="avsora_actor_seo_description" id="avsora_actor_seo_description" rows="4"></textarea></div>
        <div class="form-field"><label for="avsora_actor_canonical">Canonical URL</label><input type="url" name="avsora_actor_canonical" id="avsora_actor_canonical" /></div>
        <div class="form-field"><label for="avsora_actor_robots">Robots</label><?php $this->robots_select( WPS_Control_Center_42::get( 'actor_index', 'index' ) . ',' . WPS_Control_Center_42::get( 'actor_follow', 'follow' ), 'avsora_actor_robots' ); ?></div>
        <div class="form-field"><label for="avsora_actor_og_title">OG Title</label><input type="text" name="avsora_actor_og_title" id="avsora_actor_og_title" /></div>
        <div class="form-field"><label for="avsora_actor_og_description">OG Description</label><textarea name="avsora_actor_og_description" id="avsora_actor_og_description" rows="3"></textarea></div>
        <div class="form-field"><label for="avsora_actor_og_image">OG Image URL</label><input type="url" name="avsora_actor_og_image" id="avsora_actor_og_image" /></div>
        <?php
    }

    public function edit_term_fields( $term ) {
        $values = $this->term_values( $term->term_id );
        wp_nonce_field( 'avsora_actor_term_save', 'avsora_actor_term_nonce' );
        ?>
        <tr class="form-field"><th><label for="avsora_actor_seo_title">SEO Title</label></th><td><input type="text" name="avsora_actor_seo_title" id="avsora_actor_seo_title" value="<?php echo esc_attr( $values['title'] ); ?>" class="regular-text" /></td></tr>
        <tr class="form-field"><th><label for="avsora_actor_seo_description">Meta Description</label></th><td><textarea name="avsora_actor_seo_description" id="avsora_actor_seo_description" rows="4" class="large-text"><?php echo esc_textarea( $values['description'] ); ?></textarea></td></tr>
        <tr class="form-field"><th><label for="avsora_actor_canonical">Canonical URL</label></th><td><input type="url" name="avsora_actor_canonical" id="avsora_actor_canonical" value="<?php echo esc_attr( $values['canonical'] ); ?>" class="regular-text code" /></td></tr>
        <tr class="form-field"><th><label for="avsora_actor_robots">Robots</label></th><td><?php $this->robots_select( $values['robots'] ?: WPS_Control_Center_42::get( 'actor_index', 'index' ) . ',' . WPS_Control_Center_42::get( 'actor_follow', 'follow' ), 'avsora_actor_robots' ); ?></td></tr>
        <tr class="form-field"><th><label for="avsora_actor_og_title">OG Title</label></th><td><input type="text" name="avsora_actor_og_title" id="avsora_actor_og_title" value="<?php echo esc_attr( $values['og_title'] ); ?>" class="regular-text" /></td></tr>
        <tr class="form-field"><th><label for="avsora_actor_og_description">OG Description</label></th><td><textarea name="avsora_actor_og_description" id="avsora_actor_og_description" rows="3" class="large-text"><?php echo esc_textarea( $values['og_description'] ); ?></textarea></td></tr>
        <tr class="form-field"><th><label for="avsora_actor_og_image">OG Image URL</label></th><td><input type="url" name="avsora_actor_og_image" id="avsora_actor_og_image" value="<?php echo esc_attr( $values['og_image'] ); ?>" class="regular-text code" /></td></tr>
        <?php
    }

    private function robots_select( $selected, $name = 'avsora_actor_robots' ) {
        echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
        foreach ( array( 'index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow' ) as $value ) {
            printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $value ), selected( $selected, $value, false ) );
        }
        echo '</select>';
    }

    public function save_term_fields( $term_id ) {
        if ( empty( $_POST['avsora_actor_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['avsora_actor_term_nonce'] ) ), 'avsora_actor_term_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }
        $map = array(
            'avsora_actor_seo_title'       => '_avsora_actor_seo_title',
            'avsora_actor_seo_description' => '_avsora_actor_seo_description',
            'avsora_actor_canonical'       => '_avsora_actor_canonical',
            'avsora_actor_robots'          => '_avsora_actor_robots',
            'avsora_actor_og_title'        => '_avsora_actor_og_title',
            'avsora_actor_og_description'  => '_avsora_actor_og_description',
            'avsora_actor_og_image'        => '_avsora_actor_og_image',
        );
        foreach ( $map as $input => $meta_key ) {
            $raw = isset( $_POST[ $input ] ) ? wp_unslash( $_POST[ $input ] ) : '';
            if ( false !== strpos( $input, 'canonical' ) || false !== strpos( $input, 'image' ) ) {
                $value = esc_url_raw( $raw );
            } elseif ( 'avsora_actor_robots' === $input ) {
                $value = $this->sanitize_robots( $raw );
            } else {
                $value = sanitize_text_field( $raw );
            }
            if ( '' === $value ) {
                delete_term_meta( $term_id, $meta_key );
            } else {
                update_term_meta( $term_id, $meta_key, $value );
            }
        }
    }

    private function term_values( $term_id ) {
        return array(
            'title'          => (string) get_term_meta( $term_id, '_avsora_actor_seo_title', true ),
            'description'    => (string) get_term_meta( $term_id, '_avsora_actor_seo_description', true ),
            'canonical'      => (string) get_term_meta( $term_id, '_avsora_actor_canonical', true ),
            'robots'         => (string) get_term_meta( $term_id, '_avsora_actor_robots', true ),
            'og_title'       => (string) get_term_meta( $term_id, '_avsora_actor_og_title', true ),
            'og_description' => (string) get_term_meta( $term_id, '_avsora_actor_og_description', true ),
            'og_image'       => (string) get_term_meta( $term_id, '_avsora_actor_og_image', true ),
        );
    }

    public function save_settings() {
        if ( empty( $_POST['avsora_actor_seo_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( 'avsora_actor_seo_save' );
        $robots = isset( $_POST['actor_robots_default'] ) ? $this->sanitize_robots( wp_unslash( $_POST['actor_robots_default'] ) ) : 'index,follow';
        list( $index, $follow ) = array_pad( explode( ',', $robots ), 2, 'follow' );
        WPS_Control_Center_42::update(
            array(
                'actor_taxonomy'             => sanitize_key( wp_unslash( $_POST['actor_taxonomy'] ?? 'actors' ) ),
                'actor_index'                => $index,
                'actor_follow'               => $follow,
                'actor_title_template'       => sanitize_text_field( wp_unslash( $_POST['actor_title_template'] ?? '' ) ),
                'actor_description_template' => sanitize_text_field( wp_unslash( $_POST['actor_description_template'] ?? '' ) ),
                'actor_og_title_template'    => sanitize_text_field( wp_unslash( $_POST['actor_og_title_template'] ?? '' ) ),
                'actor_og_desc_template'     => sanitize_text_field( wp_unslash( $_POST['actor_og_desc_template'] ?? '' ) ),
            )
        );
        wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-actor-seo', array( 'avsora_notice' => 'saved' ) ) );
        exit;
    }

    public function page() {
        $o = WPS_Control_Center_42::options();
        ?>
        <div class="wrap avsora-cc-wrap"><h1>SEO นักแสดง</h1><?php WPS_Control_Center_42::instance()->notice(); ?>
            <div class="avsora-card">
                <form method="post">
                    <?php wp_nonce_field( 'avsora_actor_seo_save' ); ?>
                    <input type="hidden" name="avsora_actor_seo_action" value="save" />
                    <table class="form-table">
                        <tr><th><label for="actor_taxonomy">Taxonomy นักแสดง</label></th><td><input class="regular-text code" id="actor_taxonomy" name="actor_taxonomy" value="<?php echo esc_attr( $o['actor_taxonomy'] ); ?>" /><p class="description">ค่าเดิมของธีมคือ actors</p></td></tr>
                        <tr><th><label for="actor_title_template">SEO Title เริ่มต้น</label></th><td><input class="large-text" id="actor_title_template" name="actor_title_template" value="<?php echo esc_attr( $o['actor_title_template'] ); ?>" /></td></tr>
                        <tr><th><label for="actor_description_template">Meta Description เริ่มต้น</label></th><td><textarea class="large-text" rows="4" id="actor_description_template" name="actor_description_template"><?php echo esc_textarea( $o['actor_description_template'] ); ?></textarea></td></tr>
                        <tr><th><label for="actor_og_title_template">OG Title เริ่มต้น</label></th><td><input class="large-text" id="actor_og_title_template" name="actor_og_title_template" value="<?php echo esc_attr( $o['actor_og_title_template'] ); ?>" /></td></tr>
                        <tr><th><label for="actor_og_desc_template">OG Description เริ่มต้น</label></th><td><textarea class="large-text" rows="3" id="actor_og_desc_template" name="actor_og_desc_template"><?php echo esc_textarea( $o['actor_og_desc_template'] ); ?></textarea></td></tr>
                        <tr><th><label for="actor_robots_default">Robots เริ่มต้น</label></th><td><?php $this->robots_select( $o['actor_index'] . ',' . $o['actor_follow'], 'actor_robots_default' ); ?></td></tr>
                    </table>
                    <p class="description">ตัวแปร: %%actor%%, %%sitename%%, %%count%%, %%page%%</p>
                    <?php submit_button( 'บันทึก SEO นักแสดง' ); ?>
                </form>
            </div>
            <div class="avsora-card"><p>สามารถกำหนด SEO รายคนได้ที่หน้าแก้ไขคำศัพท์ของ Taxonomy นักแสดง โดยค่ารายคนจะมีลำดับสูงกว่าค่าเริ่มต้นในหน้านี้</p></div>
        </div>
        <?php
    }

    public function customizer( $wp_customize ) {
        $wp_customize->add_section( 'avsora_actor_seo', array( 'title' => 'AV Framework: SEO นักแสดง', 'priority' => 166 ) );
        $fields = array(
            'actor_title_template'       => array( 'SEO Title นักแสดง', 'text' ),
            'actor_description_template' => array( 'Meta Description นักแสดง', 'textarea' ),
            'actor_og_title_template'    => array( 'OG Title นักแสดง', 'text' ),
            'actor_og_desc_template'     => array( 'OG Description นักแสดง', 'textarea' ),
        );
        foreach ( $fields as $key => $field ) {
            $setting = 'avsora_cc_' . $key;
            $wp_customize->add_setting(
                $setting,
                array(
                    'default'           => WPS_Control_Center_42::get( $key ),
                    'sanitize_callback' => 'sanitize_text_field',
                    'type'              => 'option',
                )
            );
            $wp_customize->add_control( $setting, array( 'section' => 'avsora_actor_seo', 'label' => $field[0], 'type' => $field[1] ) );
        }
        add_action( 'customize_save_after', array( $this, 'sync_customizer_options' ) );
    }

    public function sync_customizer_options() {
        $updates = array();
        foreach ( array( 'actor_title_template', 'actor_description_template', 'actor_og_title_template', 'actor_og_desc_template' ) as $key ) {
            $value = get_option( 'avsora_cc_' . $key, null );
            if ( null !== $value ) {
                $updates[ $key ] = sanitize_text_field( $value );
                delete_option( 'avsora_cc_' . $key );
            }
        }
        if ( $updates ) {
            WPS_Control_Center_42::update( $updates );
        }
    }

    private function current_term() {
        if ( ! is_tax( $this->taxonomy() ) ) {
            return null;
        }
        $term = get_queried_object();
        return ( $term instanceof WP_Term ) ? $term : null;
    }

    private function replace( $template, WP_Term $term ) {
        $site_name = get_bloginfo( 'name' );
        $page = max( 1, (int) get_query_var( 'paged' ) );
        $replacements = array(
            '%%actor%%'    => $term->name,
            '%%sitename%%' => $site_name,
            '%%count%%'    => number_format_i18n( $term->count ),
            '%%page%%'     => $page > 1 ? sprintf( 'หน้า %d', $page ) : '',
        );
        return trim( strtr( (string) $template, $replacements ) );
    }

    private function values_for_current() {
        $term = $this->current_term();
        if ( ! $term ) {
            return null;
        }
        $meta = $this->term_values( $term->term_id );
        $robots = $meta['robots'] ?: WPS_Control_Center_42::get( 'actor_index', 'index' ) . ',' . WPS_Control_Center_42::get( 'actor_follow', 'follow' );
        $link = get_term_link( $term );
        return array(
            'term'        => $term,
            'title'       => $this->replace( $meta['title'] ?: WPS_Control_Center_42::get( 'actor_title_template' ), $term ),
            'description' => $this->replace( $meta['description'] ?: WPS_Control_Center_42::get( 'actor_description_template' ), $term ),
            'canonical'   => $meta['canonical'] ?: ( is_wp_error( $link ) ? '' : $link ),
            'robots'      => $robots,
            'og_title'    => $this->replace( $meta['og_title'] ?: WPS_Control_Center_42::get( 'actor_og_title_template' ), $term ),
            'og_desc'     => $this->replace( $meta['og_description'] ?: WPS_Control_Center_42::get( 'actor_og_desc_template' ), $term ),
            'og_image'    => $meta['og_image'],
        );
    }

    public function document_title( $title ) {
        $v = $this->values_for_current();
        return $v ? $v['title'] : $title;
    }
    public function yoast_title( $title ) { return $this->document_title( $title ); }
    public function yoast_description( $description ) { $v = $this->values_for_current(); return $v ? $v['description'] : $description; }
    public function yoast_canonical( $canonical ) { $v = $this->values_for_current(); return $v && $v['canonical'] ? $v['canonical'] : $canonical; }
    public function yoast_robots( $robots ) { $v = $this->values_for_current(); return $v ? $v['robots'] : $robots; }
    public function yoast_robots_array( $robots ) {
        $v = $this->values_for_current();
        if ( ! $v || ! is_array( $robots ) ) { return $robots; }
        $parts = array_pad( explode( ',', $v['robots'] ), 2, 'follow' );
        $robots['index'] = $parts[0];
        $robots['follow'] = $parts[1];
        return $robots;
    }
    public function yoast_og_title( $title ) { $v = $this->values_for_current(); return $v ? $v['og_title'] : $title; }
    public function yoast_og_description( $description ) { $v = $this->values_for_current(); return $v ? $v['og_desc'] : $description; }
    public function yoast_og_image( $image ) { $v = $this->values_for_current(); return $v && $v['og_image'] ? $v['og_image'] : $image; }

    public function fallback_meta() {
        $v = $this->values_for_current();
        if ( ! $v || defined( 'WPSEO_VERSION' ) || $this->fallback_printed ) {
            return;
        }
        $this->fallback_printed = true;
        echo "\n<!-- AV Framework Actor SEO -->\n";
        if ( $v['description'] ) {
            printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $v['description'] ) );
        }
        if ( $v['canonical'] ) {
            printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $v['canonical'] ) );
        }
        printf( '<meta name="robots" content="%s" />' . "\n", esc_attr( $v['robots'] ) );
        printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $v['og_title'] ) );
        printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $v['og_desc'] ) );
        if ( $v['og_image'] ) {
            printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $v['og_image'] ) );
        }
        echo "<!-- /AV Framework Actor SEO -->\n";
    }
}
