<?php
/**
 * Template Name: Custom Video Grid (New)
 * 
 * คัดลอกโครงสร้างคลาสเดิมจาก KolorTube index.php
 * เปลี่ยนชื่อเทมเพลต แต่ใช้ class names เดิมทั้งหมด
 * ไม่สร้าง meta ใหม่ - ใช้ get_header() และ get_footer() เดิม
 * 
 * วิธีใช้: สร้างหน้าใหม่ใน WordPress → เลือก Template นี้
 * 
 * @package kolortube
 */
defined( 'ABSPATH' ) || exit;

// ใช้ header เดิม = meta tags, wp_head(), scripts ทุกอย่างยังคงเดิม
// ไม่มี meta ใหม่เพิ่มเติม
get_header();
?>

<?php
// ดึงค่า settings เดิมจากธีม (ไม่สร้าง option ใหม่)
$sidebar_pos    = get_theme_mod( 'sidebar_position', 'left' );
$mobile_columns = get_theme_mod( 'mobile_columns', '2' );
$show_duration  = get_theme_mod( 'video_listing_general_show_duration', 'yes' );
$show_title     = get_theme_mod( 'video_listing_general_show_title', 'yes' );
$col_mobile     = 'col-' . intval( 12 / intval( $mobile_columns ) );
?>

<div id="content">
    
    <!-- Hero Section - ใช้ class เดิม: hero, home-seo-block, container, row, hero-text -->
    <section class="hero home-seo-block" aria-labelledby="custom-grid-title">
        <div class="container" tabindex="-1">
            <div class="row hero-text">
                <div class="col-12 col-md-8 mx-auto">
                    <h1 id="custom-grid-title">หน้าแสดงวิดีโอแบบกำหนดเอง</h1>
                    <p class="hero-desc">
                        ใช้โครงสร้างคลาสเดิมจากธีม KolorTube ทุกประการ
                        แต่เป็นเทมเพลตใหม่ที่สร้างขึ้นเอง ชื่อใหม่ ไม่ทับไฟล์เดิม
                        ระบบ meta, scripts, styles ยังคงเหมือนเดิมทั้งหมด
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Container - ใช้ class เดิม: container, container-lg, d-flex, flex-wrap -->
    <div class="container container-lg p-0 <?php echo 'none' !== $sidebar_pos ? 'd-flex flex-wrap' : ''; ?>">
        
        <?php if ( 'left' === $sidebar_pos ) : ?>
            <?php get_sidebar(); // ใช้ sidebar เดิมทั้งหมด ?>
        <?php endif; ?>

        <!-- Video Loop - ใช้ class เดิม: video-loop -->
        <div class="video-loop" <?php echo 'none' !== $sidebar_pos ? 'style="flex: 1;"' : ''; ?>>
            <div class="row no-gutters">
                <div class="col-12">                    
                    <div class="row no-gutters">
                        
                        <?php
                        // Query วิดีโอ (สามารถปรับเปลี่ยนเงื่อนไขได้ตามต้องการ)
                        // ไม่สร้าง meta ใหม่ ใช้ข้อมูลเดิมจากฐานข้อมูล
                        $args = array(
                            'post_type'      => 'post',
                            'post_status'    => 'publish',
                            'posts_per_page' => 24,
                            'orderby'        => 'date',
                            'order'          => 'DESC',
                            'no_found_rows'  => true,
                        );
                        
                        $custom_query = new WP_Query( $args );
                        
                        if ( $custom_query->have_posts() ) :
                            while ( $custom_query->have_posts() ) :
                                $custom_query->the_post();
                                
                                // เรียกใช้ loop template ใหม่ที่สร้างเอง
                                // ใช้ class names เดิมทั้งหมดภายในไฟล์นี้
                                get_template_part( 'loop-templates/content-custom-video' );
                                
                            endwhile;
                            wp_reset_postdata();
                        else :
                            // ไม่มีข้อมูล - ใช้ template เดิมจากธีม
                            get_template_part( 'loop-templates/content', 'none' );
                        endif;
                        ?>
                        
                    </div>
                </div>
            </div>
            
            <!-- Pagination - ใช้ฟังก์ชันเดิมจากธีม -->
            <?php 
            if ( function_exists( 'wpst_pagination' ) ) {
                wpst_pagination(); 
            }
            ?>
            
        </div><!-- .video-loop -->
        
        <?php if ( 'right' === $sidebar_pos ) : ?>
            <?php get_sidebar(); // ใช้ sidebar เดิมทั้งหมด ?>
        <?php endif; ?>
        
    </div><!-- .container.container-lg -->

</div><!-- #content -->

<?php
// ใช้ footer เดิม = wp_footer(), scripts, lazyload ทุกอย่างยังคงเดิม
get_footer();
?>
