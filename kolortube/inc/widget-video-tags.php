<?php
// Register 'KOT Tags' widget
add_action( 'widgets_init', 'init_wpst_kolortube_video_tags' );
function init_wpst_kolortube_video_tags() {
	return register_widget( 'wpst_kolortube_video_tags' ); }

class wpst_kolortube_video_tags extends WP_Widget {
	/** constructor */
	function __construct() {
		parent::__construct(
			'wpst_kolortube_video_tags',
			$name = 'KolorTube Video Tags',
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

		// Widget options
		if ( array_key_exists( 'title', $instance ) ) {
			$title = apply_filters( 'widget_title', $instance['title'] ); // Title
		} else {
			$title = '';
		}
		if ( array_key_exists( 'taxonomy', $instance ) ) {
			$this_taxonomy = $instance['taxonomy']; // Taxonomy to show
		} else {
			$this_taxonomy = '';
		}
		$hierarchical = ! empty( $instance['hierarchical'] ) ? '1' : '0';
		$inv_empty    = ! empty( $instance['empty'] ) ? '0' : '1'; // invert to go from UI's "show empty" to WP's "hide empty"
		$showcount    = ! empty( $instance['count'] ) ? '1' : '0';
		if ( array_key_exists( 'tags_number', $instance ) ) {
			$tags_number = $instance['tags_number'];
		} else {
			$tags_number = 15;
		}
		if ( array_key_exists( 'orderby', $instance ) ) {
			$orderby = $instance['orderby'];
		} else {
			$orderby = 'count';
		}
		if ( array_key_exists( 'ascdsc', $instance ) ) {
			$ascdsc = $instance['ascdsc'];
		} else {
			$ascdsc = 'desc';
		}
		if ( array_key_exists( 'exclude', $instance ) ) {
			$exclude = $instance['exclude'];
		} else {
			$exclude = '';
		}
		if ( array_key_exists( 'tags_page_url', $instance ) ) {
			$tags_page_url = $instance['tags_page_url'];
		} else {
			$tags_page_url = get_bloginfo( 'url' ) . '/tags';
		}
		// if( array_key_exists('childof',$instance) ){
		// $childof = $instance['childof'];
		// }
		// else {
		// $childof = '';
		// }
		if ( array_key_exists( 'dropdown', $instance ) ) {
			$dropdown = $instance['dropdown'];
		} else {
			$dropdown = false;
		}
		// Dropdown doesn't work for built-in taxonomies.
		$builtin = array( 'post_tag', 'post_format', 'category' );
		if ( $dropdown && in_array( $this_taxonomy, $builtin ) ) {
			$dropdown = false;
		}
		// Output
		// $tax = $this_taxonomy;
		$tax             = 'post_tag';
		$taxonomy_object = get_taxonomy( $tax );

		// $before_title = '<h3 class="widget-title"><img src="' . get_template_directory_uri() . '/img/tag.svg" width="15" height="15" style="margin-right: 7px; position: relative; top: -1px;">';
		// $after_title = '</h3>';

		echo $before_widget;
		echo '<div id="wpst-video-tags-container" class="wpst-tag-filter-widget">';
		if ( $title ) {
			echo $before_title . $title . $after_title;
		}
		if ( $dropdown ) {
			if ( in_array( $tax, array( 'category', 'post_tag', 'post_format' ) ) ) {
				$walker = '';
			} else {
				$walker = new wpst_video_tags_Taxonomy_Dropdown_Walker();
			}
			$args = array(
				'show_option_all'  => false,
				'show_option_none' => '',
				'orderby'          => 'RANDOM()', // $orderby,
				'order'            => $ascdsc,
				'show_count'       => $showcount,
				'hide_empty'       => $inv_empty,
				// 'child_of'           => $childof,
				'exclude'          => $exclude,
				'tags_page_url'    => $tags_page_url,
				'number'           => $tags_number,
				'echo'             => 1,
				// 'selected'           => 0,
				'hierarchical'     => $hierarchical,
				'name'             => $taxonomy_object->query_var,
				'id'               => 'wpsttf-widget-' . $tax,
				// 'class'              => 'postform',
				'depth'            => 0,
				// 'tab_index'          => 0,
				'taxonomy'         => $tax,
				'hide_if_empty'    => true,
				'walker'           => $walker,
			);
			echo '<form action="' . get_bloginfo( 'url' ) . '" method="get">';
			wp_dropdown_categories( $args );
			echo '<input type="submit" value="go &raquo;" /></form>';
		} else {
			$args = array(
				'show_option_all'    => false,
				'orderby'            => $orderby,
				'order'              => $ascdsc,
				'style'              => 'list',
				'show_count'         => $showcount,
				'hide_empty'         => $inv_empty,
				'use_desc_for_title' => 1,
				// 'child_of'           => $childof,
				// 'feed'               => '',
				// 'feed_type'          => '',
				// 'feed_image'         => '',
				'exclude'            => $exclude,
				'tags_page_url'      => $tags_page_url,
				// 'exclude_tree'       => '',
				// 'include'            => '',
				'hierarchical'       => $hierarchical,
				'title_li'           => '',
				'show_option_none'   => '<small>No tags</small>',
				'number'             => $tags_number,
				'echo'               => 1,
				'depth'              => 0,
				// 'current_category'   => 0,
				// 'pad_counts'         => 0,
				'taxonomy'           => $tax,
				'walker'             => null,
			);
			echo '<ul id="wpst-video-tags">';
			wp_list_categories( $args );
			echo '<li><a class="see-all-tax" href="' . $tags_page_url . '">' . __( 'See all tags', 'wpst' ) . ' <i class="fa fa-chevron-right"></i></a></li>';
			echo '</ul>';
		}
		echo '</div>';
		echo $after_widget;
	}
	/** Widget control update */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title']         = strip_tags( $new_instance['title'] );
		$instance['tags_number']   = $new_instance['tags_number'];
		$instance['taxonomy']      = strip_tags( $new_instance['taxonomy'] );
		$instance['orderby']       = $new_instance['orderby'];
		$instance['ascdsc']        = $new_instance['ascdsc'];
		$instance['exclude']       = $new_instance['exclude'];
		$instance['tags_page_url'] = $new_instance['tags_page_url'];
		// $instance['expandoptions'] = $new_instance['expandoptions'];
		// $instance['childof'] = $new_instance['childof'];
		$instance['hierarchical'] = ! empty( $new_instance['hierarchical'] ) ? 1 : 0;
		$instance['empty']        = ! empty( $new_instance['empty'] ) ? 1 : 0;
		$instance['count']        = ! empty( $new_instance['count'] ) ? 1 : 0;
		$instance['dropdown']     = ! empty( $new_instance['dropdown'] ) ? 1 : 0;

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
			$title         = isset( $instance['title'] ) ? (string) $instance['title'] : false;
			$this_taxonomy = isset( $instance['taxonomy'] ) ? (string) $instance['taxonomy'] : false;
			$tags_number   = isset( $instance['tags_number'] ) ? (string) $instance['tags_number'] : false;
			$orderby       = isset( $instance['orderby'] ) ? (string) $instance['orderby'] : false;
			$ascdsc        = isset( $instance['ascdsc'] ) ? (string) $instance['ascdsc'] : false;
			$exclude       = isset( $instance['exclude'] ) ? (string) $instance['exclude'] : false;
			$tags_page_url = isset( $instance['tags_page_url'] ) ? (string) $instance['tags_page_url'] : false;
			// $expandoptions = isset($instance['expandoptions']) ? (bool) $instance['expandoptions'] :false;
			// $childof = isset($instance['childof']) ? (bool) $instance['childof'] :false;
			$showcount    = isset( $instance['count'] ) ? (bool) $instance['count'] : false;
			$hierarchical = isset( $instance['hierarchical'] ) ? (bool) $instance['hierarchical'] : false;
			$empty        = isset( $instance['empty'] ) ? (bool) $instance['empty'] : false;
			$dropdown     = isset( $instance['dropdown'] ) ? (bool) $instance['dropdown'] : false;
		} else {
			// These are our defaults
			$title         = '';
			$tags_number   = 15;
			$orderby       = 'count';
			$ascdsc        = 'desc';
			$exclude       = '';
			$tags_page_url = get_bloginfo( 'url' ) . '/tags';
			// $expandoptions  = 'contract';
			// $childof  = '';
			$this_taxonomy = 'category';// this will display the category taxonomy, which is used for normal, built-in posts
			$hierarchical  = false;
			$showcount     = false;
			$empty         = false;
			$dropdown      = false;
		}

