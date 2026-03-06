<?php
/**
 * Customer Portal – admin/index.php
 * Hoofd admin-router: laadt de juiste subpagina op basis van ?page=
 *
 * URL: /admin/modules/customer-portal/?page={customers|quotes|invoices|settings}
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$page     = preg_replace('/[^a-z_]/', '', $_GET['page'] ?? 'customers');
$allowed  = ['customers', 'quotes', 'invoices', 'settings'];
$page     = in_array($page, $allowed) ? $page : 'customers';

$pageMap  = [
    'customers' => ['title' => 'Klanten',     'active' => 'cp-customers'],
    'quotes'    => ['title' => 'Offertes',     'active' => 'cp-quotes'],
    'invoices'  => ['title' => 'Facturen',     'active' => 'cp-invoices'],
    'settings'  => ['title' => 'Instellingen', 'active' => 'cp-settings'],
];

$pageTitle  = 'Klantenpaneel – ' . $pageMap[$page]['title'];
$activePage = $pageMap[$page]['active'];

require_once ADMIN_PATH . '/includes/header.php';

// Laad de subpagina (zonder opnieuw Auth::requireAdmin te callen)
require __DIR__ . '/_' . $page . '.php';

require_once ADMIN_PATH . '/includes/footer.php';
