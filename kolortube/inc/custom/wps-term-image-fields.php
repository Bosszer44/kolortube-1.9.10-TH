<?php
/**
 * WPS term image fields for Studio and Post Tags.
 * Existing Category/Actors image fields stay unchanged; if no term image is set,
 * frontend cards use the latest post image in that taxonomy automatically.
 */
defined( 'ABSPATH' ) || exit;

final class WPS_Term_Image_Fields {
	private static $taxonomies = array(
		'studio'   => array( 'meta_key' => 'studio-image-id', 'label' => 'Studio image' ),
		'post_tag' => array( 'meta_key' => 'post_tag-image-id', 'label' => 'Tag image' ),
	);

	public static function register() {
		foreach ( self::$taxonomies as $taxonomy => $config ) {
			add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'add_field' ), 20, 1 );
			add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'edit_field' ), 20, 2 );
			add_action( 'created_' . $taxonomy, array( __CLASS__, 'save_field' ), 20, 1 );
			add_action( 'edited_' . $taxonomy, array( __CLASS__, 'save_field' ), 20, 1 );
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_footer-edit-tags.php', array( __CLASS__, 'inline_script' ) );
		add_action( 'admin_footer-term.php', array( __CLASS__, 'inline_script' ) );
	}

	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
		if ( isset( self::$taxonomies[ $taxonomy ] ) ) {
			wp_enqueue_media();
		}
	}

	public static function add_field( $taxonomy ) {
		$taxonomy = is_string( $taxonomy ) ? $taxonomy : '';
		$config = isset( self::$taxonomies[ $taxonomy ] ) ? self::$taxonomies[ $taxonomy ] : null;
		if ( ! $config ) { return; }
		?>
		<div class="form-field term-group wps-term-image-field">
			<label><?php esc_html_e( 'Image', 'wpst' ); ?></label>
			<input type="hidden" class="wps-term-image-id" name="<?php echo esc_attr( $config['meta_key'] ); ?>" value="">
			<div class="wps-term-image-preview"></div>
			<p>
				<button type="button" class="button wps-term-image-select"><?php esc_html_e( 'Add Image', 'wpst' ); ?></button>
				<button type="button" class="button wps-term-image-remove"><?php esc_html_e( 'Remove Image', 'wpst' ); ?></button>
			</p>
			<p class="description">ถ้าไม่เลือกรูป ระบบหน้าโฮมจะใช้รูปจากเรื่องล่าสุดในหมวด/แท็ก/ค่าย/นักแสดงนั้นโดยอัตโนมัติ</p>
		</div>
		<?php
	}

	public static function edit_field( $term, $taxonomy ) {
		$config = isset( self::$taxonomies[ $taxonomy ] ) ? self::$taxonomies[ $taxonomy ] : null;
		if ( ! $config ) { return; }
		$image_id = absint( get_term_meta( $term->term_id, $config['meta_key'], true ) );
		$fallback = '';
		if ( ! $image_id && function_exists( 'wps_get_term_best_image_url' ) ) {
			$fallback = wps_get_term_best_image_url( $term, $taxonomy, 'thumbnail' );
		}
		?>
		<tr class="form-field term-group-wrap wps-term-image-field">
			<th scope="row"><label><?php esc_html_e( 'Image', 'wpst' ); ?></label></th>
			<td>
				<input type="hidden" class="wps-term-image-id" name="<?php echo esc_attr( $config['meta_key'] ); ?>" value="<?php echo esc_attr( $image_id ); ?>">
				<div class="wps-term-image-preview">
					<?php if ( $image_id ) : ?>
						<?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?>
					<?php elseif ( $fallback ) : ?>
						<img src="<?php echo esc_url( $fallback ); ?>" alt="" style="max-width:150px;max-height:90px;opacity:.75">
						<p class="description">ภาพนี้เป็นภาพอัตโนมัติจากเรื่องล่าสุด ยังไม่ได้บันทึกเป็นรูปประจำ term</p>
					<?php endif; ?>
				</div>
				<p>
					<button type="button" class="button wps-term-image-select"><?php esc_html_e( 'Add Image', 'wpst' ); ?></button>
					<button type="button" class="button wps-term-image-remove"><?php esc_html_e( 'Remove Image', 'wpst' ); ?></button>
				</p>
				<p class="description">ถ้าไม่เลือกรูป ระบบหน้าโฮมจะใช้รูปจากเรื่องล่าสุดในหมวด/แท็ก/ค่าย/นักแสดงนั้นโดยอัตโนมัติ ไม่ปล่อยเป็นกล่องดำ</p>
			</td>
		</tr>
		<?php
	}

	public static function save_field( $term_id ) {
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		if ( ! isset( self::$taxonomies[ $taxonomy ] ) ) {
			return;
		}
		$meta_key = self::$taxonomies[ $taxonomy ]['meta_key'];
		$image_id = isset( $_POST[ $meta_key ] ) ? absint( wp_unslash( $_POST[ $meta_key ] ) ) : 0;
		update_term_meta( $term_id, $meta_key, $image_id );
	}

	public static function inline_script() {
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
		if ( ! isset( self::$taxonomies[ $taxonomy ] ) ) {
			return;
		}
		?>
		<script>
		jQuery(function($){
			$(document).on('click','.wps-term-image-select',function(e){
				e.preventDefault();
				var row=$(this).closest('.wps-term-image-field');
				var frame=wp.media({title:'เลือกภาพ',button:{text:'ใช้ภาพนี้'},multiple:false,library:{type:'image'}});
				frame.on('select',function(){
					var item=frame.state().get('selection').first().toJSON();
					var src=(item.sizes&&item.sizes.thumbnail)?item.sizes.thumbnail.url:item.url;
					row.find('.wps-term-image-id').val(item.id);
					row.find('.wps-term-image-preview').html('<img src="'+src+'" style="max-width:150px;max-height:90px" alt="">');
				});
				frame.open();
			});
			$(document).on('click','.wps-term-image-remove',function(e){
				e.preventDefault();
				var row=$(this).closest('.wps-term-image-field');
				row.find('.wps-term-image-id').val('');
				row.find('.wps-term-image-preview').empty();
			});
		});
		</script>
		<?php
	}
}

WPS_Term_Image_Fields::register();
