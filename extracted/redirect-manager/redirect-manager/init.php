<?php
// ── Sidebar-navigatie ─────────────────────────────────────────────────────────
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'redirect-manager' ? ' active' : '';
    echo '<a href="' . e(BASE_URL) . '/admin/modules/redirect-manager/" class="nav-link' . $isActive . '">'
       . '<i class="bi bi-arrow-left-right me-2"></i>Redirect Manager</a>';
});

// Redirect-check direct uitvoeren (vóór de router), zodat paden die niet in het CMS bestaan
// toch correct doorgestuurd worden in plaats van een 404 te tonen.
(function () {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    // Niet uitvoeren voor admin-pagina's
    if (str_starts_with($path, '/admin')) return;

    $uri = '/' . ltrim($path, '/');
    $db  = Database::getInstance();
    $row = $db->fetch(
        "SELECT * FROM `" . DB_PREFIX . "redirects` WHERE source = ? AND active = 1 LIMIT 1",
        [$uri]
    );
    if ($row) {
        if (Settings::get('redirects_log_hits', '1') === '1') {
            $db->query("UPDATE `" . DB_PREFIX . "redirects` SET hits = hits + 1 WHERE id = ?", [$row['id']]);
        }
        header('Location: ' . $row['destination'], true, (int)$row['type']);
        exit;
    }
})();
