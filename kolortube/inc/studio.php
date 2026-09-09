<?php

add_action( 'init', 'wpst_create_studio_taxonomy', 0 );

function wpst_create_studio_taxonomy() {

    $labels = array(
        'name'                       => _x( 'Studios', 'wpst' ),
        'singular_name'              => _x( 'Studio', 'wpst' ),
        'search_items'               => __( 'Search Studios', 'wpst' ),
        'popular_items'              => __( 'Popular Studios', 'wpst' ),
        'all_items'                  => __( 'All Studios', 'wpst' ),
        'parent_item'                => null,
        'parent_item_colon'          => null,
        'edit_item'                  => __( 'Edit Studio', 'wpst' ),
        'update_item'                => __( 'Update Studio', 'wpst' ),
        'add_new_item'               => __( 'Add New Studio', 'wpst' ),
        'new_item_name'              => __( 'New Studio Name', 'wpst' ),
        'separate_items_with_commas' => __( 'Separate Studios with commas', 'wpst' ),
        'add_or_remove_items'        => __( 'Add or remove Studios', 'wpst' ),
        'choose_from_most_used'      => __( 'Choose from the most used Studios', 'wpst' ),
        'menu_name'                  => __( 'Studios', 'wpst' ),
    );

    register_taxonomy(
        'studio',
        'post',
        array(
            'hierarchical'          => false,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_in_rest'          => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'             => true,
            'rewrite'               => array(
                'slug' => 'studio'
            ),
        )
    );
}