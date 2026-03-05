<?php
/**
 * Video Gallery Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

// Verwerk POST-acties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title         = trim($_POST['title'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $video_url     = trim($_POST['video_url'] ?? '');
        $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
        $group         = trim($_POST['gallery_group'] ?? 'default');
        $sort_order    = (int)($_POST['sort_order'] ?? 0);

        if ($title === '' || $video_url === '') {
            set_flash('error', 'Titel en video URL zijn verplicht.');
        } else {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "video_gallery_items (title, description, video_url, thumbnail_url, gallery_group, sort_order) VALUES (?, ?, ?, ?, ?, ?)", [$title, $description, $video_url, $thumbnail_url, $group, $sort_order]);
            set_flash('success', 'Video succesvol toegevoegd.');
        }
        redirect(BASE_URL . '/modules/video-gallery/admin/');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "video_gallery_items WHERE id = ?", [$id]);
            set_flash('success', 'Video verwijderd.');
        }
        redirect(BASE_URL . '/modules/video-gallery/admin/');
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "video_gallery_items SET status = IF(status='active','inactive','active') WHERE id = ?", [$id]);
            set_flash('success', 'Status bijgewerkt.');
        }
        redirect(BASE_URL . '/modules/video-gallery/admin/');
    }
}

// Haal video's op
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "video_gallery_items ORDER BY gallery_group ASC, sort_order ASC, id ASC");
$videos = $stmt->fetchAll();

$pageTitle = 'Video Gallery beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-play-btn me-2"></i>Video Gallery</h1>
    </div>

    <?php flash_messages(); ?>

    <div class="row">
        <!-- Lijst van video's -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Video's (<?= count($videos) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($videos)): ?>
                        <p class="text-muted p-3 mb-0">Nog geen video's toegevoegd.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Titel</th>
                                        <th>Groep</th>
                                        <th>Volgorde</th>
                                        <th>Status</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($videos as $video): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($video['title']) ?></strong>
                                                <?php if (!empty($video['description'])): ?>
                                                    <br><small class="text-muted"><?= e(mb_substr($video['description'], 0, 60)) ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-info text-dark"><?= e($video['gallery_group']) ?></span></td>
                                            <td><?= e($video['sort_order']) ?></td>
                                            <td>
                                                <?php if ($video['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Actief</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactief</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= (int)$video['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Status wijzigen">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Video verwijderen?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$video['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Verwijderen">
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

        <!-- Formulier nieuwe video -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Video toevoegen</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Titel <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Omschrijving</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Video URL <span class="text-danger">*</span></label>
                            <input type="url" name="video_url" class="form-control" placeholder="https://youtube.com/watch?v=..." required>
                            <div class="form-text">YouTube of Vimeo URL</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Thumbnail URL</label>
                            <input type="text" name="thumbnail_url" class="form-control" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Galerij groep</label>
                            <input type="text" name="gallery_group" class="form-control" value="default">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Volgorde</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i>Video toevoegen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
