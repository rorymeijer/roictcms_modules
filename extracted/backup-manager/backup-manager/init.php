<?php
// ── Sidebar-navigatie ─────────────────────────────────────────────────────────
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'backup-manager' ? ' active' : '';
    echo '<a href="' . e(BASE_URL) . '/admin/modules/backup-manager/" class="nav-link' . $isActive . '">'
       . '<i class="bi bi-database-down me-2"></i>Backup Manager</a>';
});

// Geen frontend-init nodig voor backup-manager.
