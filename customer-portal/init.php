<?php
/**
 * Customer Portal – init.php
 * Wordt geladen bij elke pagina-aanvraag als de module actief is.
 */

// ----------------------------------------------------------------
// Hulpfuncties
// ----------------------------------------------------------------

/**
 * Controleer of de ingelogde frontend-gebruiker een klantprofiel heeft.
 * Geeft de klant-rij terug of false.
 */
function cp_get_current_customer(): array|false
{
    if (!class_exists('FrontendLoginModule') || !FrontendLoginModule::isLoggedIn()) {
        return false;
    }
    $user = FrontendLoginModule::currentUser();
    if (!$user) {
        return false;
    }
    $db = Database::getInstance();
    return $db->fetch(
        "SELECT * FROM `" . DB_PREFIX . "cp_customers` WHERE `fl_user_id` = ? OR `email` = ? LIMIT 1",
        [$user['id'], $user['email']]
    );
}

/**
 * Genereer een volgend offertenummer.
 */
function cp_next_quote_number(): string
{
    $settings = Settings::getInstance();
    $prefix   = $settings->get('cp_quote_prefix')   ?: 'O';
    $counter  = (int)($settings->get('cp_quote_counter') ?: 1);
    $settings->set('cp_quote_counter', $counter + 1);
    return $prefix . str_pad($counter, 4, '0', STR_PAD_LEFT);
}

/**
 * Genereer een volgend factuurnummer.
 */
function cp_next_invoice_number(): string
{
    $settings = Settings::getInstance();
    $prefix   = $settings->get('cp_invoice_prefix')   ?: 'F';
    $counter  = (int)($settings->get('cp_invoice_counter') ?: 1);
    $settings->set('cp_invoice_counter', $counter + 1);
    return $prefix . str_pad($counter, 4, '0', STR_PAD_LEFT);
}

/**
 * Bereken offerteregel-totalen en sla op.
 */
function cp_recalculate_quote(int $quoteId): void
{
    $db  = Database::getInstance();
    $p   = DB_PREFIX;

    $items    = $db->fetchAll("SELECT * FROM `{$p}cp_quote_items` WHERE `quote_id` = ?", [$quoteId]);
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['line_total'];
    }

    $quote    = $db->fetch("SELECT * FROM `{$p}cp_quotes` WHERE `id` = ?", [$quoteId]);
    $discount = (float)($quote['discount'] ?? 0);
    $taxRate  = (float)($quote['tax_rate']  ?? 21);
    $base     = $subtotal - $discount;
    $tax      = round($base * $taxRate / 100, 2);
    $total    = $base + $tax;

    $db->update("{$p}cp_quotes", [
        'subtotal'   => $subtotal,
        'tax_amount' => $tax,
        'total'      => $total,
    ], ['id' => $quoteId]);
}

/**
 * Bereken factuurregel-totalen en sla op.
 */
function cp_recalculate_invoice(int $invoiceId): void
{
    $db  = Database::getInstance();
    $p   = DB_PREFIX;

    $items    = $db->fetchAll("SELECT * FROM `{$p}cp_invoice_items` WHERE `invoice_id` = ?", [$invoiceId]);
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['line_total'];
    }

    $invoice  = $db->fetch("SELECT * FROM `{$p}cp_invoices` WHERE `id` = ?", [$invoiceId]);
    $discount = (float)($invoice['discount'] ?? 0);
    $taxRate  = (float)($invoice['tax_rate']  ?? 21);
    $base     = $subtotal - $discount;
    $tax      = round($base * $taxRate / 100, 2);
    $total    = $base + $tax;

    $db->update("{$p}cp_invoices", [
        'subtotal'   => $subtotal,
        'tax_amount' => $tax,
        'total'      => $total,
    ], ['id' => $invoiceId]);
}

// ----------------------------------------------------------------
// Admin navigatie
// ----------------------------------------------------------------
add_action('admin_sidebar_nav', function (string $activePage) {
    $pages = [
        'klantenpaneel'          => ['label' => 'Klanten',    'icon' => 'people',        'url' => BASE_URL . 'admin/?module=customer-portal&page=customers'],
        'klantenpaneel-quotes'   => ['label' => 'Offertes',   'icon' => 'file-earmark-text', 'url' => BASE_URL . 'admin/?module=customer-portal&page=quotes'],
        'klantenpaneel-invoices' => ['label' => 'Facturen',   'icon' => 'receipt',       'url' => BASE_URL . 'admin/?module=customer-portal&page=invoices'],
        'klantenpaneel-settings' => ['label' => 'Instellingen', 'icon' => 'gear',        'url' => BASE_URL . 'admin/?module=customer-portal&page=settings'],
    ];

    echo '<li class="nav-section-title mt-3">Klantenpaneel</li>';
    foreach ($pages as $pageKey => $info) {
        $active = ($activePage === $pageKey) ? 'active' : '';
        printf(
            '<li><a href="%s" class="nav-link %s"><sl-icon name="%s"></sl-icon> %s</a></li>',
            e($info['url']),
            $active,
            e($info['icon']),
            e($info['label'])
        );
    }
});

