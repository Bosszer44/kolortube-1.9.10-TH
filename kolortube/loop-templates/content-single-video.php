<?php
/**
 * Template Part: Single Video Content
 * ✅ อัปเดตล่าสุด:
 * - 3 ปุ่มแคปซูลเรียงกันใต้โปสเตอร์: ถูกใจ / บันทึก / แชร์
 * - ปุ่มไลค์มีแค่หัวใจอย่างเดียว ไม่มีดิสไลค์
 * - ย้าย Views ไปไว้ใต้รหัสอ้างอิงในคอลัมน์ซ้าย
 * - ✅ เพิ่มระบบ Lightbox แกลเลอรีรูปภาพครบถ้วน
 *   - กดรูปขนาดเล็ก → โผล่หน้าจอใหญ่
 *   - ปุ่มถัดไป/ก่อนหน้า
 *   - ปิดด้วยปุ่ม X, คลิกพื้นหลัง, หรือปุ่ม ESC
 *   - รองรับคีย์บอร์ด: ลูกศรซ้าย/ขวา
 *
 * @package kolortube
 */
// ============================================================
// ส่วนที่ 1: PHP ด้านบน — ประกาศตัวแปร, Filters, โหลดโฆษณา
// ============================================================
add_filter(
	'theme_mod_seo_video_title',
	function ( $value ) {
		$video_title = get_the_title();
		$value       = str_replace( '%%video_title%%', $video_title, $value );
		return $value;
	}
);
add_filter(
	'theme_mod_seo_video_tracking_button',
	function ( $value ) {
		$video_title = get_the_title();
		$value       = str_replace( '%%video_title%%', $video_title, $value );
		return $value;
	}
);
// 📌 ดึงข้อมูลหลักของวิดีโอ
$video_title        = get_the_title();
$video_tracking_url = get_post_meta( $post->ID, 'tracking_url', true );
$video_thumb_url    = wpst_get_video_thumb_url( get_the_ID(), 'video-thumb', false );
// 🖼️ ดึงรูปโปสเตอร์
$wps_poster_id  = absint( get_post_meta( get_the_ID(), '_wps_poster_id', true ) );
$wps_poster_url = '';
if ( $wps_poster_id ) {
	$wps_poster_url = wp_get_attachment_image_url( $wps_poster_id, 'large' );
}
if ( ! $wps_poster_url ) {
	$wps_poster_url = $video_thumb_url;
}
$wps_poster_alt = $video_title;
// 👍 ตรวจสอบว่าแสดงปุ่ม Like หรือไม่
$wps_show_likes = ! class_exists( 'WPS_Theme_Finalizer' ) || WPS_Theme_Finalizer::single_show_likes();
// 📢 โหลดตำแหน่งโฆษณาของธีม
$ads = array(
	'in_player_1'              => WPS_Ads::html( 'ads_single_video_page_in_player_1' ),
	'in_player_2'              => WPS_Ads::html( 'ads_single_video_page_in_player_2' ),
	'under_player'              => WPS_Ads::html( 'ads_single_video_page_under_player' ),
	'before_related_videos_ads' => WPS_Ads::html( 'ads_single_video_page_before_related_videos' ),
	'beside_player_1'           => WPS_Ads::html( 'ads_single_video_page_beside_player_1' ),
	'beside_player_2'           => WPS_Ads::html( 'ads_single_video_page_beside_player_2' ),
	'static_top'                => WPS_Ads::html( 'ads_single_static_top' ),
	'static_bottom_1'           => WPS_Ads::html( 'ads_single_static_bottom_1' ),
	'static_bottom_2'           => WPS_Ads::html( 'ads_single_static_bottom_2' ),
	'static_bottom_3'           => WPS_Ads::html( 'ads_single_static_bottom_3' ),
	'static_bottom_4'           => WPS_Ads::html( 'ads_single_static_bottom_4' ),
);
$has_wpst_in_player_ad = '' !== trim( $ads['in_player_1'] . $ads['in_player_2'] );
$has_wpst_beside_player_ad_zone_desktop = '' !== trim( $ads['beside_player_1'] . $ads['beside_player_2'] );
// 🏷️ ดึงหมวดหมู่แรก สำหรับ "รหัสอ้างอิง"
$post_categories = get_the_category();
$reference_code  = '';
if ( ! empty( $post_categories ) && ! is_wp_error( $post_categories ) ) {
	$first_category = reset( $post_categories );
	$reference_code = $first_category->name;
}
// 👁️ จำนวน Views
$views_count = intval( wpst_getPostViews( get_the_ID() ) );
// 🖼️ ดึงรูปแกลเลอรี
$gallery_images = array();
$gallery_ids = get_post_meta( get_the_ID(), '_wps_42_gallery_ids', true );
if ( ! $gallery_ids ) {
	$gallery_ids = get_post_meta( get_the_ID(), 'wps_gallery_ids', true );
}
if ( $gallery_ids && is_string( $gallery_ids ) ) {
	$gallery_ids = array_filter( array_map( 'intval', explode( ',', $gallery_ids ) ) );
}
if ( is_array( $gallery_ids ) && ! empty( $gallery_ids ) ) {
	foreach ( array_slice( $gallery_ids, 0, 6 ) as $img_id ) {
		$thumb_url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
		$full_url  = wp_get_attachment_image_url( $img_id, 'large' );
		if ( ! $full_url ) {
			$full_url = wp_get_attachment_image_url( $img_id, 'full' );
		}
		if ( $thumb_url ) {
			$gallery_images[] = array(
				'thumb' => $thumb_url,
				'full'  => $full_url ? $full_url : $thumb_url,
			);
		}
	}
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?> itemprop="video" itemscope itemtype="https://schema.org/VideoObject">
	<section class="single-video-player">
		<div class="container">
			<div class="row">
				<?php if ( $has_wpst_beside_player_ad_zone_desktop ) : ?>
					<div class="col-12 col-md-9 wps-player-column">
						<div class="wps-player-stage">
							<?php get_template_part( 'loop-templates/content', 'video-player' ); ?>
							<?php if ( $has_wpst_in_player_ad ) : ?>
								<div class="happy-inside-player" role="complementary" aria-label="<?php esc_attr_e( 'Advertisement', 'wpst' ); ?>">
									<?php if ( $ads['in_player_1'] ) : ?><div class="zone-1"><?php echo $ads['in_player_1']; ?></div><?php endif; ?>
									<?php if ( $ads['in_player_2'] ) : ?><div class="zone-2"><?php echo $ads['in_player_2']; ?></div><?php endif; ?>
									<button type="button" class="close-text" aria-label="<?php esc_attr_e( 'Close advertisement', 'wpst' ); ?>">×</button>
								</div>
							<?php endif; ?>
						</div>
						<?php if ( $ads['under_player'] ) : ?>
							<div class="happy-player-under wps-player-width-banner">
								<?php echo $ads['under_player']; ?>
							</div>
						<?php endif; ?>
					</div>
					<div class="col-12 col-md-3 happy-player-beside">
						<div class="zone-1"><?php echo $ads['beside_player_1']; ?></div>
						<div class="zone-2"><?php echo $ads['beside_player_2']; ?></div>
					</div>
				<?php else : ?>
					<div class="col-12 col-md-10 mx-auto wps-player-column">
						<a href="https://westbluez.club/register/p8274/" target="_blank" rel="nofollow">
							<img loading="lazy" style="border:0" src="//avyoufin.com/wp-content/uploads/2026/05/wespro01.gif" width="100%" height="180" alt="">
						</a>
						<?php if ( $ads['static_top'] ) : ?>
							<div class="wps-single-static-ad wps-player-width-banner">
								<?php echo $ads['static_top']; ?>
							</div>
						<?php endif; ?>
						<div class="wps-player-stage">
							<?php get_template_part( 'loop-templates/content', 'video-player' ); ?>
							<?php if ( $has_wpst_in_player_ad ) : ?>
								<div class="happy-inside-player" role="complementary" aria-label="<?php esc_attr_e( 'Advertisement', 'wpst' ); ?>">
									<?php if ( $ads['in_player_1'] ) : ?><div class="zone-1"><?php echo $ads['in_player_1']; ?></div><?php endif; ?>
									<?php if ( $ads['in_player_2'] ) : ?><div class="zone-2"><?php echo $ads['in_player_2']; ?></div><?php endif; ?>
									<button type="button" class="close-text" aria-label="<?php esc_attr_e( 'Close advertisement', 'wpst' ); ?>">×</button>
								</div>
							<?php endif; ?>
						</div>
						<a href="https://orll.cc/kwp8239/" target="_blank" rel="nofollow">
							<img loading="lazy" style="border:0" src="//avyoufin.com/wp-content/uploads/2026/05/kpro01.gif" width="100%" height="180" alt="">
						</a>
						<a href="https://t.me/+3ajdE6gCZhpjOTNl" target="_blank" rel="nofollow">
							<img loading="lazy" style="border:0" src="//avyoufin.com/wp-content/uploads/2026/05/gtl00.gif" width="100%" height="180" alt="">
						</a>
						<a href="https://t.me/+qBmOvw5RP55iYjdl" target="_blank" rel="nofollow">
							<img loading="lazy" style="border:0" src="//avyoufin.com/wp-content/uploads/2026/06/JAV-ซับไทย-HD.gif" width="100%" height="180" alt="">
						</a>
						<?php if ( $ads['under_player'] ) : ?>
							<div class="happy-player-under wps-player-width-banner">
								<?php echo $ads['under_player']; ?>
							</div>
						<?php endif; ?>
						<?php if ( $ads['static_bottom_1'] ) : ?>
							<div class="wps-single-static-ad wps-player-width-banner">
								<?php echo $ads['static_bottom_1']; ?>
							</div>
						<?php endif; ?>
						<?php if ( $ads['static_bottom_2'] ) : ?>
							<div class="wps-single-static-ad wps-player-width-banner">
								<?php echo $ads['static_bottom_2']; ?>
							</div>
						<?php endif; ?>
						<?php if ( $ads['static_bottom_3'] ) : ?>
							<div class="wps-single-static-ad wps-player-width-banner">
								<?php echo $ads['static_bottom_3']; ?>
							</div>
						<?php endif; ?>
						<?php if ( $ads['static_bottom_4'] ) : ?>
							<div class="wps-single-static-ad wps-player-width-banner">
								<?php echo $ads['static_bottom_4']; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<div class="wps-single-floating-ad-scope" data-wps-single-floating-ads>
		<?php echo WPS_Ads::floating_html(); ?>
		
		<div id="floating-widget">
			<div class="promo-unit promo-left">
				<button class="widget-close" data-close="floating-widget">
					<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="10"></circle>
						<line x1="15" y1="9" x2="9" y2="15"></line>
						<line x1="9" y1="9" x2="15" y2="15"></line>
					</svg>
				</button>
				<a href="https://orll.cc/westblue50" target="_blank" rel="nofollow noopener">
					<img class="promo-media side-media" loading="lazy" decoding="async" src="https://avyoufin.com/wp-content/uploads/2026/05/FREE-50.gif" alt="promotion">
				</a>
			</div>
			<div class="promo-unit promo-right">
				<button class="widget-close" data-close="floating-widget">
					<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="10"></circle>
						<line x1="15" y1="9" x2="9" y2="15"></line>
						<line x1="9" y1="9" x2="15" y2="15"></line>
					</svg>
				</button>
				<a href="https://orll.cc/kingof50" target="_blank" rel="nofollow noopener">
					<img class="promo-media side-media" loading="lazy" decoding="async" src="http://avyoufin.com/wp-content/uploads/2026/06/FREE50king.gif" alt="utility">
				</a>
			</div>
		</div>
		<div id="promo_04" class="floating_content">
			<div class="promo-box">
				<a href="javascript:void(0)" class="promo-close" data-close="promo_04">
					<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 24 24" stroke-width="3" stroke="#ffffff" fill="none" stroke-linecap="round" stroke-linejoin="round">
						<path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
						<path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
						<path d="M10 10l4 4m0 -4l-4 4"></path>
					</svg>
				</a>
				<a href="https://orll.cc/westblue50/" target="_blank" rel="nofollow noopener">
					<img class="promo-img" src="//avyoufin.com/wp-content/uploads/2024/12/w67.gif" alt="slot เว็บตรง">
				</a>
			</div>
		</div>
		<style>
			/* FLOATING BOTTOM PROMO */
			#promo_04 { position: fixed; left: 50%; bottom: 10px; transform: translateX(-50%); z-index: 99999; text-align: center; }
			.promo-box { position: relative; display: inline-block; }
			.promo-img { display: block; max-width: 100%; width: 728px; height: auto; border-radius: 10px; }
			.promo-close { position: absolute; top: 0; right: 0; background: rgba(0,0,0,.7); width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; z-index: 10; border-radius: 0 10px 0 10px; }
			
			/* GLOBAL & FLOATING SIDES */
			#floating-widget, #bottom-widget { z-index: 99999; font-family: system-ui, sans-serif; }
			#floating-widget { position: fixed; inset: 0; pointer-events: none; }
			.promo-unit { position: fixed; pointer-events: auto; }
			.promo-left { left: 12px; bottom: 140px; }
			.promo-right { right: 12px; bottom: 140px; }
			.promo-media { display: block; border-radius: 14px; height: auto; box-shadow: 0 8px 24px rgba(0,0,0,.25); }
			.side-media { width: 140px; }
			#bottom-widget { position: fixed; left: 50%; bottom: 10px; transform: translateX(-50%); }
			.bottom-unit { position: relative; }
			.bottom-media { width: clamp(260px, 90vw, 728px); }
			.widget-close { position: absolute; top: 0; right: 0; width: 32px; height: 32px; border: none; border-radius: 0 14px 0 14px; background: rgba(0,0,0,.72); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 20; backdrop-filter: blur(4px); }
			
			/* MOBILE */
			@media(max-width: 768px) {
				.promo-img { width: 95vw; max-width: 420px; }
				.side-media { width: 82px; }
				.promo-left, .promo-right { bottom: 120px; }
			}
		</style>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				document.querySelectorAll('[data-close]').forEach(button => {
					button.addEventListener('click', () => {
						const target = document.getElementById(button.dataset.close);
						if(target){
							target.style.display = 'none';
						}
					});
				});
			});
		</script>
	</div>
	<section class="single-video-infos">
		<div class="container">
			<div class="row">
				<div class="col-12 col-md-8 col-left">
					<h1 class="single-video-title">
						<?php echo wp_kses_post( get_theme_mod( 'seo_video_title', $video_title ) ); ?>
					</h1>
					<?php
					$content          = get_the_content();
					$video_in_content = false;
					$video_code       = array();
					if ( preg_match( '/\[video.+\]/', get_the_content(), $video_code ) ) {
						$video_in_content = '/\[video.+\]/';
					} elseif ( preg_match( '/<iframe.+<\/iframe>/', get_the_content(), $video_code ) ) {
						$video_in_content = '/<iframe.+<\/iframe>/';
					} elseif ( preg_match( '/<video.+<\/video>/', get_the_content(), $video_code ) ) {
						$video_in_content = '/<video.+<\/video>/';
					} elseif ( preg_match( '/<object.+<\/object>/', get_the_content(), $video_code ) ) {
						$video_in_content = '/<object.+<\/object>/';
					} elseif ( preg_match( "/https:\/\/www.youtube.com\/watch\?v=.+?\b/", get_the_content(), $video_code ) ) {
						$video_in_content = "/https:\/\/www.youtube.com\/watch\?v=.+?\b/";
					}
					do_action( 'wps_single_video_template_ready', get_the_ID() );
					if ( has_action( 'wps_single_video_labels_before' ) ) {
						do_action( 'wps_single_video_labels_before', get_the_ID() );
					}
					if ( ! empty( $content ) ) :
						?>
						<div class="video-description">
							<?php
							$embed_code      = get_post_meta( $post->ID, 'embed', true );
							$video_url       = get_post_meta( $post->ID, 'video_url', true );
							$video_shortcode = get_post_meta( $post->ID, 'shortcode', true );
							if ( false !== $video_in_content && ( '' === $embed_code && '' === $video_shortcode && '' === $video_url ) ) {
								$content = preg_replace( $video_in_content, '', get_the_content() );
							}
							echo apply_filters( 'the_content', $content );
							?>
						</div>
					<?php endif; ?>

					<?php
					// ✅ ====== แกลเลอรีรูปภาพ + ระบบ Lightbox ครบถ้วน ======
					if ( ! empty( $gallery_images ) ) : ?>
						<div class="video-gallery wps-video-gallery">
							<h3 class="gallery-title">แกลเลอรีรูปภาพ</h3>
							<div class="gallery-grid">
								<?php foreach ( $gallery_images as $idx => $img_data ) : ?>
									<div class="gallery-thumb">
										<a href="<?php echo esc_url( $img_data['full'] ); ?>"
										   class="wps-lightbox-trigger"
										   data-lightbox="video-gallery"
										   data-index="<?php echo esc_attr( $idx ); ?>"
										   data-caption="<?php echo esc_attr( $video_title . ' - รูปที่ ' . ( $idx + 1 ) ); ?>">
											<img src="<?php echo esc_url( $img_data['thumb'] ); ?>"
												 alt="<?php echo esc_attr( $video_title ); ?>"
												 loading="lazy"
												 decoding="async">
											<div class="gallery-zoom-icon">
												<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<circle cx="11" cy="11" r="8"></circle>
													<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
													<line x1="11" y1="8" x2="11" y2="14"></line>
													<line x1="8" y1="11" x2="14" y2="11"></line>
												</svg>
											</div>
										</a>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- ✅ Lightbox Modal -->
						<div id="wps-lightbox-modal" class="wps-lightbox-modal" role="dialog" aria-modal="true" aria-label="Image gallery">
							<button class="lightbox-close" aria-label="Close">&times;</button>
							<button class="lightbox-prev" aria-label="Previous image">&#10094;</button>
							<button class="lightbox-next" aria-label="Next image">&#10095;</button>
							<div class="lightbox-content">
								<img class="lightbox-image" src="" alt="">
								<div class="lightbox-caption"></div>
								<div class="lightbox-counter"></div>
							</div>
						</div>

						<!-- ✅ Lightbox CSS & JavaScript -->
						<style>
							/* ===== Gallery Grid ===== */
							.wps-video-gallery { margin: 24px 0; }
							.wps-video-gallery .gallery-title {
								font-size: 1.2rem;
								font-weight: 700;
								margin-bottom: 16px;
								color: #fff;
							}
							.wps-video-gallery .gallery-grid {
								display: grid;
								grid-template-columns: repeat(3, 1fr);
								gap: 12px;
							}
							.wps-video-gallery .gallery-thumb {
								position: relative;
								overflow: hidden;
								border-radius: 8px;
								cursor: pointer;
								aspect-ratio: 16/10;
							}
							.wps-video-gallery .gallery-thumb img {
								width: 100%;
								height: 100%;
								object-fit: cover;
								transition: transform 0.3s ease;
							}
							.wps-video-gallery .gallery-thumb:hover img {
								transform: scale(1.08);
							}
							.wps-video-gallery .gallery-zoom-icon {
								position: absolute;
								inset: 0;
								display: flex;
								align-items: center;
								justify-content: center;
								background: rgba(0, 0, 0, 0.35);
								color: #fff;
								opacity: 0;
								transition: opacity 0.3s ease;
							}
							.wps-video-gallery .gallery-thumb:hover .gallery-zoom-icon {
								opacity: 1;
							}
							@media (max-width: 768px) {
								.wps-video-gallery .gallery-grid {
									grid-template-columns: repeat(2, 1fr);
									gap: 8px;
								}
							}

							/* ===== Lightbox Modal ===== */
							.wps-lightbox-modal {
								display: none;
								position: fixed;
								inset: 0;
								z-index: 999999;
								background: rgba(0, 0, 0, 0.92);
								align-items: center;
								justify-content: center;
								padding: 20px;
								box-sizing: border-box;
							}
							.wps-lightbox-modal.is-open {
								display: flex;
								animation: lightboxFadeIn 0.25s ease;
							}
							@keyframes lightboxFadeIn {
								from { opacity: 0; }
								to { opacity: 1; }
							}
							.wps-lightbox-modal .lightbox-content {
								position: relative;
								max-width: 90vw;
								max-height: 85vh;
								display: flex;
								flex-direction: column;
								align-items: center;
								justify-content: center;
							}
							.wps-lightbox-modal .lightbox-image {
								max-width: 100%;
								max-height: 80vh;
								width: auto;
								height: auto;
								object-fit: contain;
								border-radius: 6px;
								box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
							}
							.wps-lightbox-modal .lightbox-caption {
								color: #fff;
								text-align: center;
								margin-top: 12px;
								font-size: 14px;
								opacity: 0.85;
								max-width: 600px;
							}
							.wps-lightbox-modal .lightbox-counter {
								color: #fff;
								text-align: center;
								margin-top: 6px;
								font-size: 13px;
								opacity: 0.6;
							}
							.wps-lightbox-modal .lightbox-close {
								position: fixed;
								top: 20px;
								right: 30px;
								background: rgba(255, 255, 255, 0.15);
								color: #fff;
								border: none;
								font-size: 42px;
								line-height: 1;
								width: 50px;
								height: 50px;
								border-radius: 50%;
								cursor: pointer;
								z-index: 10;
								transition: all 0.2s ease;
								display: flex;
								align-items: center;
								justify-content: center;
							}
							.wps-lightbox-modal .lightbox-close:hover {
								background: rgba(255, 255, 255, 0.25);
								transform: rotate(90deg);
							}
							.wps-lightbox-modal .lightbox-prev,
							.wps-lightbox-modal .lightbox-next {
								position: fixed;
								top: 50%;
								transform: translateY(-50%);
								background: rgba(255, 255, 255, 0.15);
								color: #fff;
								border: none;
								font-size: 28px;
								width: 50px;
								height: 50px;
								border-radius: 50%;
								cursor: pointer;
								z-index: 10;
								transition: all 0.2s ease;
								display: flex;
								align-items: center;
								justify-content: center;
							}
							.wps-lightbox-modal .lightbox-prev:hover,
							.wps-lightbox-modal .lightbox-next:hover {
								background: rgba(255, 255, 255, 0.25);
							}
							.wps-lightbox-modal .lightbox-prev { left: 20px; }
							.wps-lightbox-modal .lightbox-next { right: 20px; }
							@media (max-width: 768px) {
								.wps-lightbox-modal .lightbox-prev { left: 8px; }
								.wps-lightbox-modal .lightbox-next { right: 8px; }
								.wps-lightbox-modal .lightbox-close { top: 12px; right: 12px; }
								.wps-lightbox-modal .lightbox-prev,
								.wps-lightbox-modal .lightbox-next,
								.wps-lightbox-modal .lightbox-close {
									width: 40px;
									height: 40px;
									font-size: 22px;
								}
							}
						</style>

						<script>
						(function() {
							// ✅ ระบบ Lightbox ครบถ้วน
							var galleryData = <?php echo wp_json_encode( $gallery_images ); ?>;
							var currentIndex = 0;
							var modal = document.getElementById('wps-lightbox-modal');
							var modalImg = modal.querySelector('.lightbox-image');
							var caption = modal.querySelector('.lightbox-caption');
							var counter = modal.querySelector('.lightbox-counter');
							var prevBtn = modal.querySelector('.lightbox-prev');
							var nextBtn = modal.querySelector('.lightbox-next');
							var closeBtn = modal.querySelector('.lightbox-close');

							function openLightbox(index) {
								currentIndex = parseInt(index, 10) || 0;
								updateLightbox();
								modal.classList.add('is-open');
								document.body.style.overflow = 'hidden';
							}

							function closeLightbox() {
								modal.classList.remove('is-open');
								document.body.style.overflow = '';
							}

							function updateLightbox() {
								if (!galleryData[currentIndex]) return;
								modalImg.src = galleryData[currentIndex].full;
								modalImg.alt = 'Gallery image ' + (currentIndex + 1);
								var captionText = '<?php echo esc_js( $video_title ); ?>' + ' - รูปที่ ' + (currentIndex + 1);
								caption.textContent = captionText;
								counter.textContent = (currentIndex + 1) + ' / ' + galleryData.length;
							}

							function showPrev() {
								currentIndex = (currentIndex - 1 + galleryData.length) % galleryData.length;
								updateLightbox();
							}

							function showNext() {
								currentIndex = (currentIndex + 1) % galleryData.length;
								updateLightbox();
							}

							// ผูกอีเวนต์กับรูปขนาดเล็ก
							document.querySelectorAll('.wps-lightbox-trigger').forEach(function(trigger) {
								trigger.addEventListener('click', function(e) {
									e.preventDefault();
									var idx = this.getAttribute('data-index') || 0;
									openLightbox(idx);
								});
							});

							// ปุ่มปิด
							closeBtn.addEventListener('click', closeLightbox);

							// คลิกพื้นหลัง → ปิด
							modal.addEventListener('click', function(e) {
								if (e.target === modal) closeLightbox();
							});

							// ปุ่มถัดไป/ก่อนหน้า
							prevBtn.addEventListener('click', function(e) { e.stopPropagation(); showPrev(); });
							nextBtn.addEventListener('click', function(e) { e.stopPropagation(); showNext(); });

							// คีย์บอร์ด
							document.addEventListener('keydown', function(e) {
								if (!modal.classList.contains('is-open')) return;
								if (e.key === 'Escape') closeLightbox();
								if (e.key === 'ArrowLeft') showPrev();
								if (e.key === 'ArrowRight') showNext();
							});

							// ป้องกันการลากรูป
							modalImg.addEventListener('dragstart', function(e) { e.preventDefault(); });
						})();
						</script>
					<?php endif; ?>

					<?php
					if ( has_action( 'wps_single_video_labels_after' ) ) {
						do_action( 'wps_single_video_labels_after', get_the_ID() );
					} elseif ( ! has_action( 'wps_single_video_labels_before' ) ) {
						$postcats = get_the_category();
						$posttags = get_the_tags();
						$actors   = wp_get_post_terms( $post->ID, 'actors' );
						$studios  = wp_get_post_terms( $post->ID, 'studio' );
						?>
						<div class="video-taxonomy-meta" aria-label="<?php echo esc_attr__( 'ข้อมูลวิดีโอ', 'wpst' ); ?>">
							<div class="video-taxonomy-row">
								<div class="video-taxonomy-title"><i class="fa fa-folder-open-o" aria-hidden="true"></i><span>หมวดหมู่ :</span></div>
								<div class="video-taxonomy-items">
									<?php
									$has_category = false;
									if ( ! empty( $postcats ) && ! is_wp_error( $postcats ) ) :
										foreach ( (array) $postcats as $cat ) :
											if ( 'uncategorized' === $cat->slug ) {
												continue;
											}
											$has_category = true;
											?>
											<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" class="label" title="<?php echo esc_attr( $cat->name ); ?>"><i class="fa fa-folder" aria-hidden="true"></i> <?php echo esc_html( $cat->name ); ?></a>
											<?php
										endforeach;
									endif;
									if ( ! $has_category ) :
										?><span class="video-taxonomy-empty">ไม่มีหมวดหมู่</span><?php
									endif;
									?>
								</div>
							</div>

							<?php if ( ! empty( $actors ) && ! is_wp_error( $actors ) ) : ?>
								<div class="video-taxonomy-row">
									<div class="video-taxonomy-title"><i class="fa fa-star" aria-hidden="true"></i><span>นักแสดง :</span></div>
									<div class="video-taxonomy-items">
										<?php foreach ( (array) $actors as $actor ) : ?>
											<a href="<?php echo esc_url( get_term_link( $actor->term_id ) ); ?>" class="label" title="<?php echo esc_attr( $actor->name ); ?>"><i class="fa fa-star" aria-hidden="true"></i> <?php echo esc_html( $actor->name ); ?></a>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $studios ) && ! is_wp_error( $studios ) ) : ?>
								<div class="video-taxonomy-row">
									<div class="video-taxonomy-title"><i class="fa fa-video-camera" aria-hidden="true"></i><span>ค่าย :</span></div>
									<div class="video-taxonomy-items">
										<?php foreach ( (array) $studios as $studio ) : ?>
											<a href="<?php echo esc_url( get_term_link( $studio->term_id ) ); ?>" class="label" title="<?php echo esc_attr( $studio->name ); ?>"><i class="fa fa-video-camera" aria-hidden="true"></i> <?php echo esc_html( $studio->name ); ?></a>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $posttags ) && ! is_wp_error( $posttags ) ) : ?>
								<div class="video-taxonomy-row">
									<div class="video-taxonomy-title"><i class="fa fa-tags" aria-hidden="true"></i><span>แท็ก :</span></div>
									<div class="video-taxonomy-items">
										<?php foreach ( (array) $posttags as $tag ) : ?>
											<a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="label" title="<?php echo esc_attr( $tag->name ); ?>"><i class="fa fa-tag" aria-hidden="true"></i> <?php echo esc_html( $tag->name ); ?></a>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php
					}
					?>
				</div>
				<div class="col-12 col-md-4">
					<?php if ( $wps_poster_url ) : ?>
						<div class="poster-card mb-3">
							<img 
								src="<?php echo esc_url( $wps_poster_url ); ?>" 
								alt="<?php echo esc_attr( $wps_poster_alt ); ?>" 
								class="img-fluid"
								loading="eager"
							>
						</div>
					<?php endif; ?>
					<div class="video-buttons-row" role="group" aria-label="Video actions">
						<?php if ( $wps_show_likes ) : ?>
							<button
								type="button"
								class="capsule-btn capsule-btn-like<?php echo wpst_hasAlreadyVoted( get_the_ID() ) ? ' is-active' : ''; ?>"
								aria-pressed="<?php echo wpst_hasAlreadyVoted( get_the_ID() ) ? 'true' : 'false'; ?>"
								data-post_id="<?php echo esc_attr( get_the_ID() ); ?>"
								data-post_like="like"
								data-action-ready="1"
							>
								<i class="fa fa-heart" aria-hidden="true"></i>
								<span><?php echo esc_html( wpst_hasAlreadyVoted( get_the_ID() ) ? 'ถูกใจแล้ว' : 'ถูกใจ' ); ?></span>
								<span class="action-spinner" aria-hidden="true"></span>
							</button>
						<?php endif; ?>
						<button
							type="button"
							class="capsule-btn capsule-btn-save"
							aria-pressed="false"
							data-post_id="<?php echo esc_attr( get_the_ID() ); ?>"
							data-action-ready="1"
						>
							<i class="fa fa-bookmark" aria-hidden="true"></i>
							<span>บันทึก</span>
						</button>
						<button
							type="button"
							id="show-sharing-buttons"
							class="capsule-btn capsule-btn-share"
							aria-expanded="false"
							data-action-ready="1"
						>
							<i class="fa fa-link" aria-hidden="true"></i>
							<span>แชร์</span>
						</button>
					</div>
					<div class="video-share-box" id="video-share-box" style="display: none;">
						<span class="title"><?php esc_html_e( 'Share', 'wpst' ); ?></span>
						
						<div class="share-buttons">
							<?php get_template_part( 'loop-templates/content', 'share-buttons' ); ?>
						</div>
						<div class="video-share-url">
							<textarea id="copyme" readonly="readonly"><?php the_permalink(); ?></textarea>
							<a id="clickme"><?php esc_html_e( 'Copy the link', 'wpst' ); ?></a>
							<textarea id="temptext"></textarea>
						</div>
						<div class="clear"></div>
					</div>
					<?php if ( $video_tracking_url && get_theme_mod( 'enable_video_tracking_link', 'no' ) === 'yes' ) : ?>
						<div class="embed-responsive embed-responsive-16by9 video-tracking mt-3">
							<div class="bg-image" style="background-image: url(<?php echo esc_url( $video_thumb_url ); ?>);"></div>
							<div class="bg-gradient"></div>
							<a href="<?php echo esc_url( $video_tracking_url ); ?>" target="_blank" rel="nofollow noreferrer">
								<span class="text"><?php echo esc_html( get_theme_mod( 'seo_video_tracking_button', 'Download full video now' ) ); ?></span>
							</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
	<?php if ( $ads['before_related_videos_ads'] ) : ?>
		<div class="happy-section">
			<?php echo $ads['before_related_videos_ads']; ?>
		</div>
	<?php endif; ?>
