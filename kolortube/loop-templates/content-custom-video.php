<?php
/**
 * Template Part: Custom Video Card
 * 
 * ใช้ class names เดิมจาก KolorTube loop-templates/loop-video.php ทั้งหมด
 * แต่เป็นไฟล์ใหม่ ชื่อใหม่ ไม่ทับไฟล์เดิม
 * 
 * Class names ที่ใช้ (เดิมทั้งหมด):
 * - video-block, thumbs-rotation
 * - thumb
 * - video-img, img-fluid, loaded
 * - infos
 * - title
 * - video-datas
 * - views-number
 * - duration
 * 
 * ฟังก์ชันที่ใช้ (เดิมทั้งหมดจากธีม):
 * - wps_get_post_best_image_url()
 * - wpst_get_video_duration()
 * - wpst_getPostViews()
 * 
 * @package kolortube
 */
defined( 'ABSPATH' ) || exit;

// ดึงค่า settings เดิมจากธีม (ไม่สร้าง option ใหม่)
$sidebar_pos    = get_theme_mod( 'sidebar_position', 'left' );
$mobile_columns = get_theme_mod( 'mobile_columns', '2' );
$show_duration  = get_theme_mod( 'video_listing_general_show_duration', 'yes' );
$col_mobile     = 'col-' . intval( 12 / intval( $mobile_columns ) );

$video_id = get_the_ID();

// ใช้ฟังก์ชันเดิมจากธีมเพื่อดึงรูปภาพ (ไม่สร้าง meta ใหม่)
$video_thumb_url = '';
if ( function_exists( 'wps_get_post_best_image_url' ) ) {
    $video_thumb_url = wps_get_post_best_image_url( $video_id, 'video-thumb' );
}
if ( ! $video_thumb_url && has_post_thumbnail() ) {
    $video_thumb_url = get_the_post_thumbnail_url( $video_id, 'video-thumb' );
}
$video_thumb_fallback = get_template_directory_uri() . '/img/no-thumb.png';

// ใช้ฟังก์ชันเดิมจากธีมเพื่อดึงระยะเวลา (ไม่สร้าง meta ใหม่)
$video_duration = '';
if ( function_exists( 'wpst_get_video_duration' ) ) {
    $video_duration = wpst_get_video_duration( $video_id );
}

// ใช้ฟังก์ชันเดิมจากธีมเพื่อดึงจำนวน views (ไม่สร้าง meta ใหม่)
$video_views = 0;
if ( function_exists( 'wpst_getPostViews' ) ) {
    $video_views = intval( wpst_getPostViews( $video_id ) );
}
?>

<!-- ============================================================
     โครงสร้าง HTML + Class names: COPIED from original loop-video.php
     ทุก class คือของเดิม CSS จะทำงานเหมือนเดิม 100%
     ============================================================ -->
<div class="<?php echo esc_html( $col_mobile ); ?> <?php echo 'none' !== $sidebar_pos ? 'col-md-4' : 'col-md-3'; ?> col-lg-3 col-xl-2">
    
    <!-- video-block + thumbs-rotation = class เดิม -->
    <div class="video-block thumbs-rotation" data-post-id="<?php echo intval( $video_id ); ?>">
        
        <!-- thumb = class เดิม -->
        <a class="thumb" href="<?php the_permalink(); ?>">
            <?php if ( $video_thumb_url ) : ?>
                <!-- video-img, img-fluid, loaded = class เดิม -->
                <img class="video-img img-fluid loaded" 
                     src="<?php echo esc_url( $video_thumb_url ); ?>" 
                     alt="<?php echo esc_attr( get_the_title() ); ?>" 
                     loading="lazy" 
                     decoding="async"
                     onerror="this.onerror=null;this.src='<?php echo esc_url( $video_thumb_fallback ); ?>';">
            <?php else : ?>
                <img class="video-img img-fluid loaded" 
                     src="<?php echo esc_url( $video_thumb_fallback ); ?>" 
                     alt="<?php echo esc_attr( get_the_title() ); ?>" 
                     loading="lazy" 
                     decoding="async">
            <?php endif; ?>
        </a>
        
        <!-- infos = class เดิม -->
        <a class="infos" href="<?php the_permalink(); ?>" title="<?php echo esc_attr( get_the_title() ); ?>">
            
            <!-- title = class เดิม -->
            <span class="title"><?php the_title(); ?></span>
            
            <!-- video-datas = class เดิม -->
            <div class="video-datas">
                
                <!-- views-number = class เดิม -->
                <span class="views-number">
                    <i class="fa fa-eye"></i> 
                    <?php echo $video_views; ?>
                </span>
                
                <?php if ( 'yes' === $show_duration && '' !== $video_duration ) : ?>
                    <!-- duration = class เดิม -->
                    <span class="duration"><?php echo esc_html( $video_duration ); ?></span>
                <?php endif; ?>
                
            </div><!-- .video-datas -->
            
        </a><!-- .infos -->
        
    </div><!-- .video-block -->
    
</div><!-- column wrapper -->
