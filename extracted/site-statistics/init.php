<?php
/**
 * Site Statistieken – init.php
 * Geladen bij elke request. Registreert hooks.
 */

if (!defined('BASE_PATH')) return;

// ── Sidebar-navigatie ─────────────────────────────────────────────────────────
add_action('admin_sidebar_nav', function (string $active) {
    $cls = ($active === 'site-statistics') ? ' active' : '';
    echo '<li class="nav-item">'
        . '<a class="nav-link' . $cls . '" href="' . e(BASE_URL) . '/admin/modules/site-statistics/">'
        . '<i class="bi bi-bar-chart-line me-2"></i>Statistieken'
        . '</a></li>';
});

// ── Admin head: Chart.js + CSS (alleen op statistiekenpagina) ─────────────────
add_action('admin_head', function () {
    if (($_GET['slug'] ?? '') !== 'site-statistics') return;
    echo '<link rel="stylesheet" href="' . e(BASE_URL) . '/modules/site-statistics/assets/css/statistics.css">';
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>';
});

// ── Admin footer: JS (alleen op statistiekenpagina) ───────────────────────────
add_action('admin_footer', function () {
    if (($_GET['slug'] ?? '') !== 'site-statistics') return;
    echo '<script src="' . e(BASE_URL) . '/modules/site-statistics/assets/js/statistics.js"></script>';
});

// ── Frontend tracking snippet ─────────────────────────────────────────────────
add_action('theme_footer', function () {
    if (!Settings::get('stats_enabled', '1')) return;

    // Sla ingelogde beheerders over
    if (Settings::get('stats_exclude_admin', '1') && Auth::isLoggedIn()) return;

    $trackUrl      = BASE_URL . '/modules/site-statistics/api/track.php';
    $trackUrlJson  = json_encode($trackUrl);
    $trackUrlHtml  = e($trackUrl);
    $currentUrl    = urlencode($_SERVER['REQUEST_URI'] ?? '/');
    $respectDnt    = Settings::get('stats_respect_dnt', '1') ? 'true' : 'false';
    $requireConsent = Settings::get('stats_require_consent', '0') ? 'true' : 'false';

    echo '<script>' . "\n";
    echo '(function(){' . "\n";
    echo '  var TRACK_URL = ' . $trackUrlJson . ';' . "\n";
    echo '  var RESPECT_DNT = ' . $respectDnt . ';' . "\n";
    echo '  var NEED_CONSENT = ' . $requireConsent . ';' . "\n";
    echo '  if (RESPECT_DNT && (navigator.doNotTrack === "1" || window.doNotTrack === "1")) return;' . "\n";
    echo '  if (document.cookie.split(";").some(function(c){return c.trim().startsWith("stats_optout=1");})) return;' . "\n";
    echo '  if (NEED_CONSENT && !document.cookie.split(";").some(function(c){return c.trim().startsWith("stats_consent=1");})) return;' . "\n";
    echo '  var sid = sessionStorage.getItem("_roict_sid");' . "\n";
    echo '  if (!sid) { sid = Math.random().toString(36).slice(2) + Date.now().toString(36); sessionStorage.setItem("_roict_sid", sid); }' . "\n";
    echo '  var payload = JSON.stringify({url: location.href, title: document.title, referrer: document.referrer, screen: screen.width+"x"+screen.height, lang: navigator.language||"", sid: sid});' . "\n";
    echo '  if (navigator.sendBeacon) { navigator.sendBeacon(TRACK_URL, new Blob([payload], {type:"application/json"})); }' . "\n";
    echo '  else { var x = new XMLHttpRequest(); x.open("POST", TRACK_URL, true); x.setRequestHeader("Content-Type","application/json"); x.send(payload); }' . "\n";
    echo '})();' . "\n";
    echo '</script>' . "\n";
    echo '<noscript><img src="' . $trackUrlHtml . '?noscript=1&url=' . $currentUrl . '" width="1" height="1" alt="" style="position:absolute;left:-9999px;top:-9999px;"></noscript>' . "\n";
}, 20);

// ── Opt-out helper-functies voor gebruik in themes ────────────────────────────
function site_stats_optout_url(): string
{
    return BASE_URL . '/modules/site-statistics/api/track.php?action=optout';
}

function site_stats_optin_url(): string
{
    return BASE_URL . '/modules/site-statistics/api/track.php?action=optin';
}
