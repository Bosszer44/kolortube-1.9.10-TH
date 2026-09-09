/*
 * KolorTube Gallery Lightbox — expanded to support all gallery types.
 * Supports: .video-gallery, .wp-block-gallery, .player-gallery-grid,
 * and standalone images inside .entry-content / .single-post-content / .wp-block-image / figure.
 * Capture phase prevents theme anchor handlers from navigating away.
 */
(function () {
    'use strict';
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }
    ready(function () {
        if (document.getElementById('wps-gallery-lightbox')) return;
        var overlay = document.createElement('div');
        overlay.id = 'wps-gallery-lightbox';
        overlay.className = 'wps-lightbox-overlay gallery-lightbox wpst-gallery-lightbox wpmb-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML =
            '<button type="button" class="wps-lightbox-close lb-close wpst-gallery-lightbox-close wpmb-lightbox-close" aria-label="Close">&times;</button>' +
            '<button type="button" class="wps-lightbox-prev lb-prev wpst-gallery-lightbox-prev wpmb-lightbox-prev" aria-label="Previous image"><span aria-hidden="true">&#8249;</span></button>' +
            '<div class="wps-lightbox-stage"><img src="" alt="" draggable="false"></div>' +
            '<button type="button" class="wps-lightbox-next lb-next wpst-gallery-lightbox-next wpmb-lightbox-next" aria-label="Next image"><span aria-hidden="true">&#8250;</span></button>' +
            '<div class="wps-lightbox-counter lb-counter wpst-gallery-lightbox-count wpmb-lightbox-count" aria-live="polite"></div>';
        document.body.appendChild(overlay);
        var img = overlay.querySelector('img');
        var prev = overlay.querySelector('.wps-lightbox-prev');
        var next = overlay.querySelector('.wps-lightbox-next');
        var closeBtn = overlay.querySelector('.wps-lightbox-close');
        var counter = overlay.querySelector('.wps-lightbox-counter');
        var items = [];
        var index = 0;
        var startX = 0;
        var startY = 0;
        var previousOverflow = '';
        var changeTimer = null;

        function isImageUrl(url) {
            return /\.(jpg|jpeg|png|gif|webp|bmp|svg)(\?|#|$)/i.test(url);
        }

        function getFullImageSrc(imgEl) {
            if (!imgEl) return '';
            // Try data attributes first
            var src = imgEl.getAttribute('data-large') ||
                      imgEl.getAttribute('data-full') ||
                      imgEl.getAttribute('data-src') ||
                      imgEl.getAttribute('src') || '';
            // Try to remove WordPress size suffix (-123x456) to get full size
            src = src.replace(/(-\d+x\d+)(\.[^.]+?)(\?|#|$)/, '$2$3');
            return src;
        }

        function getTriggers() {
            // 1) Gallery links: video-gallery, wp-block-gallery, player-gallery-grid
            var gallerySelectors = [
                '.video-gallery a.wps-lightbox-trigger',
                '.wp-block-gallery a',
                '.player-gallery-grid a'
            ];
            var galleryLinks = Array.prototype.slice.call(
                document.querySelectorAll(gallerySelectors.join(', '))
            ).filter(function (a) {
                var href = a.getAttribute('href') || '';
                // Only keep links that point to images or have an img inside
                return isImageUrl(href) || !!a.querySelector('img');
            });

            // 2) Standalone images in content (not inside gallery links)
            var imageSelectors = [
                '.entry-content img',
                '.single-post-content img',
                '.wp-block-image img',
                'figure img'
            ];
            var contentImages = Array.prototype.slice.call(
                document.querySelectorAll(imageSelectors.join(', '))
            ).filter(function (img) {
                // Skip images already inside a gallery trigger link
                return !img.closest(gallerySelectors.join(', '));
            });

            return galleryLinks.concat(contentImages);
        }

        function collect() {
            items = getTriggers().map(function (el) {
                if (el.tagName && el.tagName.toLowerCase() === 'a') {
                    // Link element
                    var thumb = el.querySelector('img');
                    var href = el.getAttribute('href') || '';
                    // If href is not an image, use the img src instead
                    if (!isImageUrl(href) && thumb) {
                        href = getFullImageSrc(thumb);
                    }
                    return {
                        src: href,
                        alt: thumb ? (thumb.getAttribute('alt') || '') : ''
                    };
                } else {
                    // Image element
                    return {
                        src: getFullImageSrc(el),
                        alt: el.getAttribute('alt') || ''
                    };
                }
            }).filter(function (item) {
                return !!item.src;
            });
        }

        function updateControls() {
            var multi = items.length > 1;
            prev.hidden = !multi;
            next.hidden = !multi;
            counter.hidden = !multi;
            counter.textContent = multi ? ((index + 1) + ' / ' + items.length) : '';
        }

        function setImage(item) {
            img.src = item.src;
            img.alt = item.alt;
        }

        function render(i, animate) {
            if (!items.length) return;
            index = (i + items.length) % items.length;
            var item = items[index];
            if (changeTimer) {
                window.clearTimeout(changeTimer);
                changeTimer = null;
            }
            if (animate) {
                overlay.classList.add('is-changing');
                changeTimer = window.setTimeout(function () {
                    setImage(item);
                    window.requestAnimationFrame(function () {
                        overlay.classList.remove('is-changing');
                    });
                }, 90);
            } else {
                setImage(item);
                overlay.classList.remove('is-changing');
            }
            updateControls();
        }

        function openAt(i) {
            collect();
            if (!items.length) return;
            index = Math.max(0, Math.min(i, items.length - 1));
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            document.body.classList.add('wps-lightbox-open');
            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');
            render(index, false);
        }

        function close() {
            overlay.classList.remove('active', 'is-changing');
            overlay.setAttribute('aria-hidden', 'true');
            img.removeAttribute('src');
            document.body.classList.remove('wps-lightbox-open');
            document.body.style.overflow = previousOverflow;
        }

        // Combined click handler for all trigger types
        document.addEventListener('click', function (e) {
            var target = e.target;
            if (!target || !document.body.contains(target)) return;

            var gallerySelectors = [
                '.video-gallery a.wps-lightbox-trigger',
                '.wp-block-gallery a',
                '.player-gallery-grid a'
            ];
            var gallerySelectorStr = gallerySelectors.join(', ');

            // Check for gallery links first
            var anchor = target.closest
                ? target.closest(gallerySelectorStr)
                : null;

            // Then check for standalone content images
            var imgEl = null;
            if (!anchor && target.tagName && target.tagName.toLowerCase() === 'img') {
                if (!target.closest(gallerySelectorStr)) {
                    if (target.closest('.entry-content, .single-post-content, .wp-block-image, figure')) {
                        imgEl = target;
                    }
                }
            }

            if (!anchor && !imgEl) return;

            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }

            var triggers = getTriggers();
            var elToFind = anchor || imgEl;
            var idx = triggers.indexOf(elToFind);
            if (idx >= 0) {
                openAt(idx);
            }
        }, true);

        prev.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (items.length > 1) render(index - 1, true);
        });
        next.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (items.length > 1) render(index + 1, true);
        });
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            close();
        });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) close();
        });
        overlay.addEventListener('touchstart', function (e) {
            if (!e.touches[0]) return;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });
        overlay.addEventListener('touchend', function (e) {
            if (items.length < 2 || !e.changedTouches[0]) return;
            var dx = e.changedTouches[0].clientX - startX;
            var dy = e.changedTouches[0].clientY - startY;
            if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
                render(index + (dx < 0 ? 1 : -1), true);
            }
        }, { passive: true });
        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('active')) return;
            if (e.key === 'Escape') {
                close();
            } else if (e.key === 'ArrowLeft' && items.length > 1) {
                e.preventDefault();
                render(index - 1, true);
            } else if (e.key === 'ArrowRight' && items.length > 1) {
                e.preventDefault();
                render(index + 1, true);
            }
        });
    });
})();
