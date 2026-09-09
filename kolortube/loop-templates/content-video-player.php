<?php
/**
 * Video Player Template - Works with all WordPress sites
 * 
 * Features:
 * - Main domain: pronxxnx.ggcdn.xyz
 * - Auto-fallback: if main domain fails, tries next domain in list
 * - Guest support: hides URL via AJAX for non-logged-in users
 * - Google Drive support: auto-converts via VdoHide API
 * - Supports: Youtube, MP4, WebM, Embed code, Shortcode
 * - Admin Preview in post edit screen
 * 
 * ✅ อัปเดต: ลบป้ายดำ/พื้นหลังดำบน Player ออกทั้งหมด
 */
// ==============================================
// APIVdohide - Convert Google Drive links via API
// ==============================================
if (!function_exists('APIVdohide')) {
    function APIVdohide($post_id, $source) {
        if (empty($source)) {
            return $source;
        }
        $domain = "pronxxnx.ggcdn.xyz";
        $prefix = 'google_slug_' . md5($source);
        $proxy_meta = get_post_meta($post_id, $prefix, true);
        if (!empty($proxy_meta)) {
            return "//" . $domain . "/embed/" . $proxy_meta . "/";
        }
        $token = "thchfjory9agnir9kyl8";
        if (empty($token)) {
            return $source;
        }
        $post_data = [
            'source' => esc_url_raw($source),
            'token'  => $token,
        ];
        $response = wp_remote_post(
            "https://api.vdohide.com/remote",
            [
                'method'      => 'POST',
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => [
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($post_data),
            ]
        );
        if (is_wp_error($response)) {
            return $source;
        }
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            return $source;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        if (
            json_last_error() === JSON_ERROR_NONE &&
            is_object($data) &&
            isset($data->slug)
        ) {
            $slug = sanitize_text_field($data->slug);
            update_post_meta($post_id, $prefix, $slug);
            return "//" . $domain . "/embed/" . $slug . "/";
        }
        return $source;
    }
}
// ==============================================
// VDOHide Admin Preview
// ==============================================
if (is_admin() && function_exists('get_current_screen')) {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'post') !== false) {
        $vd_slug = trim((string) get_post_meta($post->ID, 'vdohide_slug', true));
        $vd_duration = get_post_meta($post->ID, 'vdohide_duration', true);
        if ('' === trim((string) $vd_duration)) {
            $vd_duration = get_post_meta($post->ID, 'duration', true);
        }
        if (
            '' !== $vd_slug &&
            preg_match('/^[A-Za-z0-9_-]{5,128}$/', $vd_slug)
        ) {
            $preview_domains = apply_filters(
                'wpst_vdohide_mirror_domains',
                []
            );
            $preview_domain = !empty($preview_domains)
                ? reset($preview_domains)
                : 'pronxxnx.ggcdn.xyz';
            $preview_domain = preg_replace(
                '#^https?://#i',
                '',
                trim((string) $preview_domain)
            );
            $preview_url =
                'https://' .
                trim($preview_domain, '/') .
                '/embed/' .
                rawurlencode($vd_slug);
            echo '<div style="background:#e8f4ff;border:1px solid #b3d8ff;padding:15px;margin:15px 0;border-radius:8px;">';
            echo '<h4 style="margin:0 0 10px;color:#0066cc;">VDOHide Preview</h4>';
            if ('' !== trim((string) $vd_duration)) {
                echo '<p style="margin:5px 0;"><strong>Duration:</strong> ' .
                    esc_html($vd_duration) .
                    '</p>';
            }
            echo '<p style="margin:5px 0;"><strong>Slug:</strong> ' .
                esc_html($vd_slug) .
                '</p>';
            echo '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;margin-top:10px;">';
            echo '<iframe src="' .
                esc_url($preview_url) .
                '" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen allow="autoplay;fullscreen"></iframe>';
            echo '</div></div>';
        }
    }
}
// ==============================================
// Thumbnail / Poster
// ==============================================
$thumb = get_post_meta($post->ID, 'thumb', true);
if (has_post_thumbnail()) {
    $thumb_id = get_post_thumbnail_id();
    $thumb_url = wp_get_attachment_image_src($thumb_id, 'full', true);
    $poster =
        (is_array($thumb_url) && !empty($thumb_url[0]))
            ? $thumb_url[0]
            : $thumb;
} else {
    $poster = $thumb;
}
// ==============================================
// Helper Functions
// ==============================================
$wpst_video_format = static function ($url) {
    $path = wp_parse_url((string) $url, PHP_URL_PATH);
    if (!is_string($path) || '' === $path) {
        return '';
    }
    return strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
};
// ==============================================
// Mirror domains
// ==============================================
$wpst_vdohide_mirror_domains = apply_filters(
    'wpst_vdohide_mirror_domains',
    [
        'pronxxnx.ggcdn.xyz',
        'avkissme.fembed.co',
        'pronkub.ggcdn.xyz',
        'movie8k.ggcdn.xyz',
        'avmoviexxx.ggcdn.xyz',
        'sexjapanhd.ggcdn.xyz',
        'javfunxxx.javzero.xyz',
        'fembed.co',
    ]
);
$wpst_default_vdohide_domain = apply_filters(
    'wpst_default_vdohide_domain',
    'pronxxnx.ggcdn.xyz'
);
// ==============================================
// Extract VDOHide slug
// ==============================================
$wpst_extract_vdohide_slug = static function ($value) use (
    $wpst_vdohide_mirror_domains
) {
    $value = trim(
        html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8')
    );
    if ('' === $value) {
        return '';
    }
    if (
        false !== stripos($value, '<iframe') &&
        preg_match('/src=["\']([^"\']+)["\']/i', $value, $match)
    ) {
        $value = trim($match[1]);
    }
    if (preg_match('/^[A-Za-z0-9_-]{5,128}$/', $value)) {
        return $value;
    }
    if (
        preg_match(
            '#^https?://([A-Za-z0-9_-]{5,128})/?$#i',
            $value,
            $match
        )
    ) {
        return $match[1];
    }
    $parts = wp_parse_url($value);
    if (is_array($parts) && !empty($parts['host'])) {
        $host = strtolower(
            preg_replace('/^www\./i', '', (string) $parts['host'])
        );
        $allowed = array_map(
            static function ($domain) {
                return strtolower(
                    preg_replace('/^www\./i', '', (string) $domain)
                );
            },
            (array) $wpst_vdohide_mirror_domains
        );
        if (
            in_array($host, $allowed, true) &&
            !empty($parts['path']) &&
            preg_match(
                '#/(?:embed/)?([A-Za-z0-9_-]{5,128})/?$#',
                (string) $parts['path'],
                $match
            )
        ) {
            return $match[1];
        }
    }
    return '';
};
// ==============================================
// Build mirror URLs
// ==============================================
$wpst_build_vdohide_mirrors = static function (
    $slug
) use (
    $wpst_vdohide_mirror_domains,
    $wpst_default_vdohide_domain
) {
    $slug = trim((string) $slug);
    if ('' === $slug) {
        return [];
    }
    $domains = array_values(
        array_unique(
            array_merge(
                [$wpst_default_vdohide_domain],
                (array) $wpst_vdohide_mirror_domains
            )
        )
    );
    $urls = [];
    foreach ($domains as $domain) {
        $domain = preg_replace(
            '#^https?://#i',
            '',
            trim((string) $domain)
        );
        $domain = trim($domain, '/');
        if ('' === $domain) {
            continue;
        }
        $urls[] =
            'https://' .
            $domain .
            '/embed/' .
            rawurlencode($slug);
    }
    return array_values(array_unique($urls));
};
// ==============================================
// Normalize user input URL
// ==============================================
$wpst_normalize_video_input = static function (
    $value
) use (
    $wpst_extract_vdohide_slug,
    $wpst_build_vdohide_mirrors
) {
    $value = trim(
        html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8')
    );
    if ('' === $value) {
        return '';
    }
    if (
        false !== stripos($value, '<iframe') &&
        preg_match('/src=["\']([^"\']+)["\']/i', $value, $match)
    ) {
        $value = trim($match[1]);
    }
    if (0 === strpos($value, '//')) {
        $value = 'https:' . $value;
    }
    if (
        preg_match('/^[A-Za-z0-9_-]{5,128}$/', $value) ||
        preg_match(
            '#^https?://[A-Za-z0-9_-]{5,128}/?$#i',
            $value
        )
    ) {
        $slug = $wpst_extract_vdohide_slug($value);
        $mirrors = $wpst_build_vdohide_mirrors($slug);
        return !empty($mirrors) ? $mirrors[0] : '';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    if (
        preg_match(
            '#^[A-Za-z0-9.-]+\.[A-Za-z]{2,}(?::\d+)?(?:/|$)#',
            $value
        )
    ) {
        return 'https://' . ltrim($value, '/');
    }
    return $value;
};
// ==============================================
// MIME type
// ==============================================
$wpst_video_mime = static function ($format) {
    switch (strtolower((string) $format)) {
        case 'm3u8':
            return 'application/x-mpegURL';
        case 'ogv':
        case 'ogg':
            return 'video/ogg';
        case 'mov':
            return 'video/quicktime';
        case 'webm':
            return 'video/webm';
        case 'mp4':
        default:
            return 'video/' . strtolower((string) $format);
    }
};
// ==============================================
// Check direct video
// ==============================================
$wpst_is_direct_video = static function ($url) use (
    $wpst_video_format
) {
    return in_array(
        $wpst_video_format($url),
        ['mp4', 'webm', 'm3u8', 'ogv', 'ogg', 'mov'],
        true
    );
};
// ==============================================
// YouTube embed URL
// ==============================================
$wpst_youtube_embed_url = static function ($url) {
    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return '';
    }
    $host = strtolower(
        preg_replace('/^www\./i', '', $parts['host'])
    );
    $id = '';
    if ('youtu.be' === $host && !empty($parts['path'])) {
        $id = trim($parts['path'], '/');
    } elseif (
        in_array(
            $host,
            ['youtube.com', 'm.youtube.com'],
            true
        )
    ) {
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['v'])) {
                $id = $query['v'];
            }
        }
        if (
            '' === $id &&
            !empty($parts['path']) &&
            preg_match(
                '#/(?:embed|shorts)/([^/?]+)#',
                $parts['path'],
                $match
            )
        ) {
            $id = $match[1];
        }
    }
    return '' !== $id
        ? 'https://www.youtube.com/embed/' . rawurlencode($id)
        : '';
};
// ==============================================
// Get video data
// ==============================================
$video_url_raw = get_post_meta(
    $post->ID,
    'video_url',
    true
);
$video_url = $wpst_normalize_video_input($video_url_raw);
$video_slug = $wpst_extract_vdohide_slug($video_url_raw);
$video_mirrors =
    '' !== $video_slug
        ? $wpst_build_vdohide_mirrors($video_slug)
        : [];