		// The widget form ?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php echo __( 'Title:' ); ?></label>
				<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" class="widefat" />
			</p>			
			<?php /* <h4 class="wpsttf-expand-options"><a href="javascript:void(0)" onclick="lctwExpand('<?php echo $this->get_field_id('expandoptions'); ?>')" >More Options...</a></h4> */ ?>
			<div class="wpsttf-all-options">
				<?php
				/*
				<h4 class="wpsttf-contract-options"><a href="javascript:void(0)" onclick="lctwContract('<?php echo $this->get_field_id('expandoptions'); ?>')" >Hide Extended Options</a></h4>
				<input type="hidden" value="<?php echo $expandoptions; ?>" id="<?php echo $this->get_field_id('expandoptions'); ?>" name="<?php echo $this->get_field_name('expandoptions'); ?>" />
				<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>"<?php checked( $showcount ); ?> />
				<label for="<?php echo $this->get_field_id('count'); ?>"><?php _e( 'Show Post Counts' ); ?></label><br />
				<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('hierarchical'); ?>" name="<?php echo $this->get_field_name('hierarchical'); ?>"<?php checked( $hierarchical ); ?> />
				<label for="<?php echo $this->get_field_id('hierarchical'); ?>"><?php _e( 'Show Hierarchy' ); ?></label><br/>
				<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('empty'); ?>" name="<?php echo $this->get_field_name('empty'); ?>"<?php checked( $empty ); ?> />
				<label for="<?php echo $this->get_field_id('empty'); ?>"><?php _e( 'Show Empty Terms' ); ?></label></p> */
				?>

