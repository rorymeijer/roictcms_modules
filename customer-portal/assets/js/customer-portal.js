/**
 * Customer Portal – customer-portal.js
 * Lichtgewicht JavaScript voor het frontend klantenpaneel.
 */

(function () {
    'use strict';

    /**
     * Markeer actieve navigatielink op basis van huidig pad.
     */
    function markActiveNav() {
        const path = window.location.pathname.replace(/\/$/, '');
        document.querySelectorAll('.cp-nav-link').forEach(function (link) {
            const href = link.getAttribute('href');
            if (!href) return;
            const linkPath = href.replace(/\/$/, '');
            if (path === linkPath) {
                link.classList.add('active');
            } else if (path.startsWith(linkPath + '/') && linkPath !== '') {
                // Deelpad, maar alleen als het geen root is
                link.classList.add('active');
            }
        });
    }

    /**
     * Formateer bedragen als Europees getal (1.234,56).
     */
    function formatAmount(num) {
        return num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /**
     * Statistiek-kaarten: fade-in animatie.
     */
    function animateStats() {
        const cards = document.querySelectorAll('.cp-stat-card');
        cards.forEach(function (card, i) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(12px)';
            card.style.transition = 'opacity .3s ease ' + (i * 0.08) + 's, transform .3s ease ' + (i * 0.08) + 's';
            setTimeout(function () {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        });
    }

    /**
     * Bevestigingsdialoog voor gevaarlijke acties.
     */
    function bindConfirmLinks() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (!confirm(el.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    }

    /**
     * Flash-berichten automatisch verbergen.
     */
    function autoHideAlerts() {
        document.querySelectorAll('.cp-alert').forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity .5s';
                alert.style.opacity = '0';
                setTimeout(function () { alert.remove(); }, 500);
            }, 5000);
        });
    }

    /**
     * Print-knop feedback.
     */
    function bindPrintButtons() {
        document.querySelectorAll('[data-print]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                window.print();
            });
        });
    }

    // Init
    document.addEventListener('DOMContentLoaded', function () {
        markActiveNav();
        animateStats();
        bindConfirmLinks();
        autoHideAlerts();
        bindPrintButtons();
    });
})();