if (
    !empty($video_mirrors) &&
    preg_match('#^https?://#i', (string) $video_url)
) {
    array_unshift($video_mirrors, $video_url);
    $video_mirrors = array_values(
        array_unique($video_mirrors)
    );
}
$is_vdohide_guest =
    !is_user_logged_in() &&
    '' !== $video_slug &&
    !empty($video_mirrors);
// ==============================================
// Resolution URLs
// ==============================================
$video_url_240 = get_post_meta($post->ID, 'video_url_240', true);
$video_url_360 = get_post_meta($post->ID, 'video_url_360', true);
$video_url_480 = get_post_meta($post->ID, 'video_url_480', true);
$video_url_720 = get_post_meta($post->ID, 'video_url_720', true);
$video_url_1080 = get_post_meta($post->ID, 'video_url_1080', true);
$video_url_4k = get_post_meta($post->ID, 'video_url_4k', true);
$format = $wpst_video_format($video_url);
$format_240 = $wpst_video_format($video_url_240);
$format_360 = $wpst_video_format($video_url_360);
$format_480 = $wpst_video_format($video_url_480);
$format_720 = $wpst_video_format($video_url_720);
$format_1080 = $wpst_video_format($video_url_1080);
$format_4k = $wpst_video_format($video_url_4k);
// ==============================================
// Build Video Player
// ==============================================
$video_player = '';
$has_resolution_url =
    !empty($video_url_240) ||
    !empty($video_url_360) ||
    !empty($video_url_480) ||
    !empty($video_url_720) ||
    !empty($video_url_1080) ||
    !empty($video_url_4k);
