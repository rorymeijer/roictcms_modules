<?php
/**
 * Cache Manager Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Cache leegmaken
    if (isset($_POST['flush_cache'])) {
        $count = CacheManager::flush();
        flash('success', $count . ' cachebestand(en) verwijderd.');
        redirect(BASE_URL . '/modules/cache-manager/admin/');
    }

    // Instellingen opslaan
    if (isset($_POST['save_settings'])) {
        $enabled = isset($_POST['cache_enabled']) ? '1' : '0';
        $ttl     = max(60, (int) ($_POST['ttl'] ?? 3600));
        $exclude = trim($_POST['exclude'] ?? '/admin');

        $updates = [
            'cache_manager_enabled' => $enabled,
            'cache_manager_ttl'     => (string) $ttl,
            'cache_manager_exclude' => $exclude,
        ];

        foreach ($updates as $key => $value) {
            $ex = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "settings WHERE setting_key = ?", [$key]);
            if ($ex) {
                $db->query("UPDATE " . DB_PREFIX . "settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
            } else {
                $db->insert(DB_PREFIX . 'settings', ['setting_key' => $key, 'setting_value' => $value]);
            }
        }

        flash('success', 'Instellingen opgeslagen.');
        redirect(BASE_URL . '/modules/cache-manager/admin/');
    }
}

$enabled = $db->fetchOne("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'cache_manager_enabled'") ?? '0';
$ttl     = $db->fetchOne("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'cache_manager_ttl'") ?? '3600';
$exclude = $db->fetchOne("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'cache_manager_exclude'") ?? '/admin';

$stats   = CacheManager::getCacheSize();
$flashMsg = get_flash();
$pageTitle = 'Cache Manager';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-lightning me-2"></i>Cache Manager</h1>
</div>

<?php if ($flashMsg): ?>
    <div class="alert alert-<?= e($flashMsg['type']) ?> alert-dismissible fade show">
        <?= e($flashMsg['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Status & statistieken -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Statistieken</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-7">Status</dt>
                    <dd class="col-sm-5">
                        <span class="badge bg-<?= $enabled === '1' ? 'success' : 'secondary' ?>">
                            <?= $enabled === '1' ? 'Actief' : 'Inactief' ?>
                        </span>
                    </dd>
                    <dt class="col-sm-7">Gecachede pagina's</dt>
                    <dd class="col-sm-5"><?= (int) $stats['count'] ?></dd>
                    <dt class="col-sm-7">Totale grootte</dt>
                    <dd class="col-sm-5"><?= e(CacheManager::formatSize($stats['size'])) ?></dd>
                </dl>
            </div>
            <div class="card-footer bg-white">
                <form method="post">
                    <?= csrf_field() ?>
                    <button type="submit" name="flush_cache" value="1"
                            class="btn btn-danger btn-sm w-100"
                            onclick="return confirm('Alle cache verwijderen?')">
                        <i class="bi bi-trash me-1"></i>Cache leegmaken
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Instellingen -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Instellingen</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cache_enabled"
                                   name="cache_enabled" value="1" <?= $enabled === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="cache_enabled">Cache inschakelen</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cache levensduur (TTL in seconden)</label>
                        <input type="number" name="ttl" class="form-control" value="<?= e($ttl) ?>"
                               min="60" style="max-width:200px;">
                        <div class="form-text">Standaard: 3600 (1 uur). Minimaal 60 seconden.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Uitgesloten URL's</label>
                        <textarea name="exclude" class="form-control" rows="4"><?= e($exclude) ?></textarea>
                        <div class="form-text">Één URL-prefix per regel. Bijv. <code>/admin</code></div>
                    </div>

                    <button type="submit" name="save_settings" value="1" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Opslaan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