// ----------------------------------------------------------------
// Admin paginarouter
// ----------------------------------------------------------------
add_action('admin_module_page', function (string $module, string $page) {
    if ($module !== 'customer-portal') {
        return;
    }
    $allowed = ['customers', 'quotes', 'invoices', 'settings'];
    $page    = in_array($page, $allowed) ? $page : 'customers';
    $file    = __DIR__ . '/admin/' . $page . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// ----------------------------------------------------------------
// Frontend CSS/JS toevoegen
// ----------------------------------------------------------------
add_action('theme_head', function () {
    $settings = Settings::getInstance();
    if ($settings->get('cp_portal_enabled') !== '1') {
        return;
    }
    echo '<link rel="stylesheet" href="' . BASE_URL . 'modules/customer-portal/assets/css/customer-portal.css">' . "\n";
});

add_action('theme_footer', function () {
    $settings = Settings::getInstance();
    if ($settings->get('cp_portal_enabled') !== '1') {
        return;
    }
    echo '<script src="' . BASE_URL . 'modules/customer-portal/assets/js/customer-portal.js"></script>' . "\n";
});

// ----------------------------------------------------------------
// Frontend routing – onderschep klantenpaneel-URL's
// ----------------------------------------------------------------
add_action('frontend_route', function (string $uri) {
    $settings = Settings::getInstance();

    if ($settings->get('cp_portal_enabled') !== '1') {
        return;
    }

    $slug = trim($settings->get('cp_portal_slug') ?: 'klanten-portaal', '/');
    $uri  = trim($uri, '/');

    // Komt de aanvraag overeen met het portaal-pad?
    if ($uri !== $slug && strpos($uri, $slug . '/') !== 0) {
        return;
    }

    // Frontend login verplicht
    if (!class_exists('FrontendLoginModule') || !FrontendLoginModule::isLoggedIn()) {
        $loginSlug = Settings::getInstance()->get('fl_login_page_slug') ?: 'inloggen';
        redirect(BASE_URL . $loginSlug . '?redirect=' . urlencode('/' . $uri));
    }

    // Bepaal sub-pagina
    $sub = '';
    if (strlen($uri) > strlen($slug)) {
        $sub = ltrim(substr($uri, strlen($slug)), '/');
    }

    // Klantprofiel ophalen (maak automatisch aan als nog niet bestaat)
    $customer = cp_get_current_customer();
    if (!$customer) {
        // Automatisch klantprofiel aanmaken op basis van login-data
        $user = FrontendLoginModule::currentUser();
        $db   = Database::getInstance();
        $db->insert(DB_PREFIX . 'cp_customers', [
            'fl_user_id'   => $user['id'],
            'contact_name' => $user['username'] ?? $user['email'],
            'email'        => $user['email'],
            'status'       => 'active',
        ]);
        $customer = cp_get_current_customer();
    }

    // Route naar sub-pagina
    $map = [
        ''          => __DIR__ . '/frontend/portal.php',
        'offertes'  => __DIR__ . '/frontend/quotes.php',
        'facturen'  => __DIR__ . '/frontend/invoices.php',
        'profiel'   => __DIR__ . '/frontend/profile.php',
    ];

    // Enkelvoudige offerte/factuur detail-pagina's
    if (preg_match('#^offertes/(\d+)$#', $sub, $m)) {
        $_GET['cp_quote_id'] = (int)$m[1];
        $file = __DIR__ . '/frontend/quote-detail.php';
    } elseif (preg_match('#^facturen/(\d+)$#', $sub, $m)) {
        $_GET['cp_invoice_id'] = (int)$m[1];
        $file = __DIR__ . '/frontend/invoice-detail.php';
    } else {
        $file = $map[$sub] ?? null;
    }

    if ($file && file_exists($file)) {
        // Markeer als afgehandeld zodat de hoofd-router stopt
        define('CP_PORTAL_HANDLED', true);
        require $file;
        exit;
    }

    // 404 als geen match
    http_response_code(404);
    define('CP_PORTAL_HANDLED', true);
    require THEMES_PATH . (Settings::getInstance()->get('active_theme') ?: 'default') . '/404.php';
    exit;
});
