<?php
/** Legacy admin presentation moved from functions.php. */
defined( 'ABSPATH' ) || exit;

final class WPS_Admin_UI {
	public static function change_post_label() {
		global $menu, $submenu;
		if ( isset( $menu[5][0] ) ) {
			$menu[5][0] = __( 'Videos', 'wpst' );
		}
		if ( isset( $submenu['edit.php'] ) ) {
			$submenu['edit.php'][5][0]  = __( 'Videos', 'wpst' );
			$submenu['edit.php'][10][0] = __( 'Add Video', 'wpst' );
			$submenu['edit.php'][15][0] = __( 'Video Categories', 'wpst' );
			$submenu['edit.php'][16][0] = __( 'Video Tags', 'wpst' );
		}
	}

	public static function change_post_object() {
		global $wp_post_types;
		if ( empty( $wp_post_types['post']->labels ) ) {
			return;
		}
		$labels = $wp_post_types['post']->labels;
		$map = array(
			'name' => 'Videos', 'singular_name' => 'Video', 'add_new' => 'Add Video',
			'add_new_item' => 'Add Video', 'edit_item' => 'Edit Video', 'new_item' => 'Video',
			'view_item' => 'View Video', 'search_items' => 'Search Videos',
			'not_found' => 'No Videos found', 'not_found_in_trash' => 'No Videos found in Trash',
			'all_items' => 'All Videos', 'menu_name' => 'Videos', 'name_admin_bar' => 'Video',
		);
		foreach ( $map as $property => $label ) {
			$labels->{$property} = __( $label, 'wpst' );
		}
	}

	public static function change_taxonomy_object( $taxonomy, $singular, $plural ) {
		global $wp_taxonomies;
		if ( empty( $wp_taxonomies[ $taxonomy ]->labels ) ) {
			return;
		}
		$labels = $wp_taxonomies[ $taxonomy ]->labels;
		$labels->name               = $plural;
		$labels->singular_name      = $singular;
		$labels->add_new            = sprintf( __( 'Add %s', 'wpst' ), $singular );
		$labels->add_new_item       = sprintf( __( 'Add %s', 'wpst' ), $singular );
		$labels->edit_item          = sprintf( __( 'Edit %s', 'wpst' ), $singular );
		$labels->new_item           = $singular;
		$labels->view_item          = sprintf( __( 'View %s', 'wpst' ), $singular );
		$labels->search_items       = sprintf( __( 'Search %s', 'wpst' ), $plural );
		$labels->not_found          = sprintf( __( 'No %s found', 'wpst' ), $plural );
		$labels->not_found_in_trash = sprintf( __( 'No %s found in Trash', 'wpst' ), $plural );
		$labels->all_items          = sprintf( __( 'All %s', 'wpst' ), $plural );
		$labels->menu_name          = $singular;
		$labels->name_admin_bar     = $singular;
	}

	public static function admin_menu_icon_css() {
		echo '<style>#menu-posts .dashicons-admin-post::before,#menu-posts .dashicons-format-standard::before{content:"\\f236";}</style>';
	}

	public static function rss_post_thumbnail( $content ) {
		$post_id = get_the_ID();
		if ( $post_id && has_post_thumbnail( $post_id ) ) {
			$content = '<p>' . get_the_post_thumbnail( $post_id ) . '</p>' . $content;
		}
		return $content;
	}

	public static function remove_admin_bar() {
		if ( ! current_user_can( 'administrator' ) && ! is_admin() && function_exists( 'xbox_get_field_value' ) && 'off' === xbox_get_field_value( 'wpst-options', 'display-admin-bar' ) ) {
			show_admin_bar( false );
			remove_action( 'wp_head', '_admin_bar_bump_cb' );
		}
	}

	public static function comment_form_defaults( $fields ) {
		$fields['must_log_in'] = '<p class="must-log-in">' . wp_kses_post( __( 'You must be <a href="#wpst-login">logged in</a> to post a comment.', 'wpst' ) ) . '</p>';
		return $fields;
	}

	public static function add_video_columns( $columns ) {
		$columns['focuskw']   = __( 'Focus Keyphrase', 'wpst' );
		$columns['metadesc']  = __( 'Meta Desc', 'wpst' );
		$columns['permalink'] = __( 'URL', 'wpst' );
		return $columns;
	}

	public static function render_video_column( $column, $post_id ) {
		switch ( $column ) {
			case 'focuskw':
				echo esc_html( get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
				break;
			case 'metadesc':
				echo esc_html( wp_trim_words( get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ), 12 ) );
				break;
			case 'permalink':
				echo '<a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open link', 'wpst' ) . '</a>';
				break;
		}
	}

	public static function widgets_preview_assets() {
		?>
		<style>
		.wp-block-legacy-widget__edit-preview{box-shadow:inset 0 0 0 1px #666;min-height:50px;padding:11px}.wp-block-legacy-widget__edit-preview h3.edit-h3{color:#000;font:600 14px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}.wp-block-legacy-widget__edit-preview h3.edit-h3 span{font-size:12px;font-weight:400;color:#666;margin-left:6px}
		</style>
		<script>
		jQuery(function($){function styleLegacyWidgets(){$('.wp-block-legacy-widget__edit-preview:not([data-styled])').each(function(){var $preview=$(this),title=$preview.siblings('.wp-block-legacy-widget__edit-form').find('h3.wp-block-legacy-widget__edit-form-title').text();if(title){$preview.prepend($('<h3/>',{'class':'edit-h3'}).text(title).append($('<span/>').text(' (Legacy widget)')));}$preview.attr('data-styled','true');});}styleLegacyWidgets();if(window.MutationObserver){new MutationObserver(styleLegacyWidgets).observe(document.body,{childList:true,subtree:true});}else{window.setInterval(styleLegacyWidgets,1000);}});
		</script>
		<?php
	}
}
