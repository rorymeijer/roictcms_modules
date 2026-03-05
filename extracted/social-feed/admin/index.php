<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add_post') {
        $platform  = trim($_POST['platform'] ?? 'instagram');
        $postText  = trim($_POST['post_text'] ?? '');
        $postUrl   = trim($_POST['post_url'] ?? '');
        $imageUrl  = trim($_POST['image_url'] ?? '');
        $postedAt  = trim($_POST['posted_at'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        $allowed = ['instagram', 'twitter', 'facebook', 'linkedin'];
        if (!in_array($platform, $allowed, true)) {
            $errors[] = 'Ongeldig platform.';
        }
        if (empty($postText)) {
            $errors[] = 'Post tekst is verplicht.';
        }

        if (empty($errors)) {
            $postedAtValue = !empty($postedAt) ? $postedAt : null;
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "social_feed_posts
                 (platform, post_text, post_url, image_url, posted_at, sort_order, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())", [$platform, $postText, $postUrl ?: null, $imageUrl ?: null, $postedAtValue, $sortOrder]);
            flash('success', 'Post toegevoegd.');
            redirect(BASE_URL . '/modules/social-feed/admin/');
        }
    }

    if ($action === 'delete_post') {
        $id = (int)($_POST['post_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "social_feed_posts WHERE id = ?", [$id]);
            flash('success', 'Post verwijderd.');
        }
        redirect(BASE_URL . '/modules/social-feed/admin/');
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['post_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "social_feed_posts
                 SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?", [$id]);
            flash('success', 'Status gewijzigd.');
        }
        redirect(BASE_URL . '/modules/social-feed/admin/');
    }
}

// Fetch data
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "social_feed_posts ORDER BY sort_order ASC, posted_at DESC");
$posts = $stmt->fetchAll();

$platformIcons = [
    'instagram' => 'instagram',
    'twitter'   => 'twitter-x',
    'facebook'  => 'facebook',
    'linkedin'  => 'linkedin',
];

$pageTitle = 'Social Feed';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-rss me-2"></i>Social Feed</h1>
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

    <!-- Add Post Form -->
    <div class="card mb-4">
        <div class="card-header"><strong>Post toevoegen</strong></div>
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_post">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="platform" class="form-label">Platform <span class="text-danger">*</span></label>
                        <select class="form-select" id="platform" name="platform">
                            <?php foreach (['instagram','twitter','facebook','linkedin'] as $p): ?>
                                <option value="<?= e($p) ?>"
                                    <?= (($_POST['platform'] ?? '') === $p) ? 'selected' : '' ?>>
                                    <?= ucfirst(e($p)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label for="post_text" class="form-label">Post tekst <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="post_text" name="post_text" rows="3"
                                  required><?= e($_POST['post_text'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="post_url" class="form-label">Post URL</label>
                        <input type="url" class="form-control" id="post_url" name="post_url"
                               value="<?= e($_POST['post_url'] ?? '') ?>" placeholder="https://...">
                    </div>
                    <div class="col-md-6">
                        <label for="image_url" class="form-label">Afbeelding URL</label>
                        <input type="url" class="form-control" id="image_url" name="image_url"
                               value="<?= e($_POST['image_url'] ?? '') ?>" placeholder="https://...">
                    </div>
                    <div class="col-md-6">
                        <label for="posted_at" class="form-label">Datum/tijd gepost</label>
                        <input type="datetime-local" class="form-control" id="posted_at" name="posted_at"
                               value="<?= e($_POST['posted_at'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="sort_order" class="form-label">Sorteervolgorde</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order"
                               value="<?= e($_POST['sort_order'] ?? 0) ?>">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Post toevoegen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Shortcode info -->
    <div class="alert alert-info">
        <strong>Shortcodes:</strong>
        <code>[social_feed]</code> — alle posts &nbsp;|&nbsp;
        <code>[social_feed platform="instagram"]</code> — gefilterd op platform
    </div>

    <!-- Posts List -->
    <div class="card">
        <div class="card-header"><strong>Posts</strong></div>
        <div class="card-body p-0">
            <?php if (empty($posts)): ?>
                <div class="p-4 text-muted">Nog geen posts aangemaakt.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Platform</th>
                                <th>Tekst</th>
                                <th>Gepost op</th>
                                <th>Status</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-<?= e($platformIcons[$post['platform']] ?? 'share') ?>"></i>
                                        <?= ucfirst(e($post['platform'])) ?>
                                    </td>
                                    <td><?= e(mb_strimwidth($post['post_text'], 0, 80, '...')) ?></td>
                                    <td><?= $post['posted_at'] ? e(date('d-m-Y H:i', strtotime($post['posted_at']))) : '—' ?></td>
                                    <td>
                                        <?php if ($post['status'] === 'active'): ?>
                                            <span class="badge bg-success">Actief</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactief</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-flex gap-2">
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="post_id" value="<?= e($post['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Status wijzigen">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('Post verwijderen?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?= e($post['id']) ?>">
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
