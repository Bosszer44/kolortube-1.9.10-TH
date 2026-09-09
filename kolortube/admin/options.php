<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'WPSCORE-tabs', 'wpst_add_admin_navigation' );

function wpst_add_admin_navigation( $nav ) {
	if ( function_exists( 'WPSCORE' ) ) {
		try {
			$dynamic_tab = WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'add_tab' );
			if ( is_string( $dynamic_tab ) && '' !== trim( $dynamic_tab ) ) {
				eval( $dynamic_tab ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- trusted WP-Script Core compatibility payload.
			}
		} catch ( Throwable $error ) {
			// Keep the Theme options page usable even when the vendor payload is unavailable or malformed.
		}
	}
	if ( isset( $wpst_nav ) && is_array( $wpst_nav ) ) {
		$nav[1] = $wpst_nav;
	}
	return $nav;
}

add_filter( 'wpst-options', 'wpst_options_page' );

function wpst_options_page( $options_table ) {

	$output = '<style>
		.xbox-header-actions,
		#wp-script .xbox-separator { display: none!important; }
		#wp-script .xbox.xbox-boxed .xbox-row:not(.xbox-row-mixed):first-child { margin-top: 0; }
		#wp-script .xbox .xbox-type-title .xbox-content { padding: 0 0 30px; }
		#wp-script .xbox-row .xbox-content { border-left: none; }
		#wp-script .xbox-row .xbox-field-description,
		#wp-script .xbox-row .xbox-field-description a { font-size: 16px; font-style: normal; }
	</style>';

	$output .= '<div id="wp-script">
					<div class="content-tabs">';

	$output .= WPSCORE()->display_logo( false );
	$output .= WPSCORE()->display_tabs( false );

	$output .= '<div class="tab-content tab-options">';

	$output .= $options_table;

	$output .= '</div>';
	$output .= '</div>';

	$output .= '</div>';
	$output .= '</div>';

	$output .= WPSCORE()->display_footer( false );
	$output .= '</div>';

	return $output;
}

add_action( 'xbox_init', 'wpst_options' );
function wpst_options() {
	$icon_slug = wp_get_theme()->get( 'Template' )
		? strtolower( wp_get_theme()->get( 'Template' ) )
		: strtolower( wp_get_theme()->get( 'Name' ) );

	$options = array(
		'id'         => 'wpst-options',
		'title'      => esc_html__( 'Theme Options', 'wpst' ),
		'menu_title' => esc_html__( 'Theme Options', 'wpst' ),
		'skin'       => 'pink',
		'layout'     => 'boxed',
		'header'     => array(
			'name' => '<img src="https://www.wp-script.com/wp-content/uploads/wps-img/products/themes/icons/' . $icon_slug . '-icon.svg" width="20"> ' . wp_get_theme()->get( 'Name' ),
		),
		'capability' => 'edit_published_posts',
	);

	$xbox = xbox_new_admin_page( $options );

	$xbox->add_main_tab(
		array(
			'name'  => esc_html__( 'Main tab', 'wpst' ),
			'id'    => 'main-tab',
			'items' => array(
				'general' => '',
			),
		)
	);
		/*******************/
		/***** GENERAL */
		/*******************/
		$xbox->open_tab_item( 'general' );
			$xbox->add_field(
				array(
					'id'   => 'customizer',
					'name' => '',
					'type' => 'title',
					'desc' => sprintf( __( '<a href="%s">Click here</a> to go to the Customizer.', 'wpst' ), get_admin_url() . 'customize.php' ),
				)
			);
		$xbox->close_tab_item( 'general' );

	$xbox->close_tab( 'main-tab' );
}
