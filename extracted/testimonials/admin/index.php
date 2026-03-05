<?php
/**
 * Testimonials Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

// Verwerk POST-acties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name       = trim($_POST['name'] ?? '');
        $company    = trim($_POST['company'] ?? '');
        $quote      = trim($_POST['quote'] ?? '');
        $rating     = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $avatar_url = trim($_POST['avatar_url'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($name === '' || $quote === '') {
            set_flash('error', 'Naam en recensie zijn verplicht.');
        } else {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "testimonials (name, company, quote, rating, avatar_url, sort_order) VALUES (?, ?, ?, ?, ?, ?)", [$name, $company, $quote, $rating, $avatar_url, $sort_order]);
            set_flash('success', 'Testimonial succesvol toegevoegd.');
        }
        redirect(BASE_URL . '/modules/testimonials/admin/');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "testimonials WHERE id = ?", [$id]);
            set_flash('success', 'Testimonial verwijderd.');
        }
        redirect(BASE_URL . '/modules/testimonials/admin/');
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "testimonials SET status = IF(status='active','inactive','active') WHERE id = ?", [$id]);
            set_flash('success', 'Status bijgewerkt.');
        }
        redirect(BASE_URL . '/modules/testimonials/admin/');
    }
}

// Haal testimonials op
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "testimonials ORDER BY sort_order ASC, id ASC");
$testimonials = $stmt->fetchAll();

$pageTitle = 'Testimonials beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-chat-quote me-2"></i>Testimonials</h1>
    </div>

    <?php flash_messages(); ?>

    <div class="row">
        <!-- Lijst -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Testimonials (<?= count($testimonials) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($testimonials)): ?>
                        <p class="text-muted p-3 mb-0">Nog geen testimonials toegevoegd.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Naam</th>
                                        <th>Bedrijf</th>
                                        <th>Beoordeling</th>
                                        <th>Status</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($testimonials as $t): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($t['name']) ?></strong>
                                                <br><small class="text-muted"><?= e(mb_substr($t['quote'], 0, 60)) ?>...</small>
                                            </td>
                                            <td><?= e($t['company']) ?></td>
                                            <td>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?= $i <= $t['rating'] ? '-fill' : '' ?> text-warning small"></i>
                                                <?php endfor; ?>
                                            </td>
                                            <td>
                                                <?php if ($t['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Actief</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactief</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Status wijzigen">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Testimonial verwijderen?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
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

        <!-- Formulier -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Testimonial toevoegen</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Naam <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bedrijf</label>
                            <input type="text" name="company" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Recensie <span class="text-danger">*</span></label>
                            <textarea name="quote" class="form-control" rows="4" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Beoordeling (1-5)</label>
                            <select name="rating" class="form-select">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?= $i ?>"><?= str_repeat('★', $i) ?> (<?= $i ?>)</option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Avatar URL</label>
                            <input type="text" name="avatar_url" class="form-control" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Volgorde</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i>Toevoegen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
