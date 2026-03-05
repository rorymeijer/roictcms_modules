<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

class CookieConsentModule
{
    public static function renderBanner(): string
    {
        $title       = Settings::get('cookie_consent_title', 'Cookievoorkeuren');
        $text        = Settings::get('cookie_consent_text', 'Wij gebruiken cookies voor een optimale werking van de website.');
        $acceptAll   = Settings::get('cookie_consent_accept_all', 'Alles accepteren');
        $reject      = Settings::get('cookie_consent_reject', 'Alleen noodzakelijk');
        $privacyUrl  = Settings::get('cookie_consent_privacy_url', '');

        $privacyLink = '';
        if (!empty($privacyUrl)) {
            $privacyLink = ' <a href="' . e($privacyUrl) . '" class="cookie-consent-privacy-link" target="_blank" rel="noopener">Meer info</a>';
        }

        $html  = '<div id="cookie-consent-banner" class="cookie-consent-banner" style="display:none" aria-live="polite" role="dialog" aria-label="Cookiemelding">';
        $html .= '<div class="cookie-consent-inner">';
        $html .= '<div class="cookie-consent-text">';
        $html .= '<strong>' . e($title) . '</strong>';
        $html .= '<p class="mb-0">' . e($text) . $privacyLink . '</p>';
        $html .= '</div>';
        $html .= '<div class="cookie-consent-buttons">';
        $html .= '<button id="cookie-consent-reject" class="btn btn-outline-secondary btn-sm">' . e($reject) . '</button>';
        $html .= '<button id="cookie-consent-accept" class="btn btn-primary btn-sm">' . e($acceptAll) . '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}

// Admin sidebar link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/cookie-consent/admin/';
    echo '<li class="nav-item"><a class="nav-link" href="' . $url . '"><i class="bi bi-shield-check me-2"></i>Cookie Consent</a></li>';
});

// Load CSS in theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/cookie-consent/assets/css/cookie-consent.css">';
});

// Render banner + JS in theme footer
add_action('theme_footer', function () {
    echo CookieConsentModule::renderBanner();
    echo '<script src="' . BASE_URL . '/modules/cookie-consent/assets/js/cookie-consent.js"></script>';
});
