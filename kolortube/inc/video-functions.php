<?php

if ( ! function_exists( 'wpst_get_video_thumb_url' ) ) {
	/**
	 * Return a video cover URL without changing the legacy template markup.
	 *
	 * Existing featured images and the KolorTube `thumb` meta value keep their
	 * normal priority. KolorTube adds mapped/local ID and slug matches
	 * only when Smart Cover is enabled.
	 *
	 * @param int          $post_id         Post ID.
	 * @param string|array $size            Registered image size.
	 * @param bool         $include_default Whether to return the framework fallback.
	 * @return string
	 */
	function wpst_get_video_thumb_url( $post_id = 0, $size = 'video-thumb', $include_default = false ) {
		$post_id = absint( $post_id ? $post_id : get_the_ID() );
		if ( ! $post_id ) {
			return '';
		}

		if ( class_exists( 'WPS_Bulk_Cover_Generator' ) && get_theme_mod( 'wps_smart_cover_enabled', true ) ) {
			$cover = WPS_Bulk_Cover_Generator::resolve_cover( $post_id, (bool) $include_default );
			if ( is_array( $cover ) && ! empty( $cover['url'] ) ) {
				if ( ! empty( $cover['attachment_id'] ) ) {
					$sized_url = wp_get_attachment_image_url( absint( $cover['attachment_id'] ), $size );
					if ( $sized_url ) {
						return esc_url_raw( $sized_url );
					}
				}
				return esc_url_raw( $cover['url'] );
			}
		}

		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			$url = wp_get_attachment_image_url( $thumbnail_id, $size );
			if ( $url ) {
				return esc_url_raw( $url );
			}
		}

		return class_exists( 'WPS_Compatibility' )
			? WPS_Compatibility::legacy_cover_url( $post_id )
			: esc_url_raw( (string) get_post_meta( $post_id, 'thumb', true ) );
	}
}

if ( ! function_exists( 'wpst_get_video_duration' ) ) {
	function wpst_get_video_duration( $post_id = 0 ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    if ( ! $post_id ) return '';

    $raw = '';
    foreach ( array( 'duration', '_duration', '_wps_duration', '_wps_video_duration', 'video_duration' ) as $key ) {
        $value = get_post_meta( $post_id, $key, true );
        if ( '' !== trim( (string) $value ) ) { $raw = trim( (string) $value ); break; }
    }
    if ( '' === $raw ) return '';

    if ( preg_match( '/^(?:(\d+):)?([0-5]?\d):([0-5]\d)$/', $raw, $match ) ) {
        $hours = isset( $match[1] ) && '' !== $match[1] ? absint( $match[1] ) : 0;
        $seconds = ( $hours * HOUR_IN_SECONDS ) + ( absint( $match[2] ) * MINUTE_IN_SECONDS ) + absint( $match[3] );
    } else {
        $seconds = absint( $raw );
    }
    if ( $seconds < 1 ) return '';
    return $seconds >= HOUR_IN_SECONDS ? gmdate( 'H:i:s', $seconds ) : gmdate( 'i:s', $seconds );
	}
}

if ( ! function_exists( 'wpst_get_duration_sec' ) ) {
	function wpst_get_duration_sec( $duration, $sponsor ) {
		switch ( $sponsor ) {
			case 'pornhub':
			case 'redtube':
			case 'spankwire':
			case 'tube8':
			case 'xhamster':
			case 'youporn':
				$parts = array_map( 'absint', explode( ':', (string) $duration ) );
				if ( count( $parts ) < 2 ) {
					return false;
				}
				return (int) $parts[0] * 60 + (int) $parts[1];
			case 'xvideos':
				$duration = str_replace( array( '- ', 'h', 'min', 'sec' ), array( '', 'hours', 'minutes', 'seconds' ), $duration );
				return strtotime( $duration ) - strtotime( 'NOW' );
			default:
				return false;
		}
	}
}

if ( ! function_exists( 'wpst_getPostViews' ) ) {
	function wpst_getPostViews( $post_id ) {
		$count_key = 'post_views_count';
		$count     = get_post_meta( $post_id, $count_key, true );
		if ( '' === $count ) {
			delete_post_meta( $post_id, $count_key );
			add_post_meta( $post_id, $count_key, '0' );
			return '0';
		}
		return $count;
	}
}

// Duration in ISO 8601.
if ( ! function_exists( 'wpst_iso8601_duration' ) ) {
	function wpst_iso8601_duration( $seconds ) {
		$seconds = (int) $seconds;
		$days    = floor( $seconds / 86400 );
		$seconds = $seconds % 86400;
		$hours   = floor( $seconds / 3600 );
		$seconds = $seconds % 3600;
		$minutes = floor( $seconds / 60 );
		$seconds = $seconds % 60;
		return sprintf( 'P%dDT%dH%dM%dS', $days, $hours, $minutes, $seconds );
	}
}

