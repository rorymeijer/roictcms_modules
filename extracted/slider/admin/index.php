<?php
/**
 * Slider Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

// Verwerk POST-acties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title      = trim($_POST['title'] ?? '');
        $subtitle   = trim($_POST['subtitle'] ?? '');
        $btn_text   = trim($_POST['button_text'] ?? '');
        $btn_url    = trim($_POST['button_url'] ?? '');
        $image_path = trim($_POST['image_path'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($title === '' || $image_path === '') {
            set_flash('error', 'Titel en afbeeldingspad zijn verplicht.');
        } else {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "slider_items (title, subtitle, button_text, button_url, image_path, sort_order) VALUES (?, ?, ?, ?, ?, ?)", [$title, $subtitle, $btn_text, $btn_url, $image_path, $sort_order]);
            set_flash('success', 'Slide succesvol toegevoegd.');
        }
        redirect(BASE_URL . '/modules/slider/admin/');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "slider_items WHERE id = ?", [$id]);
            set_flash('success', 'Slide verwijderd.');
        }
        redirect(BASE_URL . '/modules/slider/admin/');
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "slider_items SET status = IF(status='active','inactive','active') WHERE id = ?", [$id]);
            set_flash('success', 'Status bijgewerkt.');
        }
        redirect(BASE_URL . '/modules/slider/admin/');
    }
}

// Haal slides op
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "slider_items ORDER BY sort_order ASC, id ASC");
$slides = $stmt->fetchAll();

$pageTitle = 'Slider beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-images me-2"></i>Slider / Carousel</h1>
    </div>

    <?php flash_messages(); ?>

    <div class="row">
        <!-- Lijst van slides -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Slides (<?= count($slides) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($slides)): ?>
                        <p class="text-muted p-3 mb-0">Nog geen slides aangemaakt.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Volgorde</th>
                                        <th>Afbeelding</th>
                                        <th>Titel</th>
                                        <th>Status</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($slides as $slide): ?>
                                        <tr>
                                            <td><?= e($slide['sort_order']) ?></td>
                                            <td>
                                                <?php if (!empty($slide['image_path'])): ?>
                                                    <img src="<?= e($slide['image_path']) ?>" alt="" style="height:40px;width:70px;object-fit:cover;" class="rounded">
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= e($slide['title']) ?></strong>
                                                <?php if (!empty($slide['subtitle'])): ?>
                                                    <br><small class="text-muted"><?= e($slide['subtitle']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($slide['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Actief</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactief</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= (int)$slide['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Status wijzigen">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Slide verwijderen?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$slide['id'] ?>">
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

        <!-- Formulier nieuwe slide -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Nieuwe slide toevoegen</h5>
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
                            <label class="form-label fw-semibold">Ondertitel</label>
                            <input type="text" name="subtitle" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Afbeeldingspad <span class="text-danger">*</span></label>
                            <input type="text" name="image_path" class="form-control" placeholder="/uploads/slider/foto.jpg" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Knoptekst</label>
                            <input type="text" name="button_text" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Knop URL</label>
                            <input type="text" name="button_url" class="form-control" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Volgorde</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i>Slide toevoegen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
