<?php
/**
 * Customer Portal – frontend/quote-detail.php
 * Detailpagina van een offerte voor de klant.
 */

$db       = Database::getInstance();
$settings = Settings::getInstance();
$p        = DB_PREFIX;
$customer = cp_get_current_customer();
$slug     = trim($settings->get('cp_portal_slug') ?: 'klanten-portaal', '/');
$quoteId  = (int)($_GET['cp_quote_id'] ?? 0);

$quote = $db->fetch(
    "SELECT * FROM `{$p}cp_quotes` WHERE `id` = ? AND `customer_id` = ?",
    [$quoteId, $customer['id']]
);

if (!$quote) {
    http_response_code(404);
    $activeTheme = $settings->get('active_theme') ?: 'default';
    require THEMES_PATH . $activeTheme . '/header.php';
    echo '<div class="cp-portal"><p class="cp-empty">Offerte niet gevonden.</p></div>';
    require THEMES_PATH . $activeTheme . '/footer.php';
    return;
}

$items = $db->fetchAll(
    "SELECT * FROM `{$p}cp_quote_items` WHERE `quote_id` = ? ORDER BY `sort_order`",
    [$quoteId]
);

$activeTheme = $settings->get('active_theme') ?: 'default';
require THEMES_PATH . $activeTheme . '/header.php';
?>

<div class="cp-portal">
    <div class="cp-portal-header">
        <h1>Offerte <?= e($quote['quote_number']) ?></h1>
        <nav class="cp-nav">
            <a href="<?= BASE_URL . $slug ?>" class="cp-nav-link">⊞ Dashboard</a>
            <a href="<?= BASE_URL . $slug ?>/offertes" class="cp-nav-link active">📄 Offertes</a>
            <a href="<?= BASE_URL . $slug ?>/facturen" class="cp-nav-link">🧾 Facturen</a>
            <a href="<?= BASE_URL . $slug ?>/profiel" class="cp-nav-link">👤 Profiel</a>
            <a href="<?= BASE_URL ?>uitloggen" class="cp-nav-link cp-nav-logout">↩ Uitloggen</a>
        </nav>
    </div>

    <div class="cp-portal-body">
        <div class="cp-document">
            <!-- Document header -->
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
                        <tr><th>Offertenummer</th><td><?= e($quote['quote_number']) ?></td></tr>
                        <tr><th>Datum</th><td><?= date('d-m-Y', strtotime($quote['created_at'])) ?></td></tr>
                        <?php if ($quote['valid_until']): ?>
                        <tr><th>Geldig tot</th><td><?= date('d-m-Y', strtotime($quote['valid_until'])) ?></td></tr>
                        <?php endif; ?>
                        <tr><th>Status</th>
                            <td><span class="cp-badge cp-badge-<?= e($quote['status']) ?>">
                                <?= ['concept' => 'Concept', 'sent' => 'In behandeling', 'accepted' => 'Geaccepteerd', 'rejected' => 'Afgewezen', 'expired' => 'Verlopen'][$quote['status']] ?? $quote['status'] ?>
                            </span></td>
                        </tr>
                    </table>
                </div>
            </div>

            <h2 class="cp-doc-title"><?= e($quote['title']) ?></h2>

            <?php if ($quote['intro']): ?>
                <div class="cp-doc-intro"><?= nl2br(e($quote['intro'])) ?></div>
            <?php endif; ?>

            <!-- Regels -->
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
                        <td class="text-right">€ <?= number_format($quote['subtotal'], 2, ',', '.') ?></td>
                    </tr>
                    <?php if ($quote['discount'] > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right">Korting</td>
                        <td class="text-right">– € <?= number_format($quote['discount'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3" class="text-right">BTW (<?= e($quote['tax_rate']) ?>%)</td>
                        <td class="text-right">€ <?= number_format($quote['tax_amount'], 2, ',', '.') ?></td>
                    </tr>
                    <tr class="cp-doc-total">
                        <td colspan="3" class="text-right"><strong>Totaal</strong></td>
                        <td class="text-right"><strong>€ <?= number_format($quote['total'], 2, ',', '.') ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <?php if ($quote['footer']): ?>
                <div class="cp-doc-footer"><?= nl2br(e($quote['footer'])) ?></div>
            <?php endif; ?>

            <?php if ($settings->get('cp_company_iban')): ?>
                <div class="cp-doc-payment">
                    <strong>Bankgegevens:</strong> <?= e($settings->get('cp_company_iban')) ?>
                </div>
            <?php endif; ?>

            <div class="cp-doc-actions">
                <a href="<?= BASE_URL . $slug ?>/offertes" class="cp-btn">← Terug naar offertes</a>
                <button onclick="window.print()" class="cp-btn cp-btn-outline">🖨 Afdrukken</button>
            </div>
        </div>
    </div>
</div>

<?php require THEMES_PATH . $activeTheme . '/footer.php'; ?>