if ($has_resolution_url) {
    $video_player =
        '<video id="wpst-video" class="video-js vjs-big-play-centered" controls preload="auto" width="640" height="264" poster="' .
        esc_url($poster) .
        '">';
    $resolution_sources = [
        '4k' => [$video_url_4k, $format_4k],
        '1080p' => [$video_url_1080, $format_1080],
        '720p' => [$video_url_720, $format_720],
        '480p' => [$video_url_480, $format_480],
        '360p' => [$video_url_360, $format_360],
        '240p' => [$video_url_240, $format_240],
    ];
    foreach ($resolution_sources as $label => $source) {
        if (empty($source[0])) {
            continue;
        }
        $video_player .=
            '<source src="' .
            esc_url($source[0]) .
            '" label="' .
            esc_attr($label) .
            '" title="' .
            esc_attr($label) .
            '" type="' .
            esc_attr($wpst_video_mime($source[1])) .
            '" />';
    }
    $video_player .= '</video>';
} elseif ('' !== $video_url) {
    $youtube_embed = $wpst_youtube_embed_url($video_url);
    $video_domain = strtolower(
        preg_replace(
            '/^www\./i',
            '',
            (string) wp_parse_url(
                $video_url,
                PHP_URL_HOST
            )
        )
    );
    if ('' !== $youtube_embed) {
        $video_player =
            '<iframe src="' .
            esc_url($youtube_embed) .
            '" frameborder="0" width="560" height="315" scrolling="no" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>';
    } elseif ('drive.google.com' === $video_domain) {
        $vdohide_url = APIVdohide(
            $post->ID,
            $video_url
        );
        $is_vdohide_converted =
            ($vdohide_url !== $video_url) &&
            (
                false !== strpos($vdohide_url, 'ggcdn.xyz') ||
                false !== strpos($vdohide_url, 'fembed.co') ||
                false !== strpos($vdohide_url, 'javzero.xyz')
            );
        if ($is_vdohide_converted) {
            $vdohide_full =
                (0 === strpos($vdohide_url, '//'))
                    ? 'https:' . $vdohide_url
                    : $vdohide_url;
            $gd_slug =
                $wpst_extract_vdohide_slug(
                    $vdohide_full
                );
            if ('' !== $gd_slug) {
                $gd_mirrors =
                    $wpst_build_vdohide_mirrors(
                        $gd_slug
                    );
                $mirror_json =
                    wp_json_encode(
                        array_values($gd_mirrors)
                    );
                $mirrors_b64 = base64_encode($mirror_json);
                $video_player =
                    '<iframe class="wpst-vdohide-mirror-frame" src="' .
                    esc_url($gd_mirrors[0]) .
                    '" data-wpst-mirrors-b64="' .
                    esc_attr($mirrors_b64) .
                    '" data-wpst-mirror-index="0" frameborder="0" width="560" height="340" scrolling="no" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen onerror="window.wpstTryNextVideoMirror&amp;&amp;window.wpstTryNextVideoMirror(this);"></iframe>';
            } else {
                $video_player =
                    '<iframe src="' .
                    esc_url($vdohide_full) .
                    '" frameborder="0" width="560" height="340" scrolling="no" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>';
            }
        } else {
            $video_url_gd =
                preg_replace(
                    '#/view(?:\?.*)?$#',
                    '/preview',
                    $video_url
                );
            $video_player =
                '<iframe src="' .
                esc_url($video_url_gd) .
                '" frameborder="0" width="640" height="360" scrolling="no" allowfullscreen></iframe>';
        }
    } elseif ($wpst_is_direct_video($video_url)) {
        $video_player =
            '<video id="wpst-video" class="video-js vjs-big-play-centered" controls preload="auto" width="640" height="264" poster="' .
            esc_url($poster) .
            '"><source src="' .
            esc_url($video_url) .
            '" type="' .
            esc_attr($wpst_video_mime($format)) .
            '"></video>';
    } elseif (preg_match('#^https?://#i', $video_url)) {
        if (!empty($video_mirrors)) {
            if (
                $is_vdohide_guest &&
                function_exists(
                    'wpst_boss_vdohide_guest_token'
                )
            ) {
                $guest_token =
                    wpst_boss_vdohide_guest_token(
                        $post->ID,
                        $video_slug
                    );
                $guest_holder =
                    'wpst-vdohide-guest-' .
                    absint($post->ID);
                // ✅ แก้ไข: ลบพื้นหลังดำ (ป้ายดำ) ออก ใช้พื้นหลังโปร่งใสแทน
                $video_player =
                    '<div id="' .
                    esc_attr($guest_holder) .
                    '" class="wpst-vdohide-guest-player" data-post-id="' .
                    absint($post->ID) .
                    '" data-token="' .
                    esc_attr($guest_token) .
                    '" data-endpoint="' .
                    esc_url(admin_url('admin-ajax.php')) .
                    '" style="position:absolute;inset:0;width:100%;height:100%;background:transparent;"></div>';
            } else {
                $mirror_json =
                    wp_json_encode(
                        array_values($video_mirrors)
                    );
                $mirrors_b64 = base64_encode($mirror_json);
                $video_player =
                    '<iframe class="wpst-vdohide-mirror-frame" src="' .
                    esc_url($video_mirrors[0]) .
                    '" data-wpst-mirrors-b64="' .
                    esc_attr($mirrors_b64) .
                    '" data-wpst-mirror-index="0" frameborder="0" width="560" height="340" scrolling="no" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen onerror="window.wpstTryNextVideoMirror&amp;&amp;window.wpstTryNextVideoMirror(this);"></iframe>';
            }
        } else {
            $video_player =
                '<iframe src="' .
                esc_url($video_url) .
                '" frameborder="0" width="560" height="340" scrolling="no" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>';
        }
    }
}
// ==============================================
// Other Data
// ==============================================
$embed_code = get_post_meta(
    $post->ID,
    'embed',
    true
);
$embed_url = '';
if ($embed_code != '') {
    preg_match(
        '/src=["\']([^"]+)["\']/',
        $embed_code,
        $match
    );
    if (isset($match[1])) {
        $embed_url = $match[1];
    }
}
$video_shortcode = get_post_meta(
    $post->ID,
    'shortcode',
    true
);
$duration = get_post_meta(
    $post->ID,
    'duration',
    true
);
$title = get_the_title();
$desc = wp_strip_all_tags(get_the_content());
$author = get_the_author();
$video_in_content = false;
?>
<div class="responsive-player video-player">
<?php if ($is_vdohide_guest) : ?>
<script>
(function () {
    function bootWpstGuestVdoHide() {
        var holder = document.getElementById(
            <?php echo wp_json_encode('wpst-vdohide-guest-' . absint($post->ID)); ?>
        );
        if (!holder || holder.getAttribute('data-wpst-started') === '1') {
            return;
        }
        holder.setAttribute('data-wpst-started', '1');
        var endpoint = holder.getAttribute('data-endpoint') || '';
        var postId = holder.getAttribute('data-post-id') || '';
        var token = holder.getAttribute('data-token') || '';
        var mirror = 0;
        var requestSeq = 0;
        function showUnavailable() {
            // ✅ แก้ไข: ลบพื้นหลังดำ ใช้พื้นหลังโปร่งใส
            holder.innerHTML =
                '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:transparent;color:#aaa;font:14px sans-serif;">Video unavailable</div>';
        }
        function loadMirror() {
            var seq = ++requestSeq;
            var body = new URLSearchParams();
            body.set('action', 'wpst_boss_vdohide_embed');
            body.set('post_id', postId);
            body.set('token', token);
            body.set('mirror', String(mirror));
            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (seq !== requestSeq) {
                    return;
                }
                if (
                    !payload ||
                    !payload.success ||
                    !payload.data ||
                    !payload.data.url
                ) {
                    showUnavailable();
                    return;
                }
                var frame = document.createElement('iframe');
                frame.className =
                    'wpst-vdohide-mirror-frame';
                frame.src = payload.data.url;
                frame.setAttribute('frameborder', '0');
                frame.setAttribute('scrolling', 'no');
                frame.setAttribute(
                    'allow',
                    'autoplay; encrypted-media; picture-in-picture; fullscreen'
                );
                frame.setAttribute(
                    'allowfullscreen',
                    'allowfullscreen'
                );
                frame.style.cssText =
                    'position:absolute;inset:0;width:100%;height:100%;border:0;';
                var finished = false;
                var timer = window.setTimeout(function () {
                    if (finished) {
                        return;
                    }
                    finished = true;
                    if (payload.data.has_next) {
                        mirror += 1;
                        loadMirror();
                    } else {
                        showUnavailable();
                    }
                }, 12000);
                frame.addEventListener(
                    'load',
                    function () {
                        if (finished) {
                            return;
                        }
                        finished = true;
                        window.clearTimeout(timer);
                    },
                    { once: true }
                );
                frame.addEventListener(
                    'error',
                    function () {
                        if (finished) {
                            return;
                        }
                        finished = true;
                        window.clearTimeout(timer);
                        if (payload.data.has_next) {
                            mirror += 1;
                            loadMirror();
                        } else {
                            showUnavailable();
                        }
                    },
                    { once: true }
                );
                holder.replaceChildren(frame);
            })
            .catch(function () {
                showUnavailable();
            });
        }
        loadMirror();
    }
    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            bootWpstGuestVdoHide,
            { once: true }
        );
    } else {
        bootWpstGuestVdoHide();
    }
})();
</script>
<?php else : ?>
<script>
if (typeof window.wpstTryNextVideoMirror !== 'function') {
    window.wpstTryNextVideoMirror = function (frame) {
        try {
            var mirrors = [];
            var mirrorsB64 = frame.getAttribute('data-wpst-mirrors-b64') || '';
            if (mirrorsB64) {
                try {
                    mirrors = JSON.parse(atob(mirrorsB64));
                } catch (e) {
                    mirrors = [];
                }
            }
            if (!mirrors.length) {
                var legacyMirrors = frame.getAttribute('data-wpst-mirrors') || '[]';
                mirrors = JSON.parse(legacyMirrors);
            }
            var index = parseInt(
                frame.getAttribute('data-wpst-mirror-index') || '0',
                10
            );
            if (
                !mirrors.length ||
                index >= mirrors.length - 1
            ) {
                return;
            }
            index += 1;
            frame.setAttribute(
                'data-wpst-mirror-index',
                String(index)
            );
            frame.src = mirrors[index];
        } catch (e) {}
    };
}
</script>
<?php endif; ?>
<!-- Schema.org Metadata -->
<meta itemprop="author" content="<?php echo esc_attr($author); ?>" />
<meta itemprop="name" content="<?php echo esc_attr($title); ?>" />
<?php if ($desc != '') : ?>
    <meta itemprop="description" content="<?php echo esc_attr($desc); ?>" />
