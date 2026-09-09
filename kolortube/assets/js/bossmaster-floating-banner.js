(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var closeButton = event.target.closest(
            '[data-ad-close], #floating-widget .widget-close, #floating-widget .promo-close'
        );

        if (!closeButton) {
            return;
        }

        var banner = closeButton.closest(
            '#floating-widget .promo-unit, #promo_04'
        );

        if (!banner) {
            return;
        }

        event.preventDefault();
        banner.style.setProperty('display', 'none', 'important');
        banner.setAttribute('aria-hidden', 'true');
    });
}());
