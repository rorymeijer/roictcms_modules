<?php
/**
 * Customer Portal – frontend/portal.php
 * Hoofdpagina klantenpaneel.
 * Wordt geladen via init.php na login-controle.
 */

$db       = Database::getInstance();
$settings = Settings::getInstance();
$p        = DB_PREFIX;
$customer = cp_get_current_customer();
$user     = FrontendLoginModule::currentUser();
$slug     = trim($settings->get('cp_portal_slug') ?: 'klanten-portaal', '/');

// Recente offertes
$recentQuotes = $db->fetchAll(
    "SELECT * FROM `{$p}cp_quotes` WHERE `customer_id` = ? ORDER BY `created_at` DESC LIMIT 5",
    [$customer['id']]
);

// Recente facturen
$recentInvoices = $db->fetchAll(
    "SELECT * FROM `{$p}cp_invoices` WHERE `customer_id` = ? ORDER BY `created_at` DESC LIMIT 5",
    [$customer['id']]
);

// Openstaande facturen
$openAmount = $db->fetch(
    "SELECT COALESCE(SUM(total),0) total, COUNT(*) cnt FROM `{$p}cp_invoices` WHERE `customer_id` = ? AND `status` IN ('sent','overdue')",
    [$customer['id']]
);

$activeTheme = $settings->get('active_theme') ?: 'default';
require THEMES_PATH . $activeTheme . '/header.php';
?>

<div class="cp-portal">
    <div class="cp-portal-header">
        <h1>Welkom, <?= e($customer['contact_name']) ?></h1>
        <?php if ($customer['company_name']): ?>
            <p class="cp-company"><?= e($customer['company_name']) ?></p>
        <?php endif; ?>
        <nav class="cp-nav">
            <a href="<?= BASE_URL . $slug ?>" class="cp-nav-link active">
                <span class="cp-nav-icon">⊞</span> Dashboard
            </a>
            <a href="<?= BASE_URL . $slug ?>/offertes" class="cp-nav-link">
                <span class="cp-nav-icon">📄</span> Offertes
            </a>
            <a href="<?= BASE_URL . $slug ?>/facturen" class="cp-nav-link">
                <span class="cp-nav-icon">🧾</span> Facturen
            </a>
            <a href="<?= BASE_URL . $slug ?>/profiel" class="cp-nav-link">
                <span class="cp-nav-icon">👤</span> Profiel
            </a>
            <a href="<?= BASE_URL ?>uitloggen" class="cp-nav-link cp-nav-logout">
                <span class="cp-nav-icon">↩</span> Uitloggen
            </a>
        </nav>
    </div>

    <div class="cp-portal-body">
        <!-- Statistieken -->
        <div class="cp-stats">
            <div class="cp-stat-card">
                <div class="cp-stat-value"><?= count($recentQuotes) ?></div>
                <div class="cp-stat-label">Recente offertes</div>
            </div>
            <div class="cp-stat-card <?= $openAmount['cnt'] > 0 ? 'cp-stat-warning' : '' ?>">
                <div class="cp-stat-value">€ <?= number_format($openAmount['total'], 2, ',', '.') ?></div>
                <div class="cp-stat-label">Openstaand bedrag</div>
            </div>
            <div class="cp-stat-card">
                <div class="cp-stat-value"><?= (int)$openAmount['cnt'] ?></div>
                <div class="cp-stat-label">Open facturen</div>
            </div>
        </div>

        <div class="cp-grid">
            <!-- Recente offertes -->
            <div class="cp-card">
                <div class="cp-card-header">
                    <h2>Recente offertes</h2>
                    <a href="<?= BASE_URL . $slug ?>/offertes" class="cp-link">Alle offertes →</a>
                </div>
                <?php if (empty($recentQuotes)): ?>
                    <p class="cp-empty">Nog geen offertes.</p>
                <?php else: ?>
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>Nummer</th>
                                <th>Omschrijving</th>
                                <th>Totaal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentQuotes as $q): ?>
                            <tr>
                                <td><a href="<?= BASE_URL . $slug ?>/offertes/<?= $q['id'] ?>"><?= e($q['quote_number']) ?></a></td>
                                <td><?= e($q['title']) ?></td>
                                <td>€ <?= number_format($q['total'], 2, ',', '.') ?></td>
                                <td><span class="cp-badge cp-badge-<?= e($q['status']) ?>"><?= cp_quote_status_label($q['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Recente facturen -->
            <div class="cp-card">
                <div class="cp-card-header">
                    <h2>Recente facturen</h2>
                    <a href="<?= BASE_URL . $slug ?>/facturen" class="cp-link">Alle facturen →</a>
                </div>
                <?php if (empty($recentInvoices)): ?>
                    <p class="cp-empty">Nog geen facturen.</p>
                <?php else: ?>
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>Nummer</th>
                                <th>Omschrijving</th>
                                <th>Totaal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td><a href="<?= BASE_URL . $slug ?>/facturen/<?= $inv['id'] ?>"><?= e($inv['invoice_number']) ?></a></td>
                                <td><?= e($inv['title']) ?></td>
                                <td>€ <?= number_format($inv['total'], 2, ',', '.') ?></td>
                                <td><span class="cp-badge cp-badge-<?= e($inv['status']) ?>"><?= cp_invoice_status_label($inv['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require THEMES_PATH . $activeTheme . '/footer.php';

// Status label helpers (alleen beschikbaar in frontend context)
function cp_quote_status_label(string $s): string {
    return ['concept' => 'Concept', 'sent' => 'Verzonden', 'accepted' => 'Geaccepteerd', 'rejected' => 'Afgewezen', 'expired' => 'Verlopen'][$s] ?? $s;
}
function cp_invoice_status_label(string $s): string {
    return ['concept' => 'Concept', 'sent' => 'Openstaand', 'paid' => 'Betaald', 'overdue' => 'Verlopen', 'cancelled' => 'Geannuleerd'][$s] ?? $s;
}