<?php else : ?>
    <meta itemprop="description" content="<?php echo esc_attr($title); ?>" />
<?php endif; ?>
<meta
    itemprop="duration"
    content="<?php echo function_exists('wpst_iso8601_duration') ? wpst_iso8601_duration($duration) : ''; ?>"
/>
<meta
    itemprop="thumbnailUrl"
    content="<?php echo esc_url($poster); ?>"
/>
<?php if ($video_url != '' && !$is_vdohide_guest) : ?>
<meta
    itemprop="contentURL"
    content="<?php echo esc_url($video_url); ?>"
/>
<?php elseif ($embed_code != '') : ?>
<meta
    itemprop="embedURL"
    content="<?php echo esc_url($embed_url); ?>"
/>
<?php endif; ?>
<meta
    itemprop="uploadDate"
    content="<?php echo esc_attr(get_the_date('c')); ?>"
/>
<?php
// ==============================================
// Paywall filter
// ==============================================
ob_start(
    function ($buffer) use ($post) {
        return function_exists('apply_filters')
            ? apply_filters(
                'wps_paywall_media_content',
                $buffer,
                $post->ID
            )
            : $buffer;
    }
);
?>
<?php if (
    $video_url != '' ||
    $video_url_240 != '' ||
    $video_url_360 != '' ||
    $video_url_480 != '' ||
    $video_url_720 != '' ||
    $video_url_1080 != '' ||
    $video_url_4k != ''
) : ?>
    <?php echo $video_player; ?>
