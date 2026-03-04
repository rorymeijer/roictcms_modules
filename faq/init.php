<?php
// ── Sidebar-navigatie ─────────────────────────────────────────────────────────
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'faq' ? ' active' : '';
    echo '<a href="' . e(BASE_URL) . '/admin/modules/faq/" class="nav-link' . $isActive . '">'
       . '<i class="bi bi-question-circle me-2"></i>FAQ</a>';
});

// FAQ-assets laden
add_action('frontend_head', function () {
    echo '<link rel="stylesheet" href="' . BASE_URL . '/modules/faq/assets/css/faq.css">';
});

// ── [faq CATEGORY-ID] shortcode ───────────────────────────────────────────────
// Gebruik: [faq] voor alle vragen, [faq 3] voor categorie met id 3
require_once __DIR__ . '/functions.php';
add_shortcode('faq', function ($args) {
    $categoryId = 0;
    if (!empty($args)) {
        if (isset($args['id'])) {
            $categoryId = (int) $args['id'];
        } elseif (isset($args[0])) {
            $categoryId = (int) $args[0];
        }
    }
    return faq_render_widget($categoryId);
});
