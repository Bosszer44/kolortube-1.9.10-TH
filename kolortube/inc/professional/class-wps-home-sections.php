<?php
/** Separate, data-driven homepage sections with responsive grid/slider controls. */
defined( 'ABSPATH' ) || exit;

final class WPS_Home_Sections {
	const CACHE_VERSION_OPTION = 'wps_home_sections_cache_version';

	public static function register() {
		add_action( 'created_term', array( __CLASS__, 'flush_cache' ) );
		add_action( 'edited_term', array( __CLASS__, 'flush_cache' ) );
		add_action( 'delete_term', array( __CLASS__, 'flush_cache' ) );
		add_action( 'save_post', array( __CLASS__, 'flush_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_cache' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 24 );
	}

	public static function enabled() {
		return class_exists( 'WPS_Professional_Suite' ) && (bool) WPS_Professional_Suite::get( 'home_sections_enabled', 1 );
	}

	private static function allowed_sections() {
		return array(
			'featured'      => 'Featured / เรื่องแนะนำ',
			'latest_videos' => 'Latest Videos / เรื่องล่าสุด',
			'seo'           => 'SEO',
			'studio'       => 'Studio / ค่าย',
			'actors'       => 'Actors / นักแสดง',
			'gallery_tags' => 'Gallery Tag / แท็ก',
		);
	}

	public static function sanitize_settings( array $current, array $posted ) {
		foreach ( array( 'home_sections_enabled', 'home_show_featured', 'home_show_seo', 'home_show_studios', 'home_show_actors', 'home_show_tags', 'home_slider_autoplay', 'home_featured_slider', 'home_studio_slider', 'home_actor_slider', 'home_tag_slider', 'home_featured_view_all', 'home_tag_view_all', 'home_featured_autoplay', 'home_studio_autoplay', 'home_actor_autoplay', 'home_tag_autoplay' ) as $key ) {
			$current[ $key ] = empty( $posted[ $key ] ) ? 0 : 1;
		}
		foreach ( array( 'home_featured_count', 'home_studio_count', 'home_actor_count', 'home_tag_count' ) as $key ) {
			$current[ $key ] = max( 4, min( 20, absint( isset( $posted[ $key ] ) ? $posted[ $key ] : 20 ) ) );
		}
		foreach ( array( 'home_columns_desktop' => array( 2, 8, 5 ), 'home_columns_tablet' => array( 2, 6, 3 ), 'home_columns_mobile' => array( 1, 5, 2 ), 'home_rows' => array( 1, 1, 1 ), 'home_slider_interval' => array( 1000, 15000, 3000 ) ) as $key => $limits ) {
			$current[ $key ] = max( $limits[0], min( $limits[1], absint( isset( $posted[ $key ] ) ? $posted[ $key ] : $limits[2] ) ) );
		}
		$current['home_layout_mode'] = isset( $posted['home_layout_mode'] ) && in_array( $posted['home_layout_mode'], array( 'grid', 'slider' ), true ) ? $posted['home_layout_mode'] : 'grid';
		$current['home_actor_name_mode'] = isset( $posted['home_actor_name_mode'] ) && in_array( $posted['home_actor_name_mode'], array( 'current', 'th', 'en' ), true ) ? $posted['home_actor_name_mode'] : 'current';
		$current['home_seo_image_layout'] = isset( $posted['home_seo_image_layout'] ) && in_array( $posted['home_seo_image_layout'], array( 'badge', 'wide', 'free', 'hidden' ), true ) ? $posted['home_seo_image_layout'] : 'free';
		$current['home_seo_logo_id'] = isset( $posted['home_seo_logo_id'] ) ? absint( $posted['home_seo_logo_id'] ) : 0;

		$allowed = array_keys( self::allowed_sections() );
		$parts = array();
		if ( isset( $posted['home_section_order_items'] ) && is_array( $posted['home_section_order_items'] ) ) {
			foreach ( $posted['home_section_order_items'] as $part ) {
				$part = sanitize_key( wp_unslash( $part ) );
				if ( in_array( $part, $allowed, true ) && ! in_array( $part, $parts, true ) ) {
					$parts[] = $part;
				}
			}
		} else {
			$order = isset( $posted['home_section_order'] ) ? sanitize_text_field( wp_unslash( $posted['home_section_order'] ) ) : 'featured,latest_videos,studio,actors,gallery_tags,seo';
			foreach ( array_map( 'trim', explode( ',', $order ) ) as $part ) {
				if ( in_array( $part, $allowed, true ) && ! in_array( $part, $parts, true ) ) {
					$parts[] = $part;
				}
			}
		}
		$current['home_section_order'] = implode( ',', array_unique( array_merge( $parts, array_diff( $allowed, $parts ) ) ) );
		self::flush_cache();
		return $current;
	}

	public static function render_admin_fields( array $settings ) {
		$settings = wp_parse_args( $settings, self::default_settings() );
		$seo_logo_id = absint( $settings['home_seo_logo_id'] );
		$seo_logo_preview = $seo_logo_id ? wp_get_attachment_image_url( $seo_logo_id, 'thumbnail' ) : '';
		$order = array_values( array_filter( array_map( 'trim', explode( ',', $settings['home_section_order'] ) ) ) );
		$allowed_sections = self::allowed_sections();
		$order = array_values( array_unique( array_merge( $order, array_diff( array_keys( $allowed_sections ), $order ) ) ) );
		?>
		<div class="wps-pro-grid">
			<section class="wps-pro-card"><h2><?php echo esc_html( WPS_Professional_Suite::admin_text( 'ส่วนข้อมูลหน้าโฮม', 'Homepage Data Sections' ) ); ?></h2><p><?php echo esc_html( WPS_Professional_Suite::admin_text( 'แยก Featured, เรื่องล่าสุด, SEO, Studio, นักแสดง และ Gallery Tag เป็นคนละส่วน โดยดึงข้อมูลจริงจากฐานข้อมูล WordPress', 'Render Featured, Latest Videos, SEO, Studios, Actors and Gallery Tags as separate live sections.' ) ); ?></p><label class="wps-pro-toggle"><input type="checkbox" name="settings[home_sections_enabled]" value="1" <?php checked( ! empty( $settings['home_sections_enabled'] ) ); ?>><span><?php echo esc_html( WPS_Professional_Suite::admin_text( 'เปิดส่วนข้อมูลหน้าโฮมแบบแยก', 'Enable separated homepage sections' ) ); ?></span></label></section>
			<section class="wps-pro-card"><h2><?php echo esc_html( WPS_Professional_Suite::admin_text( 'เปิด/ปิดแต่ละส่วน', 'Section Visibility' ) ); ?></h2><label class="wps-pro-toggle"><input type="checkbox" name="settings[home_show_featured]" value="1" <?php checked( ! empty( $settings['home_show_featured'] ) ); ?>><span>เรื่องแนะนำ Featured</span></label><label class="wps-pro-toggle"><input type="checkbox" name="settings[home_show_seo]" value="1" <?php checked( ! empty( $settings['home_show_seo'] ) ); ?>><span>SEO</span></label><label class="wps-pro-toggle"><input type="checkbox" name="settings[home_show_studios]" value="1" <?php checked( ! empty( $settings['home_show_studios'] ) ); ?>><span>Studio</span></label><label class="wps-pro-toggle"><input type="checkbox" name="settings[home_show_actors]" value="1" <?php checked( ! empty( $settings['home_show_actors'] ) ); ?>><span><?php echo esc_html( WPS_Professional_Suite::admin_text( 'นักแสดง', 'Actors' ) ); ?></span></label><label class="wps-pro-toggle"><input type="checkbox" name="settings[home_show_tags]" value="1" <?php checked( ! empty( $settings['home_show_tags'] ) ); ?>><span>Gallery Tag</span></label></section>
			<section class="wps-pro-card"><h2>Layout / Responsive</h2><p class="description">เลือกได้ว่าแต่ละบล็อกจะเป็น Grid หรือ Slider แยกกัน และตั้ง Auto Slide แยกอิสระ เช่น เปิด Auto เฉพาะ Featured แล้วปิด Auto ของ Studio / นักแสดง / Gallery Tag ได้</p><p><label>โหมดสำรอง <select name="settings[home_layout_mode]"><option value="grid" <?php selected( $settings['home_layout_mode'], 'grid' ); ?>>Grid</option><option value="slider" <?php selected( $settings['home_layout_mode'], 'slider' ); ?>>Slider</option></select></label></p><div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[home_featured_slider]" value="1" <?php checked( ! empty( $settings['home_featured_slider'] ) ); ?>> Featured เป็น Slider</label><label><input type="checkbox" name="settings[home_studio_slider]" value="1" <?php checked( ! empty( $settings['home_studio_slider'] ) ); ?>> Studio เป็น Slider</label><label><input type="checkbox" name="settings[home_actor_slider]" value="1" <?php checked( ! empty( $settings['home_actor_slider'] ) ); ?>> นักแสดงเป็น Slider</label><label><input type="checkbox" name="settings[home_tag_slider]" value="1" <?php checked( ! empty( $settings['home_tag_slider'] ) ); ?>> Gallery Tag เป็น Slider</label></div><h3>ปุ่มดูทั้งหมด</h3><div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[home_featured_view_all]" value="1" <?php checked( ! empty( $settings['home_featured_view_all'] ) ); ?>> Featured ดูทั้งหมด</label><label><input type="checkbox" name="settings[home_tag_view_all]" value="1" <?php checked( ! empty( $settings['home_tag_view_all'] ) ); ?>> Gallery Tag ดูทั้งหมด</label></div><h3>Auto Slide แยกแต่ละส่วน</h3><div class="wps-checkbox-columns"><label><input type="checkbox" name="settings[home_featured_autoplay]" value="1" <?php checked( ! empty( $settings['home_featured_autoplay'] ) ); ?>> Auto Featured</label><label><input type="checkbox" name="settings[home_studio_autoplay]" value="1" <?php checked( ! empty( $settings['home_studio_autoplay'] ) ); ?>> Auto Studio</label><label><input type="checkbox" name="settings[home_actor_autoplay]" value="1" <?php checked( ! empty( $settings['home_actor_autoplay'] ) ); ?>> Auto นักแสดง</label><label><input type="checkbox" name="settings[home_tag_autoplay]" value="1" <?php checked( ! empty( $settings['home_tag_autoplay'] ) ); ?>> Auto Gallery Tag</label></div><div class="wps-pro-two"><label>Desktop columns<input type="number" min="2" max="8" name="settings[home_columns_desktop]" value="<?php echo esc_attr( $settings['home_columns_desktop'] ); ?>"></label><label>Tablet columns<input type="number" min="2" max="6" name="settings[home_columns_tablet]" value="<?php echo esc_attr( $settings['home_columns_tablet'] ); ?>"></label><label>Mobile columns<input type="number" min="1" max="5" name="settings[home_columns_mobile]" value="<?php echo esc_attr( $settings['home_columns_mobile'] ); ?>"></label><label>Rows<input type="number" min="1" max="6" name="settings[home_rows]" value="<?php echo esc_attr( $settings['home_rows'] ); ?>"></label></div><label>Interval ms<input type="number" min="1000" max="15000" step="500" name="settings[home_slider_interval]" value="<?php echo esc_attr( $settings['home_slider_interval'] ); ?>"></label><p class="description">ถ้าเลือก Auto แต่ไม่ได้เลือกเป็น Slider ระบบจะไม่เลื่อนเองจนกว่าจะเปิด Slider ของส่วนนั้น</p></section>
			<section class="wps-pro-card"><h2><?php echo esc_html( WPS_Professional_Suite::admin_text( 'รูป SEO / ภาษานักแสดง', 'SEO image / Actor language' ) ); ?></h2><p class="description"><?php echo esc_html( WPS_Professional_Suite::admin_text( 'เลือกรูปในกรอบ SEO ได้เอง ใช้ได้ทั้งโลโก้ ภาพกว้าง หรือภาพยาวอิสระ และกำหนดให้บล็อกนักแสดงแสดงแค่ภาษาเดียว', 'Choose a custom SEO section image, including logo, wide banner or free-height image, and force actor cards to one language.' ) ); ?></p><div class="wps-media-id-row"><input type="hidden" class="wps-image-id" name="settings[home_seo_logo_id]" value="<?php echo esc_attr( $seo_logo_id ); ?>"><div class="wps-media-preview"><?php if ( $seo_logo_preview ) : ?><img src="<?php echo esc_url( $seo_logo_preview ); ?>" alt="" style="max-width:280px;max-height:140px;height:auto"><?php endif; ?></div><p><button type="button" class="button wps-choose-image-id"><?php echo esc_html( WPS_Professional_Suite::admin_text( 'เลือกรูป SEO', 'Choose SEO image' ) ); ?></button> <button type="button" class="button wps-remove-image-id"><?php echo esc_html( WPS_Professional_Suite::admin_text( 'ลบรูป SEO', 'Remove SEO image' ) ); ?></button></p></div><p><label><?php echo esc_html( WPS_Professional_Suite::admin_text( 'รูปแบบรูป SEO', 'SEO image layout' ) ); ?><select name="settings[home_seo_image_layout]"><option value="badge" <?php selected( $settings['home_seo_image_layout'], 'badge' ); ?>>โลโก้เล็กเหนือหัวข้อ</option><option value="wide" <?php selected( $settings['home_seo_image_layout'], 'wide' ); ?>>ภาพกว้าง / Banner</option><option value="free" <?php selected( $settings['home_seo_image_layout'], 'free' ); ?>>ภาพอิสระ / ภาพยาว สูงตามจริง</option><option value="hidden" <?php selected( $settings['home_seo_image_layout'], 'hidden' ); ?>>ไม่แสดงรูป</option></select></label></p><p class="description">ถ้าเลือก “ภาพอิสระ / ภาพยาว” รูปจะใช้ความกว้างเต็มกรอบและไม่บังคับตัดสูง เหมาะกับภาพโปรโมตหรือโลโก้แนวยาว</p><label><?php echo esc_html( WPS_Professional_Suite::admin_text( 'ชื่อนักแสดงในหน้าโฮม', 'Actor names on homepage' ) ); ?><select name="settings[home_actor_name_mode]"><option value="current" <?php selected( $settings['home_actor_name_mode'], 'current' ); ?>><?php echo esc_html( WPS_Professional_Suite::admin_text( 'ตามปุ่มภาษาเว็บ', 'Follow site language' ) ); ?></option><option value="th" <?php selected( $settings['home_actor_name_mode'], 'th' ); ?>>ไทยเท่านั้น</option><option value="en" <?php selected( $settings['home_actor_name_mode'], 'en' ); ?>>English only</option></select></label></section>
			<section class="wps-pro-card"><h2><?php echo esc_html( WPS_Professional_Suite::admin_text( 'จำนวนรายการ / ลำดับ', 'Item Limits / Order' ) ); ?></h2><div class="wps-pro-two"><label>Featured<input type="number" min="4" max="48" name="settings[home_featured_count]" value="<?php echo esc_attr( $settings['home_featured_count'] ); ?>"></label><label>Studio<input type="number" min="4" max="48" name="settings[home_studio_count]" value="<?php echo esc_attr( $settings['home_studio_count'] ); ?>"></label><label><?php echo esc_html( WPS_Professional_Suite::admin_text( 'นักแสดง', 'Actors' ) ); ?><input type="number" min="4" max="48" name="settings[home_actor_count]" value="<?php echo esc_attr( $settings['home_actor_count'] ); ?>"></label><label>Gallery Tag<input type="number" min="4" max="48" name="settings[home_tag_count]" value="<?php echo esc_attr( $settings['home_tag_count'] ); ?>"></label></div><h3><?php echo esc_html( WPS_Professional_Suite::admin_text( 'ย้ายตำแหน่งบล็อกหน้าแรก', 'Move homepage blocks' ) ); ?></h3><div class="wps-pro-two"><?php foreach ( $order as $idx => $current_key ) : ?><label><?php echo esc_html( sprintf( WPS_Professional_Suite::admin_text( 'ตำแหน่งที่ %d', 'Position %d' ), $idx + 1 ) ); ?><select name="settings[home_section_order_items][]"><?php foreach ( $allowed_sections as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_key, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><?php endforeach; ?></div><p><label>ลำดับ section<input class="large-text code" type="text" readonly name="settings[home_section_order]" value="<?php echo esc_attr( $settings['home_section_order'] ); ?>"></label></p><p class="description">สามารถย้าย Featured / เรื่องล่าสุด / SEO / Studio / Actors / Gallery Tags ขึ้นลงเองได้ ถ้าเลือกซ้ำ ระบบจะตัดซ้ำและเติมส่วนที่ขาดให้อัตโนมัติ</p></section>
		</div>
		<?php
	}

	private static function default_settings() {
		return array( 'home_sections_enabled' => 1, 'home_show_featured' => 1, 'home_show_seo' => 1, 'home_show_studios' => 1, 'home_show_actors' => 1, 'home_show_tags' => 1, 'home_featured_count' => 20, 'home_studio_count' => 20, 'home_actor_count' => 20, 'home_tag_count' => 20, 'home_layout_mode' => 'grid', 'home_columns_desktop' => 5, 'home_columns_tablet' => 3, 'home_columns_mobile' => 2, 'home_rows' => 1, 'home_featured_slider' => 1, 'home_studio_slider' => 0, 'home_actor_slider' => 0, 'home_tag_slider' => 1, 'home_featured_view_all' => 1, 'home_tag_view_all' => 0, 'home_slider_autoplay' => 1, 'home_featured_autoplay' => 1, 'home_studio_autoplay' => 0, 'home_actor_autoplay' => 0, 'home_tag_autoplay' => 1, 'home_slider_interval' => 3000, 'home_section_order' => 'featured,latest_videos,studio,actors,gallery_tags,seo', 'home_actor_name_mode' => 'current', 'home_seo_image_layout' => 'free', 'home_seo_logo_id' => 0 );
	}

	public static function render() {
		self::render_sections_for_position( 'all' );
	}

	public static function render_before_latest() {
		self::render_sections_for_position( 'before_latest' );
	}

	public static function render_after_latest() {
		self::render_sections_for_position( 'after_latest' );
	}

	private static function normalized_order( array $settings ) {
		$allowed = array_keys( self::allowed_sections() );
		$raw = isset( $settings['home_section_order'] ) ? (string) $settings['home_section_order'] : '';
		$parts = array();
		foreach ( array_map( 'trim', explode( ',', $raw ) ) as $part ) {
			$part = sanitize_key( $part );
			if ( in_array( $part, $allowed, true ) && ! in_array( $part, $parts, true ) ) {
				$parts[] = $part;
			}
		}
		if ( ! in_array( 'latest_videos', $parts, true ) ) {
			$featured_index = array_search( 'featured', $parts, true );
			if ( false !== $featured_index ) {
				array_splice( $parts, $featured_index + 1, 0, array( 'latest_videos' ) );
			} else {
				array_unshift( $parts, 'latest_videos' );
			}
		}
		foreach ( $allowed as $key ) {
			if ( ! in_array( $key, $parts, true ) ) {
				$parts[] = $key;
			}
		}
		return $parts;
	}

	private static function sections_map( array $settings ) {
		$map = array();
		if ( ! empty( $settings['home_show_featured'] ) ) { $map['featured'] = self::featured_section( absint( $settings['home_featured_count'] ) ); }
		if ( ! empty( $settings['home_show_seo'] ) ) { $map['seo'] = self::seo_section(); }
		if ( ! empty( $settings['home_show_studios'] ) ) { $map['studio'] = self::taxonomy_section( 'studio', 'Studio', 'Studios', absint( $settings['home_studio_count'] ), 'template-studio.php', false, 'studio' ); }
		if ( ! empty( $settings['home_show_actors'] ) ) { $map['actors'] = self::taxonomy_section( 'actors', 'นักแสดง AV', 'Actors', absint( $settings['home_actor_count'] ), 'template-actors.php', false, 'actors' ); }
		if ( ! empty( $settings['home_show_tags'] ) ) { $map['gallery_tags'] = self::taxonomy_section( 'post_tag', 'แกลเลอรีแท็ก', 'Gallery Tags', absint( $settings['home_tag_count'] ), 'template-tags.php', true, 'gallery_tags' ); }
		return $map;
	}

	private static function render_sections_for_position( $position ) {
		if ( ! self::enabled() || is_paged() ) { return; }
		$settings = wp_parse_args( WPS_Professional_Suite::settings(), self::default_settings() );
		$order = self::normalized_order( $settings );
		$latest_index = array_search( 'latest_videos', $order, true );
		$map = self::sections_map( $settings );
		$out = array();
		foreach ( $order as $idx => $key ) {
			if ( 'latest_videos' === $key ) { continue; }
			if ( 'before_latest' === $position && false !== $latest_index && $idx > $latest_index ) { continue; }
			if ( 'after_latest' === $position && false !== $latest_index && $idx < $latest_index ) { continue; }
			if ( ! empty( $map[ $key ] ) ) { $out[] = $map[ $key ]; }
		}
		if ( 'all' === $position ) {
			foreach ( $map as $key => $html ) { if ( $html && ! in_array( $html, $out, true ) ) { $out[] = $html; } }
		}
		if ( $out ) { echo '<div class="wps-home-sections wps-home-sections-' . esc_attr( $position ) . '">' . implode( '', $out ) . '</div>'; }
	}

	private static function section_attrs( $section = '' ) {
		$s = wp_parse_args( WPS_Professional_Suite::settings(), self::default_settings() );
		$style = '--wps-home-cols-desktop:' . absint( $s['home_columns_desktop'] ) . ';--wps-home-cols-tablet:' . absint( $s['home_columns_tablet'] ) . ';--wps-home-cols-mobile:' . absint( $s['home_columns_mobile'] ) . ';--wps-home-rows:' . absint( $s['home_rows'] ) . ';';
		$slider_keys = array( 'featured' => 'home_featured_slider', 'studio' => 'home_studio_slider', 'actors' => 'home_actor_slider', 'gallery_tags' => 'home_tag_slider' );
		// Studio and Actors are always rendered as one-row, user-controlled sliders.
		// Their previous grid mode allowed a second row to appear when other CSS loaded.
		$is_slider = in_array( $section, array( 'studio', 'actors' ), true ) || ( isset( $slider_keys[ $section ] ) && ! empty( $s[ $slider_keys[ $section ] ] ) );
		$class = $is_slider ? 'is-slider' : 'is-grid';
		$autoplay_keys = array( 'featured' => 'home_featured_autoplay', 'studio' => 'home_studio_autoplay', 'actors' => 'home_actor_autoplay', 'gallery_tags' => 'home_tag_autoplay' );
		$is_autoplay = $is_slider && ! empty( $s['home_slider_autoplay'] ) && isset( $autoplay_keys[ $section ] ) && ! empty( $s[ $autoplay_keys[ $section ] ] );
		$interval = max( 1000, min( 15000, absint( $s['home_slider_interval'] ) ) );
		$data = $is_slider ? ' data-wps-home-slider data-interval="' . esc_attr( $interval ) . '" data-autoplay="' . ( $is_autoplay ? '1' : '0' ) . '"' : '';
		return array( 'class' => $class, 'style' => $style, 'data' => $data );
	}

	private static function featured_section( $limit ) {
		$q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => max( 4, min( 48, $limit ) ), 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
		if ( ! $q->posts ) { return ''; }
		$attrs = self::section_attrs( 'featured' );
		$settings = wp_parse_args( WPS_Professional_Suite::settings(), self::default_settings() );
		$top_categories = get_categories( array( 'taxonomy' => 'category', 'hide_empty' => true, 'number' => 1, 'orderby' => 'count', 'order' => 'DESC' ) );
		$posts_page_id = absint( get_option( 'page_for_posts' ) );
		$all_url = ! empty( $top_categories ) ? get_category_link( $top_categories[0] ) : ( $posts_page_id ? get_permalink( $posts_page_id ) : add_query_arg( 'post_type', 'post', home_url( '/' ) ) );
		$view_all = ! empty( $settings['home_featured_view_all'] ) ? '<a class="wps-home-view-all" href="' . esc_url( $all_url ) . '">' . esc_html( WPS_Professional_Suite::text( 'ดูทั้งหมด', 'View all' ) ) . ' <span aria-hidden="true">→</span></a>' : '';
		$has_slider_controls = 'is-slider' === $attrs['class'];
		$out = '<section class="wps-home-section wps-home-featured ' . esc_attr( $attrs['class'] ) . '" style="' . esc_attr( $attrs['style'] ) . '"' . $attrs['data'] . '><div class="container container-lg"><div class="wps-home-section-heading"><div><span class="wps-home-eyebrow">FEATURED</span><h2>' . esc_html( WPS_Professional_Suite::text( 'เรื่องแนะนำ', 'Featured Videos' ) ) . '</h2></div>' . ( $view_all ? '<div class="wps-home-section-actions">' . $view_all . '</div>' : '' ) . '</div>' . ( $has_slider_controls ? '<div class="wps-home-slider-shell"><button class="wps-home-slider-control wps-home-slider-prev" type="button" data-wps-slider-prev aria-label="Previous featured videos" disabled><span aria-hidden="true">‹</span></button>' : '' ) . '<div class="wps-home-card-grid">';
		$seen_post_ids = array();
        foreach ( $q->posts as $post ) {
            $post_id = absint( $post->ID );
            if ( ! $post_id || isset( $seen_post_ids[ $post_id ] ) ) continue;
            $seen_post_ids[ $post_id ] = true;
            $image = function_exists( 'wps_get_post_best_image_url' ) ? wps_get_post_best_image_url( $post_id, 'video-thumb' ) : get_the_post_thumbnail_url( $post_id, 'video-thumb' );
            $image = $image ?: get_template_directory_uri() . '/img/no-thumb.png';
            $out .= '<a class="wps-home-card" data-post-id="' . esc_attr( $post_id ) . '" href="' . esc_url( get_permalink( $post_id ) ) . '"><span class="wps-home-card-media"><img loading="lazy" decoding="async" src="' . esc_url( $image ) . '" data-fallback="' . esc_url( self::fallback_image_url() ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '"></span><span class="wps-home-card-copy"><strong>' . esc_html( get_the_title( $post_id ) ) . '</strong></span></a>';
        }
		wp_reset_postdata();
		return $out . '</div>' . ( $has_slider_controls ? '<button class="wps-home-slider-control wps-home-slider-next" type="button" data-wps-slider-next aria-label="Next featured videos"><span aria-hidden="true">›</span></button></div>' : '' ) . '</div></section>';
	}

	private static function seo_section() {
		$title = trim( (string) get_theme_mod( 'seo_home_title', get_bloginfo( 'description' ) ) );
		$description = trim( (string) get_theme_mod( 'seo_home_description', '' ) );
		if ( '' === $title && '' === $description ) { return ''; }
		$s = wp_parse_args( WPS_Professional_Suite::settings(), self::default_settings() );
		$layout = isset( $s['home_seo_image_layout'] ) && in_array( $s['home_seo_image_layout'], array( 'badge', 'wide', 'free', 'hidden' ), true ) ? $s['home_seo_image_layout'] : 'free';
		$seo_logo_id = absint( $s['home_seo_logo_id'] );
		$image_html = '';
		$has_custom_seo_image = false;
		if ( $seo_logo_id && 'hidden' !== $layout ) {
			$src = wp_get_attachment_image_url( $seo_logo_id, 'full' );
			if ( $src ) {
				$has_custom_seo_image = true;
				$image_html = '<img class="wps-home-seo-logo-img wps-home-seo-free-img" src="' . esc_url( $src ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" loading="lazy" decoding="async">';
			}
		}
		if ( ! $image_html && 'hidden' !== $layout ) {
			$layout = 'badge';
			$custom_logo_id = absint( get_theme_mod( 'custom_logo' ) );
			if ( $custom_logo_id ) { $image_html = wp_get_attachment_image( $custom_logo_id, 'full', false, array( 'class' => 'wps-home-seo-logo-img', 'loading' => 'lazy', 'decoding' => 'async' ) ); }
			if ( ! $image_html ) {
				$site_icon = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 96 ) : '';
				if ( $site_icon ) { $image_html = '<img class="wps-home-seo-logo-img" src="' . esc_url( $site_icon ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" loading="lazy" decoding="async">'; }
			}
		}
		$classes = 'wps-home-section wps-home-seo wps-home-seo-layout-' . sanitize_html_class( $layout );
		$out = '<section class="' . esc_attr( $classes ) . '"><div class="container container-lg">';
		if ( $image_html && 'badge' !== $layout ) {
			$out .= '<div class="wps-home-seo-custom-image wps-home-seo-image-' . esc_attr( $layout ) . ( $has_custom_seo_image ? ' is-custom' : '' ) . '">' . $image_html . '</div>';
		}
		$out .= '<div class="wps-home-section-heading"><div>';
		if ( $image_html && 'badge' === $layout ) {
			$out .= '<span class="wps-home-eyebrow wps-home-seo-logo">' . $image_html . '</span>';
		} elseif ( ! $image_html && 'hidden' !== $layout ) {
			$out .= '<span class="wps-home-eyebrow wps-home-seo-icon" aria-hidden="true">SEO</span>';
		}
		if ( '' !== $title ) { $out .= '<h2>' . esc_html( $title ) . '</h2>'; }
		$out .= '</div></div>';
		if ( '' !== $description ) { $out .= '<div class="wps-home-seo-content">' . wp_kses_post( wpautop( do_shortcode( $description ) ) ) . '</div>'; }
		return $out . '</div></section>';
	}

	private static function taxonomy_section( $taxonomy, $title_th, $title_en, $limit, $page_template, $gallery = false, $section_key = '' ) {
		if ( ! taxonomy_exists( $taxonomy ) ) { return ''; }
		$cards = self::get_cards( $taxonomy, max( 4, min( 48, absint( $limit ) ) ) ); if ( empty( $cards ) ) { return ''; }
		$attrs = self::section_attrs( $section_key ); $settings = wp_parse_args( WPS_Professional_Suite::settings(), self::default_settings() ); $title = WPS_Professional_Suite::text( $title_th, $title_en );
		$tag_style = $gallery || in_array( $section_key, array( 'studio', 'actors' ), true );
		// Post Tag never renders View All. Studio and Actors follow the Theme Control options.
		$view_all_key = 'studio' === $section_key ? 'home_studio_view_all' : ( 'actors' === $section_key ? 'home_actor_view_all' : '' );
		$all_url = ( 'post_tag' === $taxonomy || ( $view_all_key && empty( $settings[ $view_all_key ] ) ) ) ? '' : self::page_url_by_template( $page_template );
		$classes = 'wps-home-section wps-home-taxonomy wps-home-' . sanitize_html_class( $taxonomy ) . ' ' . $attrs['class']; if ( $tag_style ) { $classes .= ' is-gallery wps-home-gallery-style'; }
		$has_slider_controls = 'is-slider' === $attrs['class'];
		$out = '<section class="' . esc_attr( $classes ) . '" style="' . esc_attr( $attrs['style'] ) . '"' . $attrs['data'] . '><div class="container container-lg"><div class="wps-home-section-heading"><div><span class="wps-home-eyebrow">' . esc_html( strtoupper( str_replace( '_', ' ', $taxonomy ) ) ) . '</span><h2>' . esc_html( $title ) . '</h2></div>'; if ( $all_url ) { $out .= '<div class="wps-home-section-actions"><a class="wps-home-view-all" href="' . esc_url( $all_url ) . '">' . esc_html( WPS_Professional_Suite::text( 'ดูทั้งหมด', 'View all' ) ) . ' <span aria-hidden="true">→</span></a></div>'; } $out .= '</div>' . ( $has_slider_controls ? '<div class="wps-home-slider-shell">' . '<button class="wps-home-slider-control wps-home-slider-prev" type="button" data-wps-slider-prev aria-label="' . esc_attr( 'studio' === $section_key ? 'Previous studios' : 'Previous actors' ) . '" disabled><span aria-hidden="true">‹</span></button>' : '' ) . '<div class="wps-home-card-grid">';
		foreach ( $cards as $card ) { $out .= '<a class="wps-home-card" href="' . esc_url( $card['url'] ) . '"><span class="wps-home-card-media"><img loading="lazy" decoding="async" src="' . esc_url( $card['image'] ? $card['image'] : self::fallback_image_url() ) . '" data-fallback="' . esc_url( self::fallback_image_url() ) . '" alt="' . esc_attr( $card['name'] ) . '"></span><span class="wps-home-card-copy"><strong>' . esc_html( $card['name'] ) . '</strong><small>' . esc_html( sprintf( WPS_Professional_Suite::text( '%s เรื่อง', '%s posts' ), number_format_i18n( $card['count'] ) ) ) . '</small></span></a>'; }
		return $out . '</div>' . ( $has_slider_controls ? '<button class="wps-home-slider-control wps-home-slider-next" type="button" data-wps-slider-next aria-label="' . esc_attr( 'studio' === $section_key ? 'Next studios' : 'Next actors' ) . '"><span aria-hidden="true">›</span></button></div>' : '' ) . '</div></section>';
	}

	private static function get_cards( $taxonomy, $limit ) {
		$s = wp_parse_args( WPS_Professional_Suite::settings(), self::default_settings() );
		$term_limit = 'actors' === $taxonomy ? 0 : $limit;
		$terms = WPS_Professional_Suite::get_display_terms( $taxonomy, $term_limit );
		if ( 'actors' === $taxonomy ) {
			$terms = self::filter_actor_terms_by_language( $terms, $limit, $s['home_actor_name_mode'] );
		} else {
			$terms = array_slice( $terms, 0, $limit );
		}
		if ( empty( $terms ) ) {
			return array();
		}
		$image_taxonomy = 'studio' === $taxonomy && isset( $terms[0]->taxonomy ) && 'actors' === $terms[0]->taxonomy ? 'actors' : $taxonomy;

		// Do not cache term image cards. After database/cache cleanup, taxonomy image meta
		// and post thumbnails must be read fresh from original KolorTube values.
		return self::cards_from_terms( $terms, $image_taxonomy );
	}

	private static function filter_actor_terms_by_language( array $terms, $limit, $mode ) {
		$lang = 'current' === $mode ? ( class_exists( 'WPS_Professional_Suite' ) ? WPS_Professional_Suite::current_language() : 'th' ) : $mode;
		if ( ! in_array( $lang, array( 'th', 'en' ), true ) ) {
			$lang = 'th';
		}
		$filtered = array();
		foreach ( $terms as $term ) {
			$has_thai = preg_match( '/[\x{0E00}-\x{0E7F}]/u', $term->name );
			if ( ( 'th' === $lang && $has_thai ) || ( 'en' === $lang && ! $has_thai ) ) { $filtered[] = $term; }
			if ( count( $filtered ) >= $limit ) { break; }
		}
		/*
		 * Fallback: if language filtering emptied the list while real terms
		 * exist (e.g. the site has mostly Latin-script actor names and the
		 * language is Thai), keep the section visible by using the unfiltered
		 * list instead of silently hiding the whole Actors homepage block.
		 */
		if ( empty( $filtered ) && ! empty( $terms ) ) {
			$filtered = array_slice( $terms, 0, $limit );
		}
		return $filtered;
	}


	private static function fallback_image_url() {
		if ( function_exists( 'wps_get_latest_site_image_url' ) ) {
			return wps_get_latest_site_image_url( 'video-thumb' );
		}
		return get_template_directory_uri() . '/img/no-thumb.png';
	}

	private static function cards_from_terms( array $terms, $taxonomy ) { $cards = array(); foreach ( $terms as $term ) { $url = get_term_link( $term ); if ( is_wp_error( $url ) ) { continue; } $cards[] = array( 'name' => $term->name, 'url' => $url, 'count' => absint( $term->count ), 'image' => self::term_image( $term, $taxonomy ) ); } return $cards; }
	private static function term_image( $term, $taxonomy ) { if ( function_exists( 'wps_get_term_best_image_url' ) ) { return wps_get_term_best_image_url( $term, $taxonomy, 'video-thumb' ); } return get_template_directory_uri() . '/img/no-thumb.png'; }
	private static function page_url_by_template( $template ) { $pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_wp_page_template', 'meta_value' => $template, 'no_found_rows' => true, 'suppress_filters' => false ) ); return empty( $pages[0] ) ? '' : get_permalink( $pages[0] ); }
	public static function flush_cache() { $version = absint( get_option( self::CACHE_VERSION_OPTION, 1 ) ); update_option( self::CACHE_VERSION_OPTION, $version + 1, false ); }

	public static function assets() {
		wp_register_style( 'wps-home-sections-front', false, array(), WPS_FRAMEWORK_VERSION );
		wp_enqueue_style( 'wps-home-sections-front' );
		$css = '.wps-home-section{padding:34px 0;background:var(--wps-soft-surface,rgba(255,255,255,.04));color:var(--wps-ui-text,#fff)}.wps-home-section-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px}.wps-home-eyebrow{display:inline-flex;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;border-radius:999px;padding:5px 10px;background:var(--wps-soft-surface,rgba(255,255,255,.1));border:1px solid var(--wps-soft-border,rgba(255,255,255,.12));color:var(--wps-ui-text,#fff)}.wps-home-section h2{margin:6px 0 0;font-weight:800;color:var(--wps-ui-text,#fff)}.wps-home-view-all{border-radius:999px;padding:9px 16px;background:var(--wps-view-all-background,linear-gradient(135deg,var(--wps-button-start,#4a90e2),var(--wps-button-end,#e87ab5)));color:var(--wps-view-all-text,#fff)!important;box-shadow:0 8px 22px var(--wps-view-all-shadow,rgba(0,0,0,.2));font-weight:800}.wps-home-view-all:hover{filter:brightness(1.08);transform:translateY(-1px)}.wps-home-card-grid{display:grid;grid-template-columns:repeat(var(--wps-home-cols-desktop,5),minmax(0,1fr));gap:18px;max-height:calc((230px + 62px + 18px) * var(--wps-home-rows,2));overflow:hidden}.wps-home-card{display:block;border-radius:14px;overflow:hidden;background:var(--wps-soft-surface,rgba(255,255,255,.08));color:var(--wps-ui-text,#fff);box-shadow:0 8px 22px rgba(0,0,0,.12);text-decoration:none;transition:.22s ease;border:1px solid var(--wps-soft-border,rgba(255,255,255,.08))}.wps-home-card:hover{transform:translateY(-4px);border-color:var(--wps-link-color,#4a90e2)}.wps-home-card-media{display:block;aspect-ratio:16/9;background:var(--wps-soft-surface,linear-gradient(135deg,rgba(74,144,226,.16),rgba(232,122,181,.2)));overflow:hidden;position:relative}.wps-home-card-media:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.08),transparent 38%);pointer-events:none}.wps-home-card-media.has-no-image{background:var(--wps-button-gradient,linear-gradient(135deg,var(--wps-button-start,#4a90e2),var(--wps-button-end,#e87ab5)))}.wps-home-card-media img{width:100%;height:100%;object-fit:cover;display:block;position:relative;z-index:1}.wps-home-card-copy{display:block;padding:12px;color:var(--wps-ui-text,#fff)}.wps-home-card-copy strong{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;color:var(--wps-ui-text,#fff)}.wps-home-card-copy small{opacity:.75;color:var(--wps-ui-text,#fff)}.wps-home-section.is-slider .wps-home-card-grid{display:flex;flex-wrap:nowrap;overflow-x:auto;scroll-snap-type:x mandatory;max-height:none;padding-bottom:8px}.wps-home-section.is-slider .wps-home-card{flex:0 0 calc((100% - (var(--wps-home-cols-desktop,5) - 1)*18px)/var(--wps-home-cols-desktop,5));scroll-snap-align:start;background:var(--wps-soft-surface,rgba(255,255,255,.08));color:var(--wps-ui-text,#fff)}.wps-home-section.is-slider .wps-home-card-copy{background:transparent;color:var(--wps-ui-text,#fff)}.wps-home-section.is-slider .wps-home-card-copy strong,.wps-home-section.is-slider .wps-home-card-copy small{color:var(--wps-ui-text,#fff)}.wps-home-seo-content{font-size:16px;line-height:1.75;color:var(--wps-ui-text,#fff)}.wps-home-seo-logo{padding:0;background:transparent;border:0}.wps-home-seo-logo-img{display:block;width:auto;max-width:280px;max-height:80px;object-fit:contain}.wps-home-seo-custom-image{margin:0 0 18px}.wps-home-seo-custom-image img{display:block;max-width:100%;height:auto;border-radius:14px}.wps-home-seo-image-wide img{width:100%;max-height:220px;object-fit:contain;background:var(--wps-soft-surface,rgba(255,255,255,.03))}.wps-home-seo-image-free img{width:100%;max-height:none;object-fit:contain}.wps-home-seo-layout-free .wps-home-section-heading,.wps-home-seo-layout-wide .wps-home-section-heading{margin-top:4px}.wps-home-seo-icon{font-size:11px}.wps-home-seo{padding-bottom:18px}.wps-home-sections{margin-bottom:0}@media(max-width:991px){.wps-home-card-grid{grid-template-columns:repeat(var(--wps-home-cols-tablet,3),minmax(0,1fr))}.wps-home-section.is-slider .wps-home-card{flex-basis:calc((100% - (var(--wps-home-cols-tablet,3) - 1)*18px)/var(--wps-home-cols-tablet,3))}}@media(max-width:575px){.wrapper{min-height:auto!important;padding-bottom:0!important}.video-loop{min-height:auto!important}.wps-home-seo-image-wide img{max-height:160px}.wps-home-section{padding:22px 0;background:var(--wps-soft-surface,rgba(255,255,255,.04))}.wps-home-card-grid{grid-template-columns:repeat(var(--wps-home-cols-mobile,2),minmax(0,1fr));gap:12px}.wps-home-section.is-slider .wps-home-card{flex-basis:calc((100% - (var(--wps-home-cols-mobile,2) - 1)*12px)/var(--wps-home-cols-mobile,2))}.wps-home-section-heading{align-items:flex-start}.wps-home-view-all{font-size:12px}.wps-home-seo-logo-img{max-width:220px;max-height:60px}.wps-home-section:last-child{padding-bottom:8px}#wrapper-footer{padding-top:10px!important;padding-bottom:max(14px,env(safe-area-inset-bottom))!important;margin-top:0!important}.site-info{margin-bottom:0!important}}';
		wp_add_inline_style( 'wps-home-sections-front', $css );
	}
}