<?php elseif ($embed_code != '') : ?>
    <?php echo htmlspecialchars_decode($embed_code); ?>
<?php elseif ($video_shortcode != '') : ?>
    <?php echo do_shortcode($video_shortcode); ?>
<?php elseif (
    $video_url == '' &&
    $embed_code == '' &&
    $video_shortcode == ''
) : ?>
<?php
$video_code = [];
$is_youtube = false;
if (
    preg_match(
        '/\[video.+\]/',
        get_the_content(),
        $video_code
    )
) {
    $video_in_content = '/\[video.+\]/';
} elseif (
    preg_match(
        '/<iframe.+<\/iframe>/',
        get_the_content(),
        $video_code
    )
) {
    $video_in_content = '/<iframe.+<\/iframe>/';
} elseif (
    preg_match(
        '/<video.+<\/video>/',
        get_the_content(),
        $video_code
    )
) {
    $video_in_content = '/<video.+<\/video>/';
} elseif (
    preg_match(
        '/<object.+<\/object>/',
        get_the_content(),
        $video_code
    )
) {
    $video_in_content = '/<object.+<\/object>/';
} elseif (
    preg_match(
        "/https:\/\/www.youtube.com\/watch\?v=.+?\b/",
        get_the_content(),
        $video_code
    )
) {
    $is_youtube = true;
    $source_id = explode(
        '/',
        $video_code[0]
    );
    $source_id = str_replace(
        'watch?v=',
        '',
        end($source_id)
    );
    $video_in_content =
        '<iframe width="560" height="315" src="https://www.youtube.com/embed/' .
        $source_id .
        '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
}
if ($video_code) {
    if ($is_youtube) {
        echo $video_in_content;
    } elseif ($video_in_content == '/\[video.+\]/') {
        echo do_shortcode($video_code[0]);
    } else {
        echo $video_code[0];
    }
}
?>
<?php endif; ?>
<?php ob_end_flush(); ?>
</div>
