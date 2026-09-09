<?php
// Register 'KOT Filters' widget
add_action( 'widgets_init', 'init_wpst_kolortube_video_filters' );
function init_wpst_kolortube_video_filters() {
	return register_widget( 'wpst_kolortube_video_filters' ); }

class wpst_kolortube_video_filters extends WP_Widget {
	/** constructor */
	function __construct() {
		parent::__construct(
			'wpst_kolortube_video_filters',
			$name = 'KolorTube Video Filters',
			array(
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * This is the Widget
	 **/
	function widget( $args, $instance ) {
		global $post;
		extract( $args );

		$home_url = home_url( '/' );

		// Widget options
		if ( array_key_exists( 'title', $instance ) ) {
			$title = apply_filters( 'widget_title', $instance['title'] ); // Title
		} else {
			$title = '';
		}
		$newest     = ! empty( $instance['newest'] ) ? '1' : '0';
		$popular    = ! empty( $instance['popular'] ) ? '1' : '0';
		$mostviewed = ! empty( $instance['most-viewed'] ) ? '1' : '0';
		$longest    = ! empty( $instance['longest'] ) ? '1' : '0';
		$random     = ! empty( $instance['random'] ) ? '1' : '0';

		// $hierarchical = !empty( $instance['hierarchical'] ) ? '1' : '0';
		// $inv_empty = !empty( $instance['empty'] ) ? '0' : '1';
		// $showcount = !empty( $instance['count'] ) ? '1' : '0';

		// Output

		// $before_title = '<h3 class="widget-title"><img src="' . get_template_directory_uri() . '/img/filter.svg" width="15" height="15" style="margin-right: 7px; position: relative; top: -1px;">';
		// $after_title = '</h3>';

		echo $before_widget;
		echo '<div id="wpst-video-filters-container" class="wpst-tag-filter-widget">';
		if ( $title ) {
			echo $before_title . $title . $after_title;
		}
			echo '<ul id="wpst-video-filters">';
		if ( $newest ) {
			echo '<li><a class="' . wpst_selected_filter( 'latest' ) . '" href="' . $home_url . '?filter=latest">' . __( 'Newest', 'wpst' ) . '</a></li>';
		}
		if ( $popular ) {
			echo '<li><a class="' . wpst_selected_filter( 'popular' ) . '" href="' . $home_url . '?filter=popular">' . __( 'Popular', 'wpst' ) . '</a></li>';
		}
		if ( $mostviewed ) {
			echo '<li><a class="' . wpst_selected_filter( 'most-viewed' ) . '" href="' . $home_url . '?filter=most-viewed">' . __( 'Most viewed', 'wpst' ) . '</a></li>';
		}
		if ( $longest ) {
			echo '<li><a class="' . wpst_selected_filter( 'longest' ) . '" href="' . $home_url . '?filter=longest">' . __( 'Longest', 'wpst' ) . '</a></li>';
		}
		if ( $random ) {
			echo '<li><a class="' . wpst_selected_filter( 'random' ) . '" href="' . $home_url . '?filter=random">' . __( 'Random', 'wpst' ) . '</a></li>';
		}
			echo '</ul>';
		echo '</div>';
		echo $after_widget;
	}
	/** Widget control update */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title']       = strip_tags( $new_instance['title'] );
		$instance['newest']      = ! empty( $new_instance['newest'] ) ? 1 : 0;
		$instance['popular']     = ! empty( $new_instance['popular'] ) ? 1 : 0;
		$instance['most-viewed'] = ! empty( $new_instance['most-viewed'] ) ? 1 : 0;
		$instance['longest']     = ! empty( $new_instance['longest'] ) ? 1 : 0;
		$instance['random']      = ! empty( $new_instance['random'] ) ? 1 : 0;
		return $instance;
	}

	/**
	 * Widget settings
	 **/
	function form( $instance ) {
		// for showing/hiding advanced options; WordPress moves this script to where it needs to go
			/*
			wp_enqueue_script('jquery');
			?><script>
			jQuery(document).ready(function(){
				var status = jQuery('#<?php echo $this->get_field_id('expandoptions'); ?>').val();
				if ( status === 'expand' ) {
					jQuery('.wpsttf-expand-options').hide();
					jQuery('.wpsttf-all-options').show();
				} else {
					jQuery('.wpsttf-all-options').hide();
				}
			});
			function lctwExpand(id){
				jQuery('#' + id).val('expand');
				jQuery('.wpsttf-all-options').show(500);
				jQuery('.wpsttf-expand-options').hide(500);
			}
			function lctwContract(id){
				jQuery('#' + id).val('contract');
				jQuery('.wpsttf-all-options').hide(500);
				jQuery('.wpsttf-expand-options').show(500);
			}
			</script> */
			// instance exist? if not set defaults
		if ( $instance ) {
			$title      = isset( $instance['title'] ) ? (string) $instance['title'] : false;
			$newest     = isset( $instance['newest'] ) ? (bool) $instance['newest'] : false;
			$popular    = isset( $instance['popular'] ) ? (bool) $instance['popular'] : false;
			$mostviewed = isset( $instance['most-viewed'] ) ? (bool) $instance['most-viewed'] : false;
			$longest    = isset( $instance['longest'] ) ? (bool) $instance['longest'] : false;
			$random     = isset( $instance['random'] ) ? (bool) $instance['random'] : false;
		} else {
			// These are our defaults
			$title      = '';
			$newest     = true;
			$popular    = true;
			$mostviewed = true;
			$longest    = true;
			$random     = true;
		}

		// The widget form ?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php echo __( 'Title:' ); ?></label>
				<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" class="widefat" />
			</p>
			<div class="wpsttf-all-options">
				<p>
					<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'newest' ); ?>" name="<?php echo $this->get_field_name( 'newest' ); ?>" <?php checked( $newest ); ?> />
					<label for="<?php echo $this->get_field_id( 'newest' ); ?>"><?php _e( 'Show newest filter' ); ?></label>
				</p>
				<p>
					<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'popular' ); ?>" name="<?php echo $this->get_field_name( 'popular' ); ?>" <?php checked( $popular ); ?> />
					<label for="<?php echo $this->get_field_id( 'popular' ); ?>"><?php _e( 'Show popular filter' ); ?></label>
				</p>
				<p>
					<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'most-viewed' ); ?>" name="<?php echo $this->get_field_name( 'most-viewed' ); ?>" <?php checked( $mostviewed ); ?> />
					<label for="<?php echo $this->get_field_id( 'most-viewed' ); ?>"><?php _e( 'Show most viewed filter' ); ?></label>
				</p>
				<p>
					<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'longest' ); ?>" name="<?php echo $this->get_field_name( 'longest' ); ?>" <?php checked( $longest ); ?> />
					<label for="<?php echo $this->get_field_id( 'longest' ); ?>"><?php _e( 'Show longest filter' ); ?></label>
				</p>
				<p>
					<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'random' ); ?>" name="<?php echo $this->get_field_name( 'random' ); ?>" <?php checked( $random ); ?> />
					<label for="<?php echo $this->get_field_id( 'random' ); ?>"><?php _e( 'Show random filter' ); ?></label>
				</p>

			</div>
		<?php
	}
}
