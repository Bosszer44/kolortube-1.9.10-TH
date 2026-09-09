<?php
/** SEO module: Open Graph, canonical, schema, breadcrumbs and robots. */
defined( 'ABSPATH' ) || exit;

final class WPS_SEO {
	/**
	 * Register SEO integrations without duplicating output from dedicated SEO plugins.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'archive_title' ) );
		add_shortcode( 'wps_breadcrumb', array( __CLASS__, 'breadcrumb_shortcode' ) );

		if ( self::has_seo_plugin() ) {
			return;
		}

		if ( get_theme_mod( 'wps_enable_canonical', true ) ) {
			remove_action( 'wp_head', 'rel_canonical' );
			add_action( 'wp_head', array( __CLASS__, 'canonical' ), 5 );
		}
		if ( get_theme_mod( 'wps_enable_open_graph', true ) ) {
			add_action( 'wp_head', array( __CLASS__, 'open_graph' ), 6 );
		}
		if ( get_theme_mod( 'wps_enable_schema', true ) ) {
			add_action( 'wp_head', array( __CLASS__, 'schema' ), 20 );
		}
	}

	/**
	 * Detect common SEO plugins so the theme does not emit duplicate metadata.
	 *
	 * @return bool
	 */
	public static function has_seo_plugin() {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' )
			|| defined( 'THE_SEO_FRAMEWORK_VERSION' )
			|| defined( 'SLIM_SEO_VER' )
			|| class_exists( 'WPSEO_Frontend' )
			|| class_exists( 'RankMath' )
			|| class_exists( 'The_SEO_Framework\\Load' )
			|| class_exists( 'Slim_SEO' )
			|| function_exists( 'aioseo' )
			|| function_exists( 'the_seo_framework' );
	}

	/**
	 * Print a canonical URL for indexable public views only.
	 *
	 * @return void
	 */
	public static function canonical() {
		$url = self::current_url();
		if ( $url ) {
			echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
		}
	}

	/**
	 * Print Open Graph and Twitter metadata.
	 *
	 * @return void
	 */
	public static function open_graph() {
		$title       = wp_get_document_title();
		$description = self::description();
		$url         = self::current_url();
		$image       = self::image_url();
		$type        = is_singular( 'post' ) ? 'article' : 'website';
		$fb_app_id   = trim( (string) apply_filters( 'wps_facebook_app_id', '' ) );

		if ( ! $url ) {
			return;
		}
		?>
		<?php if ( $fb_app_id ) : ?>
		<meta property="fb:app_id" content="<?php echo esc_attr( $fb_app_id ); ?>" />
		<?php endif; ?>
		<meta property="og:locale" content="<?php echo esc_attr( get_locale() ); ?>" />
		<meta property="og:type" content="<?php echo esc_attr( $type ); ?>" />
		<meta property="og:title" content="<?php echo esc_attr( $title ); ?>" />
		<meta property="og:description" content="<?php echo esc_attr( $description ); ?>" />
		<meta property="og:url" content="<?php echo esc_url( $url ); ?>" />
		<meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
		<meta name="twitter:card" content="<?php echo esc_attr( $image ? 'summary_large_image' : 'summary' ); ?>" />
		<meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>" />
		<meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>" />
		<?php if ( $image ) : ?>
		<meta property="og:image" content="<?php echo esc_url( $image ); ?>" />
		<meta name="twitter:image" content="<?php echo esc_url( $image ); ?>" />
		<?php endif; ?>
		<?php
	}

