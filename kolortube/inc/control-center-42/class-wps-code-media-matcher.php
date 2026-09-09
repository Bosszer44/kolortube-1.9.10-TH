<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WPS_Code_Media_Matcher {
    private static $instance = null;
    const REPORT_OPTION = 'wps_42_last_media_report';
    const MARKER_START = '<!-- WPS 4.2 AUTO GALLERY START -->';
    const MARKER_END   = '<!-- WPS 4.2 AUTO GALLERY END -->';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
        add_action( 'admin_init', array( $this, 'handle' ) );
    }

    public function admin_menu() {
        add_submenu_page( 'av-control-center', 'จับคู่ภาพตาม CODE', 'จับคู่ภาพตาม CODE', 'manage_options', 'wps-code-media', array( $this, 'page' ) );
    }

    public function handle() {
        if ( empty( $_POST['avsora_media_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( 'avsora_media_matcher' );
        $action = sanitize_key( wp_unslash( $_POST['avsora_media_action'] ) );
        if ( 'save_settings' === $action ) {
            WPS_Control_Center_42::update(
                array(
                    'media_post_type'          => post_type_exists( sanitize_key( wp_unslash( $_POST['media_post_type'] ?? 'post' ) ) ) ? sanitize_key( wp_unslash( $_POST['media_post_type'] ) ) : 'post',
                    'media_overwrite_featured' => WPS_Control_Center_42::bool_post( 'media_overwrite_featured' ),
                    'media_gallery_mode'       => in_array( ( $_POST['media_gallery_mode'] ?? 'content' ), array( 'content', 'shortcode', 'meta' ), true ) ? sanitize_key( $_POST['media_gallery_mode'] ) : 'content',
                    'media_overwrite_gallery'  => WPS_Control_Center_42::bool_post( 'media_overwrite_gallery' ),
                )
            );
            wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-code-media', array( 'avsora_notice' => 'saved' ) ) );
            exit;
        }
        if ( 'rollback' === $action ) {
            $this->rollback_last();
            wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-code-media', array( 'media_notice' => 'rollback' ) ) );
            exit;
        }
        $mode = 'run' === $action ? 'run' : 'dry-run';
        $limit = max( 1, min( 1000, absint( $_POST['limit'] ?? 200 ) ) );
        $offset = max( 0, absint( $_POST['offset'] ?? 0 ) );
        $report = $this->process( $mode, $limit, $offset );
        update_option( self::REPORT_OPTION, $report, false );
        wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-code-media', array( 'media_notice' => $mode ) ) );
        exit;
    }

    private function attachment_index() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT p.ID, pm.meta_value AS attached_file
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'",
            ARRAY_A
        );
        $index = array();
        $duplicates = array();
        foreach ( $rows as $row ) {
            $file = (string) $row['attached_file'];
            $extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
            if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif' ), true ) ) {
                continue;
            }
            $stem = pathinfo( basename( $file ), PATHINFO_FILENAME );
            if ( preg_match( '/-(?:\d+x\d+|scaled|rotated)$/i', $stem ) ) {
                continue;
            }
            $key = strtolower( trim( $stem ) );
            if ( isset( $index[ $key ] ) ) {
                $duplicates[ $key ][] = (int) $index[ $key ];
                $duplicates[ $key ][] = (int) $row['ID'];
                $index[ $key ] = max( (int) $index[ $key ], (int) $row['ID'] );
            } else {
                $index[ $key ] = (int) $row['ID'];
            }
        }
        foreach ( $duplicates as $key => $ids ) {
            $duplicates[ $key ] = array_values( array_unique( $ids ) );
        }
        return array( $index, $duplicates );
    }

    private function code_for_post( WP_Post $post ) {
        foreach ( array( '_av_code', 'av_code', 'video_code', '_video_code', 'code' ) as $key ) {
            $value = get_post_meta( $post->ID, $key, true );
            if ( $value && preg_match( '/\b([A-Z0-9]{2,15}(?:-[A-Z0-9]{2,15})?-\d{2,8}[A-Z]?)\b/i', (string) $value, $match ) ) {
                return strtoupper( $match[1] );
            }
        }
        foreach ( array( $post->post_title, $post->post_name ) as $value ) {
            if ( preg_match( '/\b([A-Z0-9]{2,15}(?:-[A-Z0-9]{2,15})?-\d{2,8}[A-Z]?)\b/i', (string) $value, $match ) ) {
                return strtoupper( $match[1] );
            }
        }
        return '';
    }

    public function process( $mode, $limit, $offset ) {
        list( $index, $duplicates ) = $this->attachment_index();
        $posts = get_posts(
            array(
                'post_type'      => WPS_Control_Center_42::get( 'media_post_type', 'post' ),
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => $limit,
                'offset'         => $offset,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            )
        );
        $report = array(
            'generated_at' => current_time( 'mysql' ),
            'mode'         => $mode,
            'limit'        => $limit,
            'offset'       => $offset,
            'duplicates'   => $duplicates,
            'items'        => array(),
            'changed_ids'  => array(),
            'summary'      => array( 'posts' => 0, 'codes' => 0, 'featured_found' => 0, 'gallery_found' => 0, 'changed' => 0, 'skipped' => 0 ),
        );
        foreach ( $posts as $post ) {
            $report['summary']['posts']++;
            $code = $this->code_for_post( $post );
            if ( ! $code ) {
                $report['summary']['skipped']++;
                $report['items'][] = array( 'post_id' => $post->ID, 'title' => $post->post_title, 'code' => '', 'status' => 'ไม่พบ CODE', 'featured' => 0, 'gallery' => array() );
                continue;
            }
            $report['summary']['codes']++;
            $base_key = strtolower( $code );
            $featured = isset( $index[ $base_key ] ) ? (int) $index[ $base_key ] : 0;
            $gallery = array();
            for ( $i = 1; $i <= 6; $i++ ) {
                $key = $base_key . '-' . $i;
                if ( isset( $index[ $key ] ) ) {
                    $gallery[ $i ] = (int) $index[ $key ];
                }
            }
            if ( $featured ) { $report['summary']['featured_found']++; }
            $report['summary']['gallery_found'] += count( $gallery );
            $status = array();
            $changed = false;
            if ( 'run' === $mode ) {
                $old_thumb = (int) get_post_thumbnail_id( $post->ID );
                $old_gallery_ids = get_post_meta( $post->ID, '_wps_42_gallery_ids', true );
                $backup = array(
                    'time'        => time(),
                    'thumbnail'   => $old_thumb,
                    'content'     => $post->post_content,
                    'gallery_ids' => $old_gallery_ids,
                );
                update_post_meta( $post->ID, '_wps_42_media_backup', wp_json_encode( $backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
                if ( $featured ) {
                    if ( ! $old_thumb || WPS_Control_Center_42::get( 'media_overwrite_featured' ) ) {
                        set_post_thumbnail( $post->ID, $featured );
                        $this->parent_attachment( $featured, $post->ID );
                        $status[] = 'ตั้งภาพปก';
                        $changed = true;
                    } else {
                        $status[] = 'มีภาพปกเดิม—ไม่ทับ';
                    }
                } else {
                    $status[] = 'ไม่พบ CODE.ext';
                }
                if ( $gallery ) {
                    $gallery_ids = array_values( $gallery );
                    $existing = get_post_meta( $post->ID, '_wps_42_gallery_ids', true );
                    $has_manual_gallery = false === strpos( (string) $post->post_content, self::MARKER_START ) && ( false !== strpos( (string) $post->post_content, 'wp-block-gallery' ) || false !== stripos( (string) $post->post_content, '[gallery' ) );
                    if ( ( empty( $existing ) && ! $has_manual_gallery ) || WPS_Control_Center_42::get( 'media_overwrite_gallery' ) ) {
                        update_post_meta( $post->ID, '_wps_42_gallery_ids', $gallery_ids );
                        foreach ( $gallery as $number => $attachment_id ) {
                            $this->parent_attachment( $attachment_id, $post->ID );
                            if ( ! get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) {
                                update_post_meta( $attachment_id, '_wp_attachment_image_alt', sprintf( '%s รูปที่ %d', $code, $number ) );
                            }
                        }
                        $this->write_gallery( $post, $gallery_ids );
                        $status[] = 'ใส่รูปใต้โพสต์ ' . count( $gallery_ids ) . ' รูป';
                        $changed = true;
                    } else {
                        $status[] = $has_manual_gallery ? 'พบ Gallery เดิมในเนื้อหา—ไม่ทับ' : 'มีชุด Gallery เดิม—ไม่ทับ';
                    }
                }
                if ( $changed ) {
                    $report['summary']['changed']++;
                    $report['changed_ids'][] = $post->ID;
                }
            } else {
                $status[] = $featured ? 'พร้อมจับคู่ภาพปก' : 'ไม่พบภาพปกตรง CODE';
                $status[] = $gallery ? 'พบรูปใต้โพสต์ ' . count( $gallery ) . ' รูป' : 'ไม่พบ CODE-1..6';
            }
            $report['items'][] = array( 'post_id' => $post->ID, 'title' => $post->post_title, 'code' => $code, 'status' => implode( ' / ', $status ), 'featured' => $featured, 'gallery' => $gallery );
        }
        return $report;
    }

    private function parent_attachment( $attachment_id, $post_id ) {
        $attachment = get_post( $attachment_id );
        if ( $attachment && ( 0 === (int) $attachment->post_parent || (int) $attachment->post_parent === (int) $post_id ) ) {
            wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $post_id ) );
        }
    }

    private function write_gallery( WP_Post $post, array $ids ) {
        $mode = WPS_Control_Center_42::get( 'media_gallery_mode', 'content' );
        if ( 'meta' === $mode ) {
            return;
        }
        $block = 'shortcode' === $mode ? '[gallery ids="' . implode( ',', array_map( 'absint', $ids ) ) . '"]' : $this->gallery_block( $ids );
        $payload = self::MARKER_START . "\n" . $block . "\n" . self::MARKER_END;
        $content = (string) $post->post_content;
        $pattern = '/' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '/s';
        if ( preg_match( $pattern, $content ) ) {
            $content = preg_replace( $pattern, $payload, $content, 1 );
        } else {
            $content = rtrim( $content ) . "\n\n" . $payload;
        }
        wp_update_post( array( 'ID' => $post->ID, 'post_content' => $content ) );
    }

    private function gallery_block( array $ids ) {
        $inner = '';
        foreach ( $ids as $id ) {
            $url = wp_get_attachment_image_url( $id, 'large' );
            if ( ! $url ) { continue; }
            $alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
            $inner .= '<!-- wp:image {"id":' . absint( $id ) . ',"sizeSlug":"large","linkDestination":"none"} -->' . "\n";
            $inner .= '<figure class="wp-block-image size-large"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . absint( $id ) . '"/></figure>' . "\n";
            $inner .= '<!-- /wp:image -->' . "\n";
        }
        return '<!-- wp:gallery {"columns":2,"linkTo":"none"} -->' . "\n" . '<figure class="wp-block-gallery has-nested-images columns-2 is-cropped">' . "\n" . $inner . '</figure>' . "\n" . '<!-- /wp:gallery -->';
    }

    private function rollback_last() {
        $report = get_option( self::REPORT_OPTION, array() );
        foreach ( (array) ( $report['changed_ids'] ?? array() ) as $post_id ) {
            $raw = get_post_meta( $post_id, '_wps_42_media_backup', true );
            $backup = json_decode( (string) $raw, true );
            if ( ! is_array( $backup ) ) { continue; }
            if ( empty( $backup['thumbnail'] ) ) { delete_post_thumbnail( $post_id ); } else { set_post_thumbnail( $post_id, absint( $backup['thumbnail'] ) ); }
            wp_update_post( array( 'ID' => $post_id, 'post_content' => (string) $backup['content'] ) );
            if ( empty( $backup['gallery_ids'] ) ) { delete_post_meta( $post_id, '_wps_42_gallery_ids' ); } else { update_post_meta( $post_id, '_wps_42_gallery_ids', $backup['gallery_ids'] ); }
        }
    }

    public function page() {
        $o = WPS_Control_Center_42::options();
        $report = get_option( self::REPORT_OPTION, array() );
        ?>
        <div class="wrap avsora-cc-wrap"><h1>จับคู่ภาพตาม CODE</h1><?php WPS_Control_Center_42::instance()->notice(); ?>
            <?php if ( ! empty( $_GET['media_notice'] ) ) : ?><div class="notice notice-success is-dismissible"><p>งานเสร็จ: <?php echo esc_html( sanitize_key( $_GET['media_notice'] ) ); ?></p></div><?php endif; ?>
            <div class="avsora-card"><p><strong>กฎล็อก:</strong> <code>CODE.jpg/jpeg/png/webp/avif/gif</code> = ภาพปก และ <code>CODE-1</code> ถึง <code>CODE-6</code> = รูปใต้โพสต์ ไม่ดึงไฟล์ย่อ <code>-300x200</code> และไม่ใช้ <code>CODE-1</code> แทนภาพปก</p></div>
            <div class="avsora-grid">
                <div class="avsora-card"><h2>ตั้งค่า</h2><form method="post"><?php wp_nonce_field( 'avsora_media_matcher' ); ?><input type="hidden" name="avsora_media_action" value="save_settings" />
                    <p><label>Post type <input class="regular-text code" name="media_post_type" value="<?php echo esc_attr( $o['media_post_type'] ); ?>"></label></p>
                    <p><label><input type="checkbox" name="media_overwrite_featured" value="1" <?php checked( $o['media_overwrite_featured'] ); ?>> อนุญาตทับภาพปกเดิม</label></p>
                    <p><label>วิธีใส่ Gallery <select name="media_gallery_mode"><option value="content" <?php selected( $o['media_gallery_mode'], 'content' ); ?>>Gutenberg Gallery ในเนื้อหา</option><option value="shortcode" <?php selected( $o['media_gallery_mode'], 'shortcode' ); ?>>Shortcode [gallery]</option><option value="meta" <?php selected( $o['media_gallery_mode'], 'meta' ); ?>>บันทึก ID ใน Meta เท่านั้น</option></select></label></p>
                    <p><label><input type="checkbox" name="media_overwrite_gallery" value="1" <?php checked( $o['media_overwrite_gallery'] ); ?>> อนุญาตทับชุด Gallery ที่ระบบเคยบันทึก</label></p>
                    <?php submit_button( 'บันทึกตั้งค่า', 'secondary', 'submit', false ); ?>
                </form></div>
                <div class="avsora-card"><h2>สแกน/ทำงาน</h2><form method="post"><?php wp_nonce_field( 'avsora_media_matcher' ); ?>
                    <p><label>เริ่มจากลำดับ <input type="number" min="0" name="offset" value="0"></label></p><p><label>จำนวนต่อรอบ <input type="number" min="1" max="1000" name="limit" value="200"></label></p>
                    <button class="button" name="avsora_media_action" value="dry-run">Dry Run ไม่แก้ข้อมูล</button>
                    <button class="button button-primary avsora-confirm-button" data-confirm="ยืนยันจับคู่ภาพจริง? ระบบจะสำรองค่าเดิมรายโพสต์ก่อน" name="avsora_media_action" value="run">จับคู่จริง</button>
                </form>
                <?php if ( ! empty( $report['changed_ids'] ) ) : ?><form method="post" class="avsora-confirm-form" data-confirm="ย้อนคืนโพสต์ในรอบล่าสุด?"><?php wp_nonce_field( 'avsora_media_matcher' ); ?><button class="button" name="avsora_media_action" value="rollback">Rollback รอบล่าสุด</button></form><?php endif; ?>
                </div>
            </div>
            <?php if ( $report ) : ?>
            <div class="avsora-card"><h2>รายงานล่าสุด — <?php echo esc_html( $report['mode'] ?? '' ); ?></h2>
                <?php $s = $report['summary'] ?? array(); ?><p>โพสต์ <?php echo absint( $s['posts'] ?? 0 ); ?> | พบ CODE <?php echo absint( $s['codes'] ?? 0 ); ?> | พบภาพปก <?php echo absint( $s['featured_found'] ?? 0 ); ?> | รูป Gallery <?php echo absint( $s['gallery_found'] ?? 0 ); ?> | แก้จริง <?php echo absint( $s['changed'] ?? 0 ); ?></p>
                <div class="avsora-table-scroll"><table class="widefat striped"><thead><tr><th>Post ID</th><th>CODE</th><th>ภาพปก ID</th><th>Gallery IDs</th><th>ผล</th></tr></thead><tbody>
                <?php foreach ( array_slice( (array) ( $report['items'] ?? array() ), 0, 500 ) as $item ) : ?><tr><td><?php echo absint( $item['post_id'] ); ?></td><td><code><?php echo esc_html( $item['code'] ); ?></code></td><td><?php echo absint( $item['featured'] ); ?></td><td><?php echo esc_html( implode( ',', array_map( 'absint', (array) $item['gallery'] ) ) ); ?></td><td><?php echo esc_html( $item['status'] ); ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
                <?php if ( ! empty( $report['duplicates'] ) ) : ?><details><summary>พบชื่อไฟล์ซ้ำแบบตรงตัว <?php echo count( $report['duplicates'] ); ?> รายการ</summary><pre><?php echo esc_html( wp_json_encode( $report['duplicates'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre></details><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
