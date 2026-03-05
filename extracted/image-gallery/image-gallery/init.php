<?php
// ── Sidebar-navigatie ─────────────────────────────────────────────────────────
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'image-gallery' ? ' active' : '';
    echo '<a href="' . e(BASE_URL) . '/admin/modules/image-gallery/" class="nav-link' . $isActive . '">'
       . '<i class="bi bi-images me-2"></i>Galerijen</a>';
});

// Geen frontend-hooks nodig; de widget wordt via functions.php aangeroepen.
