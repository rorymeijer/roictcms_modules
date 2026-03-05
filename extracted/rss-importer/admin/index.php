<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add_feed') {
        $title        = trim($_POST['title'] ?? '');
        $feedUrl      = trim($_POST['feed_url'] ?? '');
        $maxItems     = (int)($_POST['max_items'] ?? 5);
        $cacheMinutes = (int)($_POST['cache_minutes'] ?? 60);

        if (empty($title)) {
            $errors[] = 'Titel is verplicht.';
        }
        if (empty($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Geldige feed URL is verplicht.';
        }
        $maxItems     = max(1, min(50, $maxItems));
        $cacheMinutes = max(1, $cacheMinutes);

        if (empty($errors)) {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "rss_feeds
                 (title, feed_url, max_items, cache_minutes, status, created_at)
                 VALUES (?, ?, ?, ?, 'active', NOW())", [$title, $feedUrl, $maxItems, $cacheMinutes]);
            flash('success', 'Feed toegevoegd.');
            redirect(BASE_URL . '/modules/rss-importer/admin/');
        }
    }

    if ($action === 'delete_feed') {
        $id = (int)($_POST['feed_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "rss_feeds WHERE id = ?", [$id]);
            // Remove cache
            Settings::delete('rss_cache_' . $id);
            flash('success', 'Feed verwijderd.');
        }
        redirect(BASE_URL . '/modules/rss-importer/admin/');
    }

    if ($action === 'refresh_feed') {
        $id = (int)($_POST['feed_id'] ?? 0);
        if ($id > 0) {
            Settings::delete('rss_cache_' . $id);
            flash('success', 'Cache gewist. Feed wordt opnieuw opgehaald bij volgende weergave.');
        }
        redirect(BASE_URL . '/modules/rss-importer/admin/');
    }
}

// Fetch data
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "rss_feeds ORDER BY id DESC");
$feeds = $stmt->fetchAll();

$pageTitle = 'RSS Importer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-rss-fill me-2"></i>RSS Importer</h1>
    </div>

    <?php foreach (get_flash() as $type => $msg): ?>
        <div class="alert alert-<?= e($type) ?> alert-dismissible fade show">
            <?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Add Feed Form -->
    <div class="card mb-4">
        <div class="card-header"><strong>Feed toevoegen</strong></div>
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_feed">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="title" class="form-label">Titel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?= e($_POST['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label for="feed_url" class="form-label">Feed URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="feed_url" name="feed_url"
                               value="<?= e($_POST['feed_url'] ?? '') ?>"
                               placeholder="https://example.com/rss.xml" required>
                    </div>
                    <div class="col-md-4">
                        <label for="max_items" class="form-label">Max. items</label>
                        <input type="number" class="form-control" id="max_items" name="max_items"
                               value="<?= e($_POST['max_items'] ?? 5) ?>" min="1" max="50">
                    </div>
                    <div class="col-md-4">
                        <label for="cache_minutes" class="form-label">Cache (minuten)</label>
                        <input type="number" class="form-control" id="cache_minutes" name="cache_minutes"
                               value="<?= e($_POST['cache_minutes'] ?? 60) ?>" min="1">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Feed toevoegen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Feeds List -->
    <div class="card">
        <div class="card-header"><strong>Feeds</strong></div>
        <div class="card-body p-0">
            <?php if (empty($feeds)): ?>
                <div class="p-4 text-muted">Nog geen feeds aangemaakt.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Titel</th>
                                <th>Feed URL</th>
                                <th>Max items</th>
                                <th>Cache</th>
                                <th>Cache status</th>
                                <th>Shortcode</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeds as $feed): ?>
                                <?php
                                $cached   = Settings::get('rss_cache_' . $feed['id']);
                                $cacheInfo = 'Geen cache';
                                if (!empty($cached)) {
                                    $dec = json_decode($cached, true);
                                    if (isset($dec['timestamp'])) {
                                        $age     = round((time() - $dec['timestamp']) / 60);
                                        $expires = (int)$feed['cache_minutes'] - $age;
                                        $cacheInfo = $expires > 0
                                            ? 'Verloopt over ' . $expires . ' min.'
                                            : 'Verlopen';
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= e($feed['id']) ?></td>
                                    <td><?= e($feed['title']) ?></td>
                                    <td>
                                        <a href="<?= e($feed['feed_url']) ?>" target="_blank" rel="noopener noreferrer"
                                           class="text-truncate d-inline-block" style="max-width:200px;"
                                           title="<?= e($feed['feed_url']) ?>">
                                            <?= e($feed['feed_url']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($feed['max_items']) ?></td>
                                    <td><?= e($feed['cache_minutes']) ?> min.</td>
                                    <td><small class="text-muted"><?= e($cacheInfo) ?></small></td>
                                    <td><code>[rss_feed id="<?= e($feed['id']) ?>"]</code></td>
                                    <td class="d-flex gap-2">
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="refresh_feed">
                                            <input type="hidden" name="feed_id" value="<?= e($feed['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                    title="Cache wissen">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('Feed verwijderen?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_feed">
                                            <input type="hidden" name="feed_id" value="<?= e($feed['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
