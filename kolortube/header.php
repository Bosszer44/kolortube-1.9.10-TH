<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<!-- CSS, scripts, SEO and guarded custom head snippets are rendered through wp_head(). -->
	<?php wp_head(); ?>
</head>

<body <?php body_class('wps-header-split-on'); ?>>
<div class="body-gradient"></div>
<?php do_action( 'wp_body_open' ); ?>
<div class="site" id="page">

	<!-- ******************* The Navbar Area ******************* -->
	<div id="wrapper-navbar" itemscope itemtype="https://schema.org/WebSite">

		<a class="skip-link sr-only sr-only-focusable" href="#content"><?php esc_html_e( 'Skip to content', 'wpst' ); ?></a>

		<nav class="navbar navbar-expand-xl navbar-dark">
			<div class="bg-darken"></div>
				<div class="container container-lg nav-container">
					<!-- Your site title as branding in the menu -->
					<?php if ( ! has_custom_logo() ) { ?>
						<?php if ( is_front_page() && is_home() ) : ?>
							<h1 class="navbar-brand mb-0"><a rel="home" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" itemprop="url"><?php bloginfo( 'name' ); ?></a></h1>
						<?php else : ?>
							<a class="navbar-brand" rel="home" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" itemprop="url"><?php bloginfo( 'name' ); ?></a>
						<?php endif; ?>
						<?php
					} else {
						the_custom_logo();
					}
					?>
					<!-- end custom logo -->

				<div class="search-nav">
					<?php if ( class_exists( 'WPS_Professional_Suite' ) ) { echo WPS_Professional_Suite::language_switcher(); } ?>
					<div class="header-search-toggle">
						<img src="<?php echo get_template_directory_uri(); ?>/img/search.svg" width="28" height="28" style="fill: var(--wps-icon-color, #ffffff)!important;">
					</div>
					<!-- Menu mobile -->
					<button class="navbar-toggler hamburger hamburger--slider" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle navigation', 'wpst' ); ?>">
						<span class="hamburger-box">
							<span class="hamburger-inner"></span>
						</span>
					</button>

					<!-- The WordPress Menu goes here -->
					<?php
					wp_nav_menu(
						array(
							'theme_location'  => 'wpst-primary-menu',
							'container_class' => 'collapse navbar-collapse',
							'container_id'    => 'navbarNavDropdown',
							'menu_class'      => 'navbar-nav ml-auto',
							'fallback_cb'     => class_exists( 'WPS_Professional_Suite' ) ? array( 'WPS_Professional_Suite', 'fallback_menu' ) : 'wp_page_menu',
							'depth'           => 2,
							'walker'          => new Understrap_WP_Bootstrap_Navwalker(),
						)
					);
					?>
				</div>
			</div><!-- .container -->
		</nav><!-- .site-navigation -->
		<div class="header-search-form">
			<?php get_search_form(); ?>
		</div>
	</div><!-- #wrapper-navbar end -->