				<p>
					<label for="<?php echo $this->get_field_id( 'tags_number' ); ?>">Number of tags</label><br/>
					<input type="number" min="1" class="widefat" name="<?php echo $this->get_field_name( 'tags_number' ); ?>" value="<?php echo $tags_number; ?>" />
				</p>
				
				<p>
					<label for="<?php echo $this->get_field_id( 'orderby' ); ?>"><?php echo __( 'Order By:' ); ?></label>
					<select name="<?php echo $this->get_field_name( 'orderby' ); ?>" id="<?php echo $this->get_field_id( 'orderby' ); ?>" class="widefat" >
						<option value="ID" 
						<?php
						if ( $orderby == 'ID' ) {
							echo 'selected="selected"'; }
						?>
						>ID</option>
						<option value="name" 
						<?php
						if ( $orderby == 'name' ) {
							echo 'selected="selected"'; }
						?>
						>Name</option>
						<option value="slug" 
						<?php
						if ( $orderby == 'slug' ) {
							echo 'selected="selected"'; }
						?>
						>Slug</option>
						<option value="count" 
						<?php
						if ( $orderby == 'count' ) {
							echo 'selected="selected"'; }
						?>
						>Count</option>
						<option value="term_group" 
						<?php
						if ( $orderby == 'term_group' ) {
							echo 'selected="selected"'; }
						?>
						>Term Group</option>
					</select>
				</p>
				<p>
					<label><input type="radio" name="<?php echo $this->get_field_name( 'ascdsc' ); ?>" value="asc" 
																<?php
																if ( $ascdsc == 'asc' ) {
																	echo 'checked'; }
																?>
					/> Ascending</label><br/>
					<label><input type="radio" name="<?php echo $this->get_field_name( 'ascdsc' ); ?>" value="desc" 
																<?php
																if ( $ascdsc == 'desc' ) {
																	echo 'checked'; }
																?>
					/> Descending</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id( 'exclude' ); ?>">Exclude (comma-separated list of ids to exclude)</label><br/>
					<input type="text" class="widefat" name="<?php echo $this->get_field_name( 'exclude' ); ?>" value="<?php echo $exclude; ?>" />
				</p>
				<p>
					<label for="<?php echo $this->get_field_id( 'tags_page_url' ); ?>">Tags page URL</label><br/>
					<input type="text" class="widefat" name="<?php echo $this->get_field_name( 'tags_page_url' ); ?>" value="<?php echo $tags_page_url; ?>" />
				</p>
				<?php
				/*
				<p>
					<label for="<?php echo $this->get_field_id('exclude'); ?>">Only Show Children of (category id)</label><br/>
					<input type="text" class="widefat" name="<?php echo $this->get_field_name('childof'); ?>" value="<?php echo $childof; ?>" />
				</p> */
				?>
				<?php
				/*
				<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('dropdown'); ?>" name="<?php echo $this->get_field_name('dropdown'); ?>"<?php checked( $dropdown ); ?> />
				<label for="<?php echo $this->get_field_id('dropdown'); ?>"><?php _e( 'Display as Dropdown' ); ?></label></p> */
				?>
			</div>
		<?php
	}
} // class wpst_kolortube_video_tags

/* Custom version of Walker_CategoryDropdown */
class wpst_video_tags_Taxonomy_Dropdown_Walker extends Walker {
	var $tree_type = 'category';
	var $db_fields = array(
		'id'     => 'term_id',
		'parent' => 'parent',
	);

	function start_el( &$output, $term, $depth = 0, $args = array(), $current_object_id = 0 ) {
		$term      = get_term( $term, $term->taxonomy );
		$term_slug = $term->slug;

		$text = str_repeat( '&nbsp;', $depth * 3 ) . $term->name;
		if ( $args['show_count'] ) {
			$text .= '&nbsp;(' . $term->count . ')';
		}

		$class_name = 'level-' . $depth;

		$output .= "\t" . '<option' . ' class="' . esc_attr( $class_name ) . '" value="' . esc_attr( $term_slug ) . '">' . esc_html( $text ) . '</option>' . "\n";
	}
}
?>