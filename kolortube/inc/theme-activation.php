<?php
/** Theme activation compatibility setup. */
defined( 'ABSPATH' ) || exit;

// Create pages upon theme activation.
if ( is_admin() ) {
	add_action( 'after_switch_theme', 'wpst_create_initial', 1, 1 );
	add_action( 'widgets_init', 'unregister_default_wp_widgets', 1 );
}


/**
 * Find a published page by exact title without using deprecated Core APIs.
 *
 * @param string $title Page title.
 * @return WP_Post|null
 */
function wpst_get_page_by_title_compat( $title ) {
	$pages = get_posts(
		array(
			'post_type'              => 'page',
			'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
			'title'                  => sanitize_text_field( $title ),
			'posts_per_page'         => 1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	return $pages ? $pages[0] : null;
}

/** Return a page ID by path, or zero when the page is unavailable. */
function wpst_get_page_id_by_path( $path ) {
	$page = get_page_by_path( $path );
	return $page instanceof WP_Post ? absint( $page->ID ) : 0;
}

/** Create a menu item only when the destination menu was created successfully. */
function wpst_safe_update_nav_menu_item( $menu_id, array $args ) {
	if ( ! $menu_id || is_wp_error( $menu_id ) ) {
		return 0;
	}
	$result = wp_update_nav_menu_item( absint( $menu_id ), 0, $args );
	return is_wp_error( $result ) ? 0 : absint( $result );
}

/**
 * Create initial theme stuff on license activation.
 *
 * @return void
 */
function wpst_create_initial() {
	$pages = array(
		// Page Title and URL (a blank space will end up becomeing a dash "-").
		'Categories' => array(
			'' => 'template-categories.php',
		),
		'Tags'       => array(
			'' => 'template-tags.php',
		),
	);
	foreach ( $pages as $page_url_title => $page_meta ) {
		$id = wpst_get_page_by_title_compat( $page_url_title );
		foreach ( $page_meta as $page_content => $page_template ) {
			$page = array(
				'post_type'    => 'page',
				'post_title'   => $page_url_title,
				'post_name'    => $page_url_title,
				'post_status'  => 'publish',
				'post_content' => $page_content,
				'post_author'  => 1,
				'post_parent'  => '',
			);
			if ( ! isset( $id->ID ) ) {
				$new_page_id = wp_insert_post( $page, true );
				if ( ! is_wp_error( $new_page_id ) && $new_page_id && ! empty( $page_template ) ) {
					update_post_meta( $new_page_id, '_wp_page_template', $page_template );
				}
			}
		}
	}
	// Create pages.
	$site_url       = get_site_url();
	$find           = array( 'http://', 'https://', 'http://www', 'https://www' );
	$replace        = '';
	$site_url_final = ucfirst( str_replace( $find, $replace, $site_url ) );

	// 18 U.S.C 2257.
	$page_2257 = array(
		'post_title'   => '18 U.S.C 2257',
		'post_content' => '<p>' . $site_url_final . ' is not a producer (primary or secondary) of any or all of the content found on the website. With respect to the records as per 18 USC 2257 for the content found on this site, please kindly direct your request to the site for which the content was produced.</p>
        <p>' . $site_url_final . ' is a live sex cam sharing site which allows the general viewing of various types of content.</p>
        <p>' . $site_url_final . ' abides by the following procedures to ensure compliance:</p>
        <p>We require all users to be 18+ years of age to upload videos.</p>
        <p>Users must affirm that they are 18+ years of age and affirm that they keep records of the videos in the content and that they are over 18 years of age.</p>',
		'post_status'  => 'publish',
		'post_author'  => 1,
		'post_type'    => 'page',
	);
	if ( null === wpst_get_page_by_title_compat( '18 U.S.C 2257' ) ) {
		wp_insert_post( $page_2257 );
	}

	// DMCA.
	$page_dmca = array(
		'post_title'   => 'DMCA',
		'post_content' => '<p>We comply with the Notice and Takedown requirements of 17 U.S.C. § 512 of the Digital Millennium Copyright Act (“DMCA”). This site qualifies as a “Service Provider” under the DMCA. Accordingly, it is entitled to certain protections from claims of copyright infringement, commonly referred to as the “safe harbor” provisions. We therefore affirm the following Notice and Takedown Policy relating to claims of copyright infringement by our users.</p>
        <h3>Notice of Claimed Infringement:</h3>
        <p>If you believe that your work has been copied in a way that constitutes copyright infringement, please provide us with the following information:</p>
        <p>(a) an electronic or physical signature of the person authorized to act on behalf of the owner of the copyright or other intellectual property interest;</p>
        <p>(b) description of the copyrighted work or other intellectual property that you claim has been infringed;</p>
        <p>(c) your address, telephone number, and email address;</p>
        <p>(d) a statement by you that you have a good faith belief that the disputed use is not authorized by the copyright owner, its agent, or the law;</p>
        (f) a statement by you, made under penalty of perjury, that the above information in your notice is accurate and that you are the copyright or intellectual property owner or authorized to act on the copyright or intellectual property owner’s behalf.</p>
        <h3>Take Down Procedure</h3>
        <p>We reserve the right at any time to remove any material or activity on our site and material claimed to be infringing or based on facts or circumstances from which infringing activity is apparent. It is our policy to terminate the account of repeat copyright infringers, when appropriate, and we will act expeditiously to remove access to all material that infringes on another’s copyright, according to the procedure set forth in 17 U.S.C. §512 of the Digital Millennium Copyright Act (“DMCA”).</p>
        <p>We reserve the right to modify, alter or add to this policy, and all users should regularly check back to these terms and conditions to stay current with any such changes.</p>',
		'post_status'  => 'publish',
		'post_author'  => 1,
		'post_type'    => 'page',
	);
	if ( null === wpst_get_page_by_title_compat( 'DMCA' ) ) {
		wp_insert_post( $page_dmca );
	}

	// Privacy Policy.
	$page_privacy_policy = array(
		'post_title'   => 'Privacy Policy',
		'post_content' => '<p>This document details important information regarding the use and disclosure of User Data collected on our site.</p>
        <p>This site expressly and strictly limits its membership and/or viewing privileges to adults 18+years of age. All persons who do not meet its criteria are strictly forbidden from accessing or viewing the contents of this site. We do not knowingly seek or collect any personal information or data from persons who have not attained the age of majority.</p>
        <h3>DATA COLLECTED</h3>
        <p>Users can watch videos without registering and without any information being collected and processed.</p>
        <p>Registration is required for uploading videos, and accessing other features.</p>
        <p>Information collected is: username, email address, year of birth.</p>
        <p>Cookies: When you visit our site, we may send one or more cookies to your computer that uniquely identifies your browser session. We use both session cookies and persistent cookies. If you remove your persistent cookie, some of the site’s features may not function properly.</p>
        <p>Log File Information: When you visit our site, our servers automatically record certain information that your web browser sends such as your IP address, browser type, browser language, referring URL, platform type, domain name and the date and time of your request.</p>
        <p>Email address: If you contact us, we may keep a record of that correspondence.</p>
        <h3>USES</h3>
        <p>Any videos that you submit to us may be redistributed through the internet and other media channels, and may be viewed by the general public.</p>
        <p>We do not use your email address or other personally identifiable information to send commercial or marketing messages without your consent.</p>
        <p>We may use your email address for non-marketing administrative purposes.</p>
        <p>We analyze aggregated user traffic information to help streamline our hosting operations and to improve the quality of the user-experience.</p>
        <h3>DISCLOSURE OF INFORMATION</h3>
        <p>We do not share your personal information (such as name or email address) with other, third-party companies.</p>
        <h3>SECURITY</h3>
        <p>You are responsible for keeping your password confidential. We ask you not to share your password with anyone.</p>',
		'post_status'  => 'publish',
		'post_author'  => 1,
		'post_type'    => 'page',
	);
	if ( null === wpst_get_page_by_title_compat( 'Privacy Policy' ) ) {
		wp_insert_post( $page_privacy_policy );
	}

	// Terms of Use.
	$page_terms_of_use = array(
		'post_title'   => 'Terms of Use',
		'post_content' => '<p>By using or visiting our site, you agree to the terms and conditions contained herein and all future amendments and modifications.</p>
        <p>These terms and conditions are subject to change at any time and you agree be bound by all modifications, changes and revisions. If you do not agree, then don’t use our site.</p>
        <p>Our website allows for uploading, sharing and general viewing various types of content allowing registered and unregistered users to share and view adult content, including sexually explicit images and video.</p>
        <p>The website may also contain certain links to third party websites which are in no way owned or controlled by us. We assume no responsibility for the content, privacy policies, practices of any third party websites. We cannot censor or edit the content of third party sites. You acknowledge that we will not be liable for any liability arising from your use of any third-party website.</p>
        <p>You affirm that you are at least eighteen (18) years of age and/or over the age of majority in the jurisdiction you reside and from which you access the website if the age of majority is greater than eighteen (18) years of age. If you are under the age of 18 and/or under the age of majority in the jurisdiction you reside and from which you access the website, then you are not permitted to use the website.</p>
        <p>You agree that you will not post any content that is illegal, unlawful, harassing, harmful, threatening, abusive, defamatory, obscene, libelous, hateful, or racial.</p>
        <p>You also agree that you shall not post, upload or publish any material that contains viruses or any code designed to destroy, interrupt, limit the functionality of, or monitor any computer.</p>
        <p>You agree that you will not post, upload nor publish content which is intentionally or unintentionally violating any applicable local, state, national, or international law.</p>
        <p>You agree that you will not post, upload or publish content depicting illegal activity nor depict any act of cruelty to animals; You agree not to use our site in any way that might expose us to criminal or civil liability.</p>
        <p>The content on our site cannot be used, copied, reproduced, distributed, transmitted, broadcast, displayed, sold, licensed, or otherwise exploited for any other purpose whatsoever without or prior written consent.</p>
        <p>In submitting a video to our site, you agree that you will not submit material that is copyrighted or subject to third party proprietary rights, nor submit material that is obscene, illegal, unlawful, , defamatory, libelous, harassing, hateful or encourages conduct that would be considered a criminal offense.</p>',
		'post_status'  => 'publish',
		'post_author'  => 1,
		'post_type'    => 'page',
	);
	if ( null === wpst_get_page_by_title_compat( 'Terms of Use' ) ) {
		wp_insert_post( $page_terms_of_use );
	}

	// Create primary menu.
	$primary_menu_name     = 'Main Menu';
	$primary_menu_location = 'wpst-primary-menu';
	$primary_menu_exists   = wp_get_nav_menu_object( $primary_menu_name );
	if ( ! $primary_menu_exists ) {
		$primary_menu_id = wp_create_nav_menu( $primary_menu_name );
		wpst_safe_update_nav_menu_item(
			$primary_menu_id,
			array(
				'menu-item-title'  => __( 'Home', 'wpst' ),
				'menu-item-url'    => home_url(),
				'menu-item-status' => 'publish',
			)
		);
		wpst_safe_update_nav_menu_item(
			$primary_menu_id,
			array(
				'menu-item-title'     => __( 'Categories', 'wpst' ),
				'menu-item-object'    => 'page',
				'menu-item-object-id' => wpst_get_page_id_by_path( 'categories' ),
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		wpst_safe_update_nav_menu_item(
			$primary_menu_id,
			array(
				'menu-item-title'     => __( 'Tags', 'wpst' ),
				'menu-item-object'    => 'page',
				'menu-item-object-id' => wpst_get_page_id_by_path( 'tags' ),
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);

		// Get all locations (including the one we just created above)
		if ( ! is_wp_error( $primary_menu_id ) && $primary_menu_id ) {
			$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
			// Set the menu to the new location and save into the database.
			$locations[ $primary_menu_location ] = absint( $primary_menu_id );
			set_theme_mod( 'nav_menu_locations', $locations );
		}
	}

	// Create footer menu.
	$footer_menu_name     = 'Footer Menu';
	$footer_menu_location = 'wpst-footer-menu';
	// Does the menu exist already?
	$footer_menu_exists = wp_get_nav_menu_object( $footer_menu_name );
	// If it doesn't exist, let's create it.
	if ( $footer_menu_exists && ! is_wp_error( $footer_menu_exists ) ) {
		$footer_menu_id = absint( $footer_menu_exists->term_id );
	} else {
		$footer_menu_id = wp_create_nav_menu( $footer_menu_name );
		wpst_safe_update_nav_menu_item(
			$footer_menu_id,
			array(
				'menu-item-title'     => __( '18 U.S.C 2257', 'wpst' ),
				'menu-item-object'    => 'page',
				'menu-item-object-id' => wpst_get_page_id_by_path( '18-u-s-c-2257' ),
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		wpst_safe_update_nav_menu_item(
			$footer_menu_id,
			array(
				'menu-item-title'     => __( 'DMCA', 'wpst' ),
				'menu-item-object'    => 'page',
				'menu-item-object-id' => wpst_get_page_id_by_path( 'dmca' ),
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		wpst_safe_update_nav_menu_item(
			$footer_menu_id,
			array(
				'menu-item-title'     => __( 'Privacy Policy', 'wpst' ),
				'menu-item-object'    => 'page',
				'menu-item-object-id' => wpst_get_page_id_by_path( 'privacy-policy' ),
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		wpst_safe_update_nav_menu_item(
			$footer_menu_id,
			array(
				'menu-item-title'     => __( 'Terms of Use', 'wpst' ),
				'menu-item-object'    => 'page',
				'menu-item-object-id' => wpst_get_page_id_by_path( 'terms-of-use' ),
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
	}

	if ( ! has_nav_menu( $footer_menu_location ) && ! is_wp_error( $footer_menu_id ) && $footer_menu_id ) {
		$locations                          = (array) get_theme_mod( 'nav_menu_locations', array() );
		$locations[ $footer_menu_location ] = absint( $footer_menu_id );
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	// Preserve existing widget assignments. Defaults are added only when absent.

	$sidebar_1 = array(
		'title'       => __( 'Filters', 'wpst' ),
		'newest'      => 1,
		'popular'     => 1,
		'most-viewed' => 1,
		'longest'     => 1,
		'random'      => 1,
	);

	$sidebar_2 = array(
		'title'               => __( 'Categories', 'wpst' ),
		'categories_number'   => '15',
		'orderby'             => 'count',
		'ascdsc'              => 'desc',
		'categories_page_url' => $site_url . '/categories',
	);

	$sidebar_3 = array(
		'title'         => __( 'Tags', 'wpst' ),
		'tags_number'   => '15',
		'orderby'       => 'count',
		'ascdsc'        => 'desc',
		'tags_page_url' => $site_url . '/tags',
	);

	wpst_add_widget_theme_activation( 'sidebar', 'wpst_kolortube_video_filters', 1, $sidebar_1 );
	wpst_add_widget_theme_activation( 'sidebar', 'wpst_kolortube_video_categories', 2, $sidebar_2 );
	wpst_add_widget_theme_activation( 'sidebar', 'wpst_kolortube_video_tags', 3, $sidebar_3 );
}

// unregister all default WP Widgets.
function unregister_default_wp_widgets() {
	unregister_widget( 'WP_Widget_Pages' );
	unregister_widget( 'WP_Widget_Calendar' );
	unregister_widget( 'WP_Widget_Archives' );
	unregister_widget( 'WP_Widget_Links' );
	unregister_widget( 'WP_Widget_Meta' );
	unregister_widget( 'WP_Widget_Search' );
	unregister_widget( 'WP_Widget_Categories' );
	unregister_widget( 'WP_Widget_Recent_Posts' );
	unregister_widget( 'WP_Widget_Recent_Comments' );
	unregister_widget( 'WP_Widget_RSS' );
	unregister_widget( 'WP_Widget_Tag_Cloud' );
}

function wpst_add_widget_theme_activation( $sidebar_id, $widget_type = 'videos_block', $widget_id = 0, $args = array() ) {
	$widget_id       = absint( $widget_id );
	$sidebars_widgets = get_option( 'sidebars_widgets', array() );
	$ops              = get_option( 'widget_' . $widget_type, array() );

	if ( ! is_array( $sidebars_widgets ) ) {
		$sidebars_widgets = array();
	}
	if ( ! isset( $sidebars_widgets[ $sidebar_id ] ) || ! is_array( $sidebars_widgets[ $sidebar_id ] ) ) {
		$sidebars_widgets[ $sidebar_id ] = array();
	}
	if ( ! is_array( $ops ) ) {
		$ops = array();
	}

	$widget_key = $widget_type . '-' . $widget_id;
	if ( ! in_array( $widget_key, $sidebars_widgets[ $sidebar_id ], true ) ) {
		$sidebars_widgets[ $sidebar_id ][] = $widget_key;
	}

	// Do not overwrite a widget instance already customized by the site owner.
	if ( ! isset( $ops[ $widget_id ] ) || ! is_array( $ops[ $widget_id ] ) ) {
		$ops[ $widget_id ] = $args;
	}
	$ops['_multiwidget'] = 1;

	update_option( 'widget_' . $widget_type, $ops );
	update_option( 'sidebars_widgets', $sidebars_widgets );
}
