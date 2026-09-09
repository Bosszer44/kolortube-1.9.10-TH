# AV Framework PRO 4.0 — LiteSpeed Integration

This build treats LiteSpeed Cache as the only page-cache and optimization owner.

## Automatically suppressed while LiteSpeed primary mode is active

- AV Framework native lazy-load filters
- the legacy inline LazyLoad JavaScript bundled by KolorTube
- AV Framework legacy CDN URL rewriting
- calls to WP Rocket, W3 Total Cache, or other cache APIs

Theme-owned list thumbnails now use standard `src` markup and no longer depend on the old `data-src` JavaScript loader. Native `loading="lazy"` is added only when the framework is operating as the fallback; LiteSpeed receives clean source markup when it owns lazy loading.

## One-time safe migration

On the first administrator request after upgrading while LiteSpeed Cache is active, the framework:

1. Saves the previous framework overlap settings in `avfp_pre_litespeed_settings`.
2. Enables `avfp_litespeed_primary`.
3. Disables the framework lazy-load fallback.
4. Disables the framework legacy CDN rewrite.
5. Disables framework debug mode when WordPress debug mode is off.

Colors, templates, layout, menus, widgets, advertisements, SEO text, WP-Script settings, and legacy KolorTube values are not changed.

## AV Control Center tools

- Apply LiteSpeed Safe Mode
- Purge All LSCache
- Purge LiteSpeed Object Cache
- Restore Framework Fallbacks
- Plugin/version/server status
- Cache-plugin conflict detection

## Recommended ownership

Use LiteSpeed Cache for page cache, browser cache, object cache, lazy loading, CSS/JS optimization, image optimization, QUIC.cloud/CDN, crawler, and database optimization. Use AV Framework for theme behavior, media matching, cover tasks, queue processing, diagnostics, settings portability, and compatibility.


## Production notes

- Do not keep WP Rocket or another full-page optimization plugin active beside LiteSpeed Cache. The framework reports conflicts but never deactivates plugins automatically.
- Configure page optimization, image optimization, crawler, object cache and QUIC.cloud from the LiteSpeed Cache screens. AV Framework deliberately does not write version-sensitive LiteSpeed plugin options.
- The theme keeps a reversible snapshot of its own fallback values only. It does not modify colors, advertisements, layouts, menus, widgets, SEO content or WP-Script Core settings.


## Player compatibility

The original KolorTube player, embed code and shortcode paths remain primary. The old template-level remote request has been removed because its response was unused and could delay every video page. An optional compatibility adapter can be enabled under **Customizer → AV Framework PRO → Player**. It uses the WordPress HTTP API, an eight-second timeout and a five-minute circuit breaker; its token is never included in JSON settings exports.
