<?php // hook into the init action and call create_topics_nonhierarchical_taxonomy when it fires
add_action( 'init', 'wpst_create_actors_taxonomy', 0 );
function wpst_create_actors_taxonomy() {
	// Labels part for the GUI
	$labels = array(
		'name'                       => _x( 'Actors', 'wpst' ),
		'singular_name'              => _x( 'Actor', 'wpst' ),
		'search_items'               => __( 'Search Actors', 'wpst' ),
		'popular_items'              => __( 'Popular Actors', 'wpst' ),
		'all_items'                  => __( 'All Actors', 'wpst' ),
		'parent_item'                => null,
		'parent_item_colon'          => null,
		'edit_item'                  => __( 'Edit Actor', 'wpst' ),
		'update_item'                => __( 'Update Actor', 'wpst' ),
		'add_new_item'               => __( 'Add New Actor', 'wpst' ),
		'new_item_name'              => __( 'New Actor Name', 'wpst' ),
		'separate_items_with_commas' => __( 'Separate Actors with commas', 'wpst' ),
		'add_or_remove_items'        => __( 'Add or remove Actors', 'wpst' ),
		'choose_from_most_used'      => __( 'Choose from the most used Actors', 'wpst' ),
		'menu_name'                  => __( 'Actors', 'wpst' ),
	);
	// Now register the non-hierarchical taxonomy like tag
	register_taxonomy(
		'actors',
		'post',
		array(
			'hierarchical'          => false,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_in_rest'          => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'actor' ),
		)
	);
}
