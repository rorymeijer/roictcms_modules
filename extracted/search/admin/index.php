<?php
/**
 * Zoekmodule - Admin Instellingen
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $maxResults  = max(1, (int) ($_POST['max_results'] ?? 10));
    $searchPages = isset($_POST['search_pages']) ? '1' : '0';
    $searchNews  = isset($_POST['search_news']) ? '1' : '0';

    $settings = [
        'search_max_results' => (string) $maxResults,
        'search_pages'       => $searchPages,
        'search_news'        => $searchNews,
    ];

    foreach ($settings as $key => $value) {
        $exists = $db->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "settings WHERE setting_key = ?",
            [$key]
        );
        if ($exists) {
            $db->query(
                "UPDATE " . DB_PREFIX . "settings SET setting_value = ? WHERE setting_key = ?",
                [$value, $key]
            );
        } else {
            $db->insert(DB_PREFIX . 'settings', [
                'setting_key'   => $key,
                'setting_value' => $value,
            ]);
        }
    }

    flash('success', 'Instellingen opgeslagen.');
    redirect(BASE_URL . '/modules/search/admin/');
}

$maxResults  = $db->fetchOne("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'search_max_results'") ?: '10';
$searchPages = $db->fetchOne("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'search_pages'") ?? '1';
$searchNews  = $db->fetchOne("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'search_news'") ?? '1';

$flashMsg  = get_flash();
$pageTitle = 'Zoekmodule Instellingen';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-search me-2"></i>Zoekmodule Instellingen</h1>
</div>

<?php if ($flashMsg): ?>
    <div class="alert alert-<?= e($flashMsg['type']) ?> alert-dismissible fade show">
        <?= e($flashMsg['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Instellingen</div>
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="max_results" class="form-label">Maximum resultaten</label>
                <input type="number" id="max_results" name="max_results" class="form-control"
                       value="<?= e($maxResults) ?>" min="1" max="100" style="max-width:150px;">
                <div class="form-text">Maximaal aantal zoekresultaten dat getoond wordt.</div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="search_pages" name="search_pages"
                           value="1" <?= $searchPages === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="search_pages">
                        Zoek in pagina's
                    </label>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="search_news" name="search_news"
                           value="1" <?= $searchNews === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="search_news">
                        Zoek in nieuwsberichten
                    </label>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Opslaan
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold">Shortcode gebruik</div>
    <div class="card-body">
        <p>Gebruik de volgende shortcodes in uw pagina's:</p>
        <ul>
            <li><code>[search_form]</code> &mdash; Toont het zoekformulier</li>
            <li><code>[search_results]</code> &mdash; Toont de zoekresultaten (op basis van de <code>?q=</code> parameter)</li>
        </ul>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
