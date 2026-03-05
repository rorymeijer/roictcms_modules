(function () {
    'use strict';

    var COOKIE_NAME = 'cookie_consent';
    var COOKIE_DURATION_DAYS = 365;

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, days) {
        var expires = new Date();
        expires.setDate(expires.getDate() + days);
        document.cookie = name + '=' + encodeURIComponent(value) +
            '; expires=' + expires.toUTCString() +
            '; path=/' +
            '; SameSite=Lax';
    }

    function hideBanner() {
        var banner = document.getElementById('cookie-consent-banner');
        if (banner) {
            banner.style.display = 'none';
        }
    }

    function showBanner() {
        var banner = document.getElementById('cookie-consent-banner');
        if (banner) {
            banner.style.display = 'block';
        }
    }

    function init() {
        var consentValue = getCookie(COOKIE_NAME);

        if (consentValue) {
            // Consent already given, keep banner hidden
            hideBanner();
            return;
        }

        // Show banner
        showBanner();

        var acceptBtn = document.getElementById('cookie-consent-accept');
        var rejectBtn = document.getElementById('cookie-consent-reject');

        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                setCookie(COOKIE_NAME, 'all', COOKIE_DURATION_DAYS);
                hideBanner();
            });
        }

        if (rejectBtn) {
            rejectBtn.addEventListener('click', function () {
                setCookie(COOKIE_NAME, 'necessary', COOKIE_DURATION_DAYS);
                hideBanner();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
