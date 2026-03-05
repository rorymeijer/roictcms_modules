/**
 * Popup Manager - Trigger Logic
 * Supports: time delay, exit-intent
 * Cookie management for show_once option
 */
(function () {
    'use strict';

    /**
     * Get a cookie by name.
     * @param {string} name
     * @returns {string|null}
     */
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&') + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    /**
     * Set a cookie.
     * @param {string} name
     * @param {string} value
     * @param {number} days
     */
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    /**
     * Show a popup element.
     * @param {HTMLElement} popup
     */
    function showPopup(popup) {
        var popupId   = popup.getAttribute('data-popup-id');
        var showOnce  = popup.getAttribute('data-show-once') === '1';
        var cookieDays = parseInt(popup.getAttribute('data-cookie-days'), 10) || 7;
        var cookieName = 'pm_popup_' + popupId;

        // Check if already shown and show_once is enabled
        if (showOnce && getCookie(cookieName)) {
            return;
        }

        popup.style.display = '';

        if (showOnce) {
            setCookie(cookieName, '1', cookieDays);
        }
    }

    /**
     * Close a popup element.
     * @param {HTMLElement} popup
     */
    function closePopup(popup) {
        popup.style.display = 'none';
    }

    /**
     * Initialise all popup elements.
     */
    function initPopups() {
        var popups = document.querySelectorAll('.pm-popup');
        var exitTriggered = false;

        popups.forEach(function (popup) {
            var trigger    = popup.getAttribute('data-trigger');
            var delay      = parseInt(popup.getAttribute('data-delay'), 10) || 0;

            // Close button
            var closeBtn = popup.querySelector('.pm-popup-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    closePopup(popup);
                });
            }

            // Click on overlay to close
            var overlay = popup.querySelector('.pm-popup-overlay');
            if (overlay) {
                overlay.addEventListener('click', function () {
                    closePopup(popup);
                });
            }

            // Close on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && popup.style.display !== 'none') {
                    closePopup(popup);
                }
            });

            if (trigger === 'time') {
                setTimeout(function () {
                    showPopup(popup);
                }, delay * 1000);
            } else if (trigger === 'exit_intent') {
                document.addEventListener('mouseleave', function handler(e) {
                    if (e.clientY <= 0 && !exitTriggered) {
                        exitTriggered = true;
                        showPopup(popup);
                        document.removeEventListener('mouseleave', handler);
                    }
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPopups);
    } else {
        initPopups();
    }
}());
