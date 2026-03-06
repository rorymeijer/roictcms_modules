<?php
/**
 * Customer Portal – frontend/quotes.php
 * Offerteoverzicht voor de ingelogde klant.
 */

$db       = Database::getInstance();
$settings = Settings::getInstance();
$p        = DB_PREFIX;
$customer = cp_get_current_customer();
$slug     = trim($settings->get('cp_portal_slug') ?: 'klanten-portaal', '/');

$quotes = $db->fetchAll(
    "SELECT * FROM `{$p}cp_quotes` WHERE `customer_id` = ? ORDER BY `created_at` DESC",
    [$customer['id']]
);

$activeTheme = $settings->get('active_theme') ?: 'default';
require THEMES_PATH . $activeTheme . '/header.php';
?>

<div class="cp-portal">
    <div class="cp-portal-header">
        <h1>Offertes</h1>
        <nav class="cp-nav">
            <a href="<?= BASE_URL . $slug ?>" class="cp-nav-link">⊞ Dashboard</a>
            <a href="<?= BASE_URL . $slug ?>/offertes" class="cp-nav-link active">📄 Offertes</a>
            <a href="<?= BASE_URL . $slug ?>/facturen" class="cp-nav-link">🧾 Facturen</a>
            <a href="<?= BASE_URL . $slug ?>/profiel" class="cp-nav-link">👤 Profiel</a>
            <a href="<?= BASE_URL ?>uitloggen" class="cp-nav-link cp-nav-logout">↩ Uitloggen</a>
        </nav>
    </div>

    <div class="cp-portal-body">
        <div class="cp-card">
            <?php if (empty($quotes)): ?>
                <p class="cp-empty">Er zijn nog geen offertes voor uw account.</p>
            <?php else: ?>
                <table class="cp-table">
                    <thead>
                        <tr>
                            <th>Nummer</th>
                            <th>Omschrijving</th>
                            <th>Datum</th>
                            <th>Geldig tot</th>
                            <th>Totaal</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotes as $q): ?>
                        <tr>
                            <td><strong><?= e($q['quote_number']) ?></strong></td>
                            <td><?= e($q['title']) ?></td>
                            <td><?= date('d-m-Y', strtotime($q['created_at'])) ?></td>
                            <td><?= $q['valid_until'] ? date('d-m-Y', strtotime($q['valid_until'])) : '–' ?></td>
                            <td><strong>€ <?= number_format($q['total'], 2, ',', '.') ?></strong></td>
                            <td>
                                <span class="cp-badge cp-badge-<?= e($q['status']) ?>">
                                    <?= ['concept' => 'Concept', 'sent' => 'In behandeling', 'accepted' => 'Geaccepteerd', 'rejected' => 'Afgewezen', 'expired' => 'Verlopen'][$q['status']] ?? $q['status'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= BASE_URL . $slug ?>/offertes/<?= $q['id'] ?>" class="cp-btn cp-btn-sm">Bekijken</a>
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
