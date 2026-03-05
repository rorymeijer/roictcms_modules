<?php
/**
 * Portfolio Module - Init
 * Registreert hooks, shortcodes en de PortfolioModule klasse.
 */

class PortfolioModule
{
    /**
     * Haalt portfolio items + categorienamen op en rendert een gefilterd grid.
     */
    public static function renderPortfolio(): string
    {
        $db = Database::getInstance();

        // Haal categorieën op
        $categories = $db->fetchAll("SELECT * FROM " . DB_PREFIX . "portfolio_categories ORDER BY name ASC");

        // Haal items op met categorienaam via JOIN
        $stmt = $db->query("
            SELECT i.*, c.name AS category_name, c.slug AS category_slug
            FROM " . DB_PREFIX . "portfolio_items i
            LEFT JOIN " . DB_PREFIX . "portfolio_categories c ON i.category_id = c.id
            WHERE i.status = 'active'
            ORDER BY i.sort_order ASC, i.id ASC
        ");
        $items = $stmt->fetchAll();

        if (empty($items)) {
            return '<p class="text-muted">Geen portfolio items beschikbaar.</p>';
        }

        $html = '<div class="roict-portfolio">';

        // Filter knoppen
        if (!empty($categories)) {
            $html .= '<div class="roict-portfolio-filters mb-4 d-flex flex-wrap gap-2">';
            $html .= '<button class="btn btn-primary btn-sm roict-filter-btn active" data-filter="all">Alles</button>';
            foreach ($categories as $cat) {
                $html .= '<button class="btn btn-outline-primary btn-sm roict-filter-btn" data-filter="' . e($cat['slug']) . '">' . e($cat['name']) . '</button>';
            }
            $html .= '</div>';
        }

        // Items grid
        $html .= '<div class="row g-4" id="roict-portfolio-grid">';
        foreach ($items as $item) {
            $catSlug = $item['category_slug'] ?? '';
            $html .= '<div class="col-12 col-sm-6 col-lg-4 roict-portfolio-item" data-category="' . e($catSlug) . '">';
            $html .= '<div class="card h-100 shadow-sm roict-portfolio-card">';

            if (!empty($item['image_url'])) {
                $html .= '<div class="roict-portfolio-img-wrap">';
                $html .= '<img src="' . e($item['image_url']) . '" alt="' . e($item['title']) . '" class="card-img-top">';
                $html .= '</div>';
            }

            $html .= '<div class="card-body">';
            if (!empty($item['category_name'])) {
                $html .= '<span class="badge bg-secondary mb-2">' . e($item['category_name']) . '</span>';
            }
            $html .= '<h5 class="card-title">' . e($item['title']) . '</h5>';
            if (!empty($item['description'])) {
                $html .= '<p class="card-text text-muted small">' . e($item['description']) . '</p>';
            }
            if (!empty($item['url'])) {
                $html .= '<a href="' . e($item['url']) . '" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">';
                $html .= '<i class="bi bi-box-arrow-up-right me-1"></i>Bekijk project';
                $html .= '</a>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}

// Sidebar navigatie link
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/portfolio/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-grid me-2"></i>Portfolio'
        . '</a></li>';
});

// Laad CSS en JS in de theme head
add_action('theme_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/portfolio/assets/css/portfolio.css">';
});

add_action('theme_footer', function () {
    echo '<script src="' . BASE_URL . '/modules/portfolio/assets/js/portfolio.js"></script>';
});

// Registreer shortcode [portfolio]
add_shortcode('portfolio', function ($atts) {
    return PortfolioModule::renderPortfolio();
});