	/**
	 * Print schema.org JSON-LD.
	 *
	 * @return void
	 */
	public static function schema() {
		$schema = is_singular( 'post' ) ? self::video_schema() : self::website_schema();
		if ( empty( $schema ) ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	private static function website_schema() {
		return array(
			'@context'       => 'https://schema.org',
			'@type'          => 'WebSite',
			'name'           => get_bloginfo( 'name' ),
			'url'            => home_url( '/' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	private static function video_schema() {
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return array();
		}

		$schema = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'VideoObject',
			'name'             => get_the_title( $post_id ),
			'description'      => self::description(),
			'uploadDate'       => get_the_date( DATE_W3C, $post_id ),
			'url'              => get_permalink( $post_id ),
			'mainEntityOfPage' => get_permalink( $post_id ),
		);
		$image  = self::image_url();
		if ( $image ) {
			$schema['thumbnailUrl'] = array( $image );
		}
		$duration = absint( get_post_meta( $post_id, 'duration', true ) );
		if ( $duration ) {
			$schema['duration'] = 'PT' . $duration . 'S';
		}
		return $schema;
	}

	public static function robots( $robots ) {
		$mode = get_theme_mod( 'wps_robots_mode', 'default' );
		if ( 'index' === $mode ) {
			$robots['index']  = true;
			$robots['follow'] = true;
			unset( $robots['noindex'], $robots['nofollow'] );
		} elseif ( 'noindex' === $mode ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'], $robots['nofollow'] );
		}
		return $robots;
	}

	public static function archive_title( $title ) {
		if ( is_tax( 'studio' ) ) {
			$override = trim( (string) get_theme_mod( 'wps_studio_archive_title', '' ) );
			return $override ? $override : $title;
		}
		if ( is_tax( 'actors' ) ) {
			$override = trim( (string) get_theme_mod( 'wps_actress_archive_title', '' ) );
			return $override ? $override : $title;
		}
		return $title;
	}

	public static function breadcrumb_shortcode() {
		return self::get_breadcrumbs();
	}

	public static function get_breadcrumbs() {
		if ( ! get_theme_mod( 'wps_enable_breadcrumbs', true ) || is_front_page() ) {
			return '';
		}

		$items = array( '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'wpst' ) . '</a>' );
		if ( is_singular() ) {
			$categories = get_the_category();
			if ( ! empty( $categories ) ) {
				$items[] = '<a href="' . esc_url( get_category_link( $categories[0] ) ) . '">' . esc_html( $categories[0]->name ) . '</a>';
			}
			$items[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$items[] = '<span aria-current="page">' . esc_html( single_term_title( '', false ) ) . '</span>';
		} elseif ( is_search() ) {
			$items[] = '<span aria-current="page">' . sprintf( esc_html__( 'Search: %s', 'wpst' ), esc_html( get_search_query() ) ) . '</span>';
		} elseif ( is_archive() ) {
			$items[] = '<span aria-current="page">' . wp_kses_post( get_the_archive_title() ) . '</span>';
		}
		return '<nav class="wps-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'wpst' ) . '">' . implode( '<span class="wps-breadcrumb-separator" aria-hidden="true"> / </span>', $items ) . '</nav>';
	}

	private static function description() {
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$meta    = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			if ( $meta ) {
				return wp_trim_words( wp_strip_all_tags( $meta ), 30, '…' );
			}
			$excerpt = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : get_post_field( 'post_content', $post_id );
			return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $excerpt ) ), 30, '…' );
		}
		$description = get_bloginfo( 'description' );
		return $description ? $description : get_bloginfo( 'name' );
	}

	private static function image_url() {
		$post_id = get_queried_object_id();
		if ( $post_id && class_exists( 'WPS_Bulk_Cover_Generator' ) ) {
			$cover = WPS_Bulk_Cover_Generator::resolve_cover( $post_id, false );
			if ( is_array( $cover ) && ! empty( $cover['url'] ) ) {
				return esc_url_raw( $cover['url'] );
			}
		}
		if ( $post_id && has_post_thumbnail( $post_id ) ) {
			return wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
		}
		if ( $post_id ) {
			$thumb = get_post_meta( $post_id, 'thumb', true );
			if ( $thumb ) {
				return esc_url_raw( $thumb );
			}
		}
		$logo_id = absint( get_theme_mod( 'custom_logo' ) );
		return $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	}

	private static function current_url() {
		if ( is_404() || is_search() || is_preview() ) {
			return '';
		}
		if ( is_singular() ) {
			return get_permalink();
		}
		if ( is_front_page() || is_home() ) {
			return home_url( '/' );
		}
		return get_pagenum_link( max( 1, absint( get_query_var( 'paged' ) ) ) );
	}
}

if ( ! function_exists( 'wps_breadcrumb' ) ) {
	function wps_breadcrumb( $echo = true ) {
		$breadcrumb = WPS_SEO::get_breadcrumbs();
		if ( $echo ) {
			echo wp_kses_post( $breadcrumb );
			return null;
		}
		return $breadcrumb;
	}
}