if ( ! function_exists( 'wpst_get_multithumbs' ) ) {
	function wpst_get_multithumbs( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		$thumbs     = array();
		$thumb_size = 'wpst_thumb_medium';
		$sizes      = array_unique( array_filter( array( $thumb_size, 'video-thumb', 'medium_large', 'large', 'full' ) ) );

		$add_url = static function ( $url ) use ( &$thumbs ) {
			$url = trim( (string) $url );
			if ( '' === $url || ! preg_match( '~^https?://|^//~i', $url ) ) {
				return;
			}
			if ( is_ssl() ) {
				$url = str_replace( 'http://', 'https://', $url );
			}
			$thumbs[] = $url;
		};

		$add_attachment = static function ( $attachment_id ) use ( $sizes, $add_url ) {
			$attachment_id = absint( $attachment_id );
			if ( ! $attachment_id ) {
				return;
			}
			foreach ( $sizes as $size ) {
				$url = wp_get_attachment_image_url( $attachment_id, $size );
				if ( $url ) {
					$add_url( $url );
					return;
				}
			}
		};

		// 1) Use the AV Framework gallery mapped under the post first. This is the same gallery shown under the post.
		$gallery_ids = get_post_meta( $post_id, '_wps_42_gallery_ids', true );
		if ( is_string( $gallery_ids ) ) {
			$decoded = json_decode( $gallery_ids, true );
			if ( is_array( $decoded ) ) {
				$gallery_ids = $decoded;
			}
		}
		if ( is_array( $gallery_ids ) ) {
			foreach ( $gallery_ids as $attachment_id ) {
				$add_attachment( $attachment_id );
			}
		}

		// 2) Then use images physically attached to the post, in WordPress menu/order ID order.
		$attachments = get_attached_media( 'image', $post_id );
		if ( $attachments ) {
			uasort(
				$attachments,
				static function ( $a, $b ) {
					$ao = isset( $a->menu_order ) ? (int) $a->menu_order : 0;
					$bo = isset( $b->menu_order ) ? (int) $b->menu_order : 0;
					if ( $ao === $bo ) {
						return (int) $a->ID <=> (int) $b->ID;
					}
					return $ao <=> $bo;
				}
			);
			foreach ( $attachments as $attachment ) {
				if ( has_post_thumbnail( $post_id ) && (int) get_post_thumbnail_id( $post_id ) === (int) $attachment->ID ) {
					continue;
				}
				$add_attachment( $attachment->ID );
			}
		}

		// 3) Legacy thumbs meta, if importer/source stored gallery URLs there.
		foreach ( (array) get_post_meta( $post_id, 'thumbs', false ) as $legacy_thumb ) {
			if ( is_array( $legacy_thumb ) ) {
				foreach ( $legacy_thumb as $url ) {
					$add_url( $url );
				}
			} else {
				foreach ( preg_split( '/[\r\n,]+/', (string) $legacy_thumb ) as $url ) {
					$add_url( $url );
				}
			}
		}

		// 4) Images inserted in content/galleries under the post.
		$post = get_post( $post_id );
		if ( $post && ! empty( $post->post_content ) ) {
			if ( preg_match_all( '~wp-image-(\d+)~', $post->post_content, $ids ) ) {
				foreach ( array_unique( array_map( 'absint', $ids[1] ) ) as $attachment_id ) {
					$add_attachment( $attachment_id );
				}
			}
			if ( preg_match_all( '~<img[^>]+src=["\']([^"\']+)["\']~i', $post->post_content, $imgs ) ) {
				foreach ( $imgs[1] as $url ) {
					$add_url( $url );
				}
			}
		}

		$thumbs = array_values( array_unique( array_filter( $thumbs ) ) );
		if ( count( $thumbs ) < 1 ) {
			return false;
		}
		return implode( ',', $thumbs );
	}
}

if ( ! function_exists( 'wpst_cats_tags' ) ) {
	function wpst_cats_tags() {
		// Hide category and tag text for pages.
		if ( 'post' === get_post_type() ) {
			$postcats = get_the_category();
			$posttags = get_the_tags();
			if ( $postcats || $posttags ) {
				if ( $postcats !== false ) {
					foreach ( (array) $postcats as $cat ) {
						echo '<a href="' . get_category_link( $cat->term_id ) . '" title="' . $cat->name . '">' . ucfirst( $cat->name ) . '</a> ';
					}
				}
				if ( false !== $posttags ) {
					foreach ( (array) $posttags as $tag ) {
						echo '<a href="' . get_tag_link( $tag->term_id ) . '" title="' . $tag->name . '">' . ucfirst( $tag->name ) . '</a> ';
					}
				}
			}
		}
		if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			echo '<span class="comments-link">';
			/* translators: %s: post title */
			comments_popup_link( sprintf( wp_kses( __( 'Leave a Comment<span class="screen-reader-text"> on %s</span>', 'wpst' ), array( 'span' => array( 'class' => array() ) ) ), get_the_title() ) );
			echo '</span>';
		}
	}
}

// This enables the function that lets you set new image sizes.
add_theme_support( 'post-thumbnails' );
// These are the new image sizes we cooked up.
add_image_size( 'video-thumb', 400 );
// Now we register the size so it appears as an option within the editor.
add_filter( 'image_size_names_choose', 'wpst_thumbs_size' );
function wpst_thumbs_size( $sizes ) {
	return array_merge(
		$sizes,
		array(
			'video-thumb' => __( 'Video Thumb' ),
		)
	);
}
