<?php
/**
 * Customer Portal – frontend/invoice-detail.php
 * Detailpagina van een factuur voor de klant.
 */

$db        = Database::getInstance();
$settings  = Settings::getInstance();
$p         = DB_PREFIX;
$customer  = cp_get_current_customer();
$slug      = trim($settings->get('cp_portal_slug') ?: 'klanten-portaal', '/');
$invoiceId = (int)($_GET['cp_invoice_id'] ?? 0);

$invoice = $db->fetch(
    "SELECT * FROM `{$p}cp_invoices` WHERE `id` = ? AND `customer_id` = ?",
    [$invoiceId, $customer['id']]
);

if (!$invoice) {
    http_response_code(404);
    $activeTheme = $settings->get('active_theme') ?: 'default';
    require THEMES_PATH . $activeTheme . '/header.php';
    echo '<div class="cp-portal"><p class="cp-empty">Factuur niet gevonden.</p></div>';
    require THEMES_PATH . $activeTheme . '/footer.php';
    return;
}

$items = $db->fetchAll(
    "SELECT * FROM `{$p}cp_invoice_items` WHERE `invoice_id` = ? ORDER BY `sort_order`",
    [$invoiceId]
);

$overdue     = $invoice['status'] === 'sent' && $invoice['due_date'] && $invoice['due_date'] < date('Y-m-d');
$statusLabel = ['concept' => 'Concept', 'sent' => 'Openstaand', 'paid' => 'Betaald', 'overdue' => 'Verlopen', 'cancelled' => 'Geannuleerd'][$overdue ? 'overdue' : $invoice['status']] ?? $invoice['status'];
$activeTheme = $settings->get('active_theme') ?: 'default';
require THEMES_PATH . $activeTheme . '/header.php';
?>

<div class="cp-portal">
    <div class="cp-portal-header">
        <h1>Factuur <?= e($invoice['invoice_number']) ?></h1>
        <nav class="cp-nav">
            <a href="<?= BASE_URL . $slug ?>" class="cp-nav-link">⊞ Dashboard</a>
            <a href="<?= BASE_URL . $slug ?>/offertes" class="cp-nav-link">📄 Offertes</a>
            <a href="<?= BASE_URL . $slug ?>/facturen" class="cp-nav-link active">🧾 Facturen</a>
            <a href="<?= BASE_URL . $slug ?>/profiel" class="cp-nav-link">👤 Profiel</a>
            <a href="<?= BASE_URL ?>uitloggen" class="cp-nav-link cp-nav-logout">↩ Uitloggen</a>
        </nav>
    </div>

    <div class="cp-portal-body">
        <div class="cp-document">
            <div class="cp-doc-header">
                <div class="cp-doc-sender">
                    <?php if ($settings->get('cp_company_name')): ?>
                        <strong><?= e($settings->get('cp_company_name')) ?></strong><br>
                        <?= e($settings->get('cp_company_address')) ?><br>
                        <?= e($settings->get('cp_company_postcode')) ?> <?= e($settings->get('cp_company_city')) ?><br>
                        <?php if ($settings->get('cp_company_kvk')): ?>KVK: <?= e($settings->get('cp_company_kvk')) ?><br><?php endif; ?>
                        <?php if ($settings->get('cp_company_btw')): ?>BTW: <?= e($settings->get('cp_company_btw')) ?><br><?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="cp-doc-meta">
                    <table class="cp-meta-table">
                        <tr><th>Factuurnummer</th><td><?= e($invoice['invoice_number']) ?></td></tr>
                        <tr><th>Factuurdatum</th><td><?= $invoice['invoice_date'] ? date('d-m-Y', strtotime($invoice['invoice_date'])) : '–' ?></td></tr>
                        <tr><th>Vervaldatum</th><td><?= $invoice['due_date'] ? date('d-m-Y', strtotime($invoice['due_date'])) : '–' ?></td></tr>
                        <tr><th>Status</th>
                            <td><span class="cp-badge cp-badge-<?= $overdue ? 'overdue' : e($invoice['status']) ?>"><?= e($statusLabel) ?></span></td>
                        </tr>
                    </table>
                </div>
            </div>

            <h2 class="cp-doc-title"><?= e($invoice['title']) ?></h2>

            <?php if ($invoice['intro']): ?>
                <div class="cp-doc-intro"><?= nl2br(e($invoice['intro'])) ?></div>
            <?php endif; ?>

            <table class="cp-doc-items">
                <thead>
                    <tr>
                        <th>Omschrijving</th>
                        <th class="text-right">Aantal</th>
                        <th class="text-right">Prijs</th>
                        <th class="text-right">Totaal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['description']) ?></td>
                        <td class="text-right"><?= number_format($item['quantity'], 2, ',', '.') ?></td>
                        <td class="text-right">€ <?= number_format($item['unit_price'], 2, ',', '.') ?></td>
                        <td class="text-right">€ <?= number_format($item['line_total'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right">Subtotaal</td>
                        <td class="text-right">€ <?= number_format($invoice['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                    <?php if ($invoice['discount'] > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right">Korting</td>
                        <td class="text-right">– € <?= number_format($invoice['discount'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3" class="text-right">BTW (<?= e($invoice['tax_rate']) ?>%)</td>
                        <td class="text-right">€ <?= number_format($invoice['tax_amount'], 2, ',', '.') ?></td>
                    </tr>
                    <tr class="cp-doc-total">
                        <td colspan="3" class="text-right"><strong>Totaal</strong></td>
                        <td class="text-right"><strong>€ <?= number_format($invoice['total'], 2, ',', '.') ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <?php if ($invoice['footer']): ?>
                <div class="cp-doc-footer"><?= nl2br(e($invoice['footer'])) ?></div>
            <?php endif; ?>

            <?php if ($settings->get('cp_company_iban') && $invoice['status'] !== 'paid'): ?>
                <div class="cp-doc-payment">
                    <strong>Betalen:</strong> Maak het bedrag over op <?= e($settings->get('cp_company_iban')) ?>
                    o.v.v. factuurnummer <strong><?= e($invoice['invoice_number']) ?></strong>.
                    <?php if ($settings->get('cp_company_name')): ?>
                        T.n.v. <?= e($settings->get('cp_company_name')) ?>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="cp-doc-actions">
                <a href="<?= BASE_URL . $slug ?>/facturen" class="cp-btn">← Terug naar facturen</a>
                <button onclick="window.print()" class="cp-btn cp-btn-outline">🖨 Afdrukken</button>
            </div>
        </div>
    </div>
</div>

<?php require THEMES_PATH . $activeTheme . '/footer.php'; ?>
