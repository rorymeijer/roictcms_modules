<?php
// ── Sidebar-navigatie ─────────────────────────────────────────────────────────
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'seo-tools' ? ' active' : '';
    echo '<a href="' . e(BASE_URL) . '/admin/modules/seo-tools/" class="nav-link' . $isActive . '">'
       . '<i class="bi bi-search me-2"></i>SEO Tools</a>';
});

// SEO-metatags injecteren voor pagina's
add_filter('page_head', function (string $html, array $page): string {
    $db  = Database::getInstance();
    $seo = $db->fetch(
        "SELECT * FROM `" . DB_PREFIX . "seo_meta` WHERE object_type='page' AND object_id=?",
        [$page['id'] ?? 0]
    );
    if (!$seo) return $html;

    $inject = '';
    if (!empty($seo['meta_title']))       $inject .= '<title>' . e($seo['meta_title']) . '</title>' . "\n";
    if (!empty($seo['meta_description'])) $inject .= '<meta name="description" content="' . e($seo['meta_description']) . '">' . "\n";
    if (!empty($seo['meta_keywords']))    $inject .= '<meta name="keywords" content="' . e($seo['meta_keywords']) . '">' . "\n";
    if ($seo['no_index'])                 $inject .= '<meta name="robots" content="noindex,nofollow">' . "\n";
    if (!empty($seo['og_title']))         $inject .= '<meta property="og:title" content="' . e($seo['og_title']) . '">' . "\n";
    if (!empty($seo['og_description']))   $inject .= '<meta property="og:description" content="' . e($seo['og_description']) . '">' . "\n";
    if (!empty($seo['og_image']))         $inject .= '<meta property="og:image" content="' . e($seo['og_image']) . '">' . "\n";

    return $inject . $html;
}, 10, 2);

// Sitemap
if (isset($_GET['sitemap']) && $_GET['sitemap'] === 'xml' && Settings::get('seo_sitemap_enabled') === '1') {
    $db    = Database::getInstance();
    $pages = $db->fetchAll("SELECT slug, updated_at FROM `" . DB_PREFIX . "pages` WHERE status='published'");
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($pages as $p) {
        echo '<url><loc>' . e(BASE_URL . '/' . ltrim($p['slug'], '/')) . '</loc>'
           . '<lastmod>' . date('Y-m-d', strtotime($p['updated_at'])) . '</lastmod></url>' . "\n";
    }
    echo '</urlset>';
    exit;
}

// robots.txt
if (isset($_SERVER['REQUEST_URI']) && rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') === '/robots.txt') {
    header('Content-Type: text/plain');
    echo Settings::get('seo_robots_txt', "User-agent: *\nAllow: /");
    exit;
}
