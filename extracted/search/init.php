<?php
/**
 * Zoekmodule - Init
 */

defined('BASE_PATH') || exit('No direct access');

class SearchModule
{
    /**
     * Render het zoekformulier (shortcode: [search_form]).
     */
    public static function renderForm(): string
    {
        $query = htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8');
        $actionUrl = htmlspecialchars(BASE_URL . '/', ENT_QUOTES, 'UTF-8');

        return '<form method="get" action="' . $actionUrl . '" class="search-form d-flex gap-2" role="search">'
            . '<input type="text" name="q" class="form-control search-input" '
            . 'placeholder="Zoeken..." value="' . $query . '" required>'
            . '<button type="submit" class="btn btn-primary search-btn">'
            . '<i class="bi bi-search"></i>'
            . '</button>'
            . '</form>';
    }

    /**
     * Render zoekresultaten (shortcode: [search_results]).
     */
    public static function renderResults(): string
    {
        $q = trim($_GET['q'] ?? '');

        if ($q === '') {
            return '';
        }

        $db         = Database::getInstance();
        $maxResults = (int) ($db->fetchOne(
            "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'search_max_results'"
        ) ?: 10);
        $searchPages = ($db->fetchOne(
            "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'search_pages'"
        ) ?: '1') === '1';
        $searchNews = ($db->fetchOne(
            "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'search_news'"
        ) ?: '1') === '1';

        $results = [];
        $like    = '%' . $q . '%';

        if ($searchPages) {
            $pages = $db->fetchAll(
                "SELECT id, title, slug, content, 'page' AS type
                   FROM " . DB_PREFIX . "pages
                  WHERE status = 'published' AND (title LIKE ? OR content LIKE ?)
                  LIMIT ?",
                [$like, $like, $maxResults]
            );
            foreach ($pages as $p) {
                $results[] = [
                    'type'    => 'Pagina',
                    'title'   => $p['title'],
                    'excerpt' => mb_substr(strip_tags($p['content']), 0, 200),
                    'url'     => BASE_URL . '/' . ltrim($p['slug'], '/'),
                ];
            }
        }

        if ($searchNews) {
            $news = $db->fetchAll(
                "SELECT id, title, slug, excerpt, content, 'news' AS type
                   FROM " . DB_PREFIX . "news
                  WHERE status = 'published' AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)
                  LIMIT ?",
                [$like, $like, $like, $maxResults]
            );
            foreach ($news as $n) {
                $excerpt = $n['excerpt'] ?: mb_substr(strip_tags($n['content']), 0, 200);
                $results[] = [
                    'type'    => 'Nieuws',
                    'title'   => $n['title'],
                    'excerpt' => mb_substr($excerpt, 0, 200),
                    'url'     => BASE_URL . '/nieuws/' . ltrim($n['slug'], '/'),
                ];
            }
        }

        // Beperk totaal aantal resultaten
        $results = array_slice($results, 0, $maxResults);
        $count   = count($results);

        $qEsc = htmlspecialchars($q, ENT_QUOTES, 'UTF-8');

        $html  = '<div class="search-results">';
        $html .= '<p class="search-count text-muted mb-3">';
        $html .= '<strong>' . $count . '</strong> resultaat' . ($count !== 1 ? 'en' : '') . " voor '<em>{$qEsc}</em>'";
        $html .= '</p>';

        if ($count === 0) {
            $html .= '<div class="alert alert-info">Geen resultaten gevonden. Probeer een andere zoekterm.</div>';
        } else {
            $html .= '<ul class="search-result-list list-unstyled">';
            foreach ($results as $r) {
                $titleEsc   = htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8');
                $excerptEsc = htmlspecialchars($r['excerpt'], ENT_QUOTES, 'UTF-8');
                $urlEsc     = htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8');
                $typeEsc    = htmlspecialchars($r['type'], ENT_QUOTES, 'UTF-8');

                $html .= '<li class="search-result-item mb-4">';
                $html .= '<span class="badge bg-secondary mb-1">' . $typeEsc . '</span>';
                $html .= '<h5 class="search-result-title mb-1"><a href="' . $urlEsc . '">' . $titleEsc . '</a></h5>';
                $html .= '<p class="search-result-excerpt text-muted small mb-0">' . $excerptEsc . '</p>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }
}

// Sidebar navigatie koppeling
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/search/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-search me-2"></i>Zoekmodule'
        . '</a></li>';
});

// CSS laden in de theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/search/assets/css/search.css">';
});

// Shortcodes registreren
if (function_exists('add_shortcode')) {
    add_shortcode('search_form', ['SearchModule', 'renderForm']);
    add_shortcode('search_results', ['SearchModule', 'renderResults']);
}
