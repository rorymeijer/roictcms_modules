<?php
/**
 * Customer Portal – frontend/invoices.php
 * Factuuroverzicht voor de ingelogde klant.
 */

$db       = Database::getInstance();
$settings = Settings::getInstance();
$p        = DB_PREFIX;
$customer = cp_get_current_customer();
$slug     = trim($settings->get('cp_portal_slug') ?: 'klanten-portaal', '/');

$invoices = $db->fetchAll(
    "SELECT * FROM `{$p}cp_invoices` WHERE `customer_id` = ? ORDER BY `created_at` DESC",
    [$customer['id']]
);

$activeTheme = $settings->get('active_theme') ?: 'default';
require THEMES_PATH . $activeTheme . '/header.php';
?>

<div class="cp-portal">
    <div class="cp-portal-header">
        <h1>Facturen</h1>
        <nav class="cp-nav">
            <a href="<?= BASE_URL . $slug ?>" class="cp-nav-link">⊞ Dashboard</a>
            <a href="<?= BASE_URL . $slug ?>/offertes" class="cp-nav-link">📄 Offertes</a>
            <a href="<?= BASE_URL . $slug ?>/facturen" class="cp-nav-link active">🧾 Facturen</a>
            <a href="<?= BASE_URL . $slug ?>/profiel" class="cp-nav-link">👤 Profiel</a>
            <a href="<?= BASE_URL ?>uitloggen" class="cp-nav-link cp-nav-logout">↩ Uitloggen</a>
        </nav>
    </div>

    <div class="cp-portal-body">
        <div class="cp-card">
            <?php if (empty($invoices)): ?>
                <p class="cp-empty">Er zijn nog geen facturen voor uw account.</p>
            <?php else: ?>
                <table class="cp-table">
                    <thead>
                        <tr>
                            <th>Nummer</th>
                            <th>Omschrijving</th>
                            <th>Factuurdatum</th>
                            <th>Vervaldatum</th>
                            <th>Totaal</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv):
                            $overdue = $inv['status'] === 'sent' && $inv['due_date'] && $inv['due_date'] < date('Y-m-d');
                        ?>
                        <tr <?= $overdue ? 'class="cp-row-warning"' : '' ?>>
                            <td><strong><?= e($inv['invoice_number']) ?></strong></td>
                            <td><?= e($inv['title']) ?></td>
                            <td><?= $inv['invoice_date'] ? date('d-m-Y', strtotime($inv['invoice_date'])) : '–' ?></td>
                            <td><?= $inv['due_date'] ? date('d-m-Y', strtotime($inv['due_date'])) : '–' ?></td>
                            <td><strong>€ <?= number_format($inv['total'], 2, ',', '.') ?></strong></td>
                            <td>
                                <span class="cp-badge cp-badge-<?= e($overdue ? 'overdue' : $inv['status']) ?>">
                                    <?= ['concept' => 'Concept', 'sent' => 'Openstaand', 'paid' => 'Betaald', 'overdue' => 'Verlopen', 'cancelled' => 'Geannuleerd'][$overdue ? 'overdue' : $inv['status']] ?? $inv['status'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= BASE_URL . $slug ?>/facturen/<?= $inv['id'] ?>" class="cp-btn cp-btn-sm">Bekijken</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require THEMES_PATH . $activeTheme . '/footer.php'; ?>
