<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add_popup') {
        $title       = trim($_POST['title'] ?? '');
        $content     = trim($_POST['content'] ?? '');
        $triggerType = trim($_POST['trigger_type'] ?? 'time');
        $triggerDelay = (int)($_POST['trigger_delay'] ?? 5);
        $showOnce    = isset($_POST['show_once']) ? 1 : 0;
        $cookieDays  = (int)($_POST['cookie_days'] ?? 7);

        if (empty($title)) {
            $errors[] = 'Titel is verplicht.';
        }
        if (empty($content)) {
            $errors[] = 'Inhoud is verplicht.';
        }
        if (!in_array($triggerType, ['time', 'exit_intent'], true)) {
            $errors[] = 'Ongeldig trigger type.';
        }
        $triggerDelay = max(0, $triggerDelay);
        $cookieDays   = max(1, $cookieDays);

        if (empty($errors)) {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "popups
                 (title, content, trigger_type, trigger_delay, show_once, cookie_days, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())", [$title, $content, $triggerType, $triggerDelay, $showOnce, $cookieDays]);
            flash('success', 'Popup toegevoegd.');
            redirect(BASE_URL . '/modules/popup-manager/admin/');
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['popup_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "popups
                 SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?", [$id]);
            flash('success', 'Status gewijzigd.');
        }
        redirect(BASE_URL . '/modules/popup-manager/admin/');
    }

    if ($action === 'delete_popup') {
        $id = (int)($_POST['popup_id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "popups WHERE id = ?", [$id]);
            flash('success', 'Popup verwijderd.');
        }
        redirect(BASE_URL . '/modules/popup-manager/admin/');
    }
}

// Fetch data
$stmt   = $db->query("SELECT * FROM " . DB_PREFIX . "popups ORDER BY id DESC");
$popups = $stmt->fetchAll();

$pageTitle = 'Popup Manager';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-window-stack me-2"></i>Popup Manager</h1>
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

    <!-- Add Popup Form -->
    <div class="card mb-4">
        <div class="card-header"><strong>Popup toevoegen</strong></div>
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_popup">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="title" class="form-label">Titel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?= e($_POST['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="trigger_type" class="form-label">Trigger type</label>
                        <select class="form-select" id="trigger_type" name="trigger_type"
                                onchange="document.getElementById('delay-group').style.display = this.value === 'time' ? '' : 'none'">
                            <option value="time" <?= (($_POST['trigger_type'] ?? 'time') === 'time') ? 'selected' : '' ?>>
                                Na tijd
                            </option>
                            <option value="exit_intent" <?= (($_POST['trigger_type'] ?? '') === 'exit_intent') ? 'selected' : '' ?>>
                                Exit intent
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3" id="delay-group">
                        <label for="trigger_delay" class="form-label">Vertraging (seconden)</label>
                        <input type="number" class="form-control" id="trigger_delay" name="trigger_delay"
                               value="<?= e($_POST['trigger_delay'] ?? 5) ?>" min="0">
                    </div>
                    <div class="col-12">
                        <label for="content" class="form-label">Inhoud <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" name="content" rows="5"
                                  required><?= e($_POST['content'] ?? '') ?></textarea>
                        <div class="form-text">HTML is toegestaan.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="cookie_days" class="form-label">Cookie geldigheid (dagen)</label>
                        <input type="number" class="form-control" id="cookie_days" name="cookie_days"
                               value="<?= e($_POST['cookie_days'] ?? 7) ?>" min="1">
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="show_once" name="show_once"
                                   value="1" <?= isset($_POST['show_once']) || !isset($_POST['action']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_once">
                                Eenmalig tonen (via cookie)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Popup toevoegen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Popups List -->
    <div class="card">
        <div class="card-header"><strong>Popups</strong></div>
        <div class="card-body p-0">
            <?php if (empty($popups)): ?>
                <div class="p-4 text-muted">Nog geen popups aangemaakt.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Titel</th>
                                <th>Trigger</th>
                                <th>Vertraging</th>
                                <th>Eenmalig</th>
                                <th>Cookie (dagen)</th>
                                <th>Status</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popups as $popup): ?>
                                <tr>
                                    <td><?= e($popup['title']) ?></td>
                                    <td>
                                        <?php if ($popup['trigger_type'] === 'time'): ?>
                                            <span class="badge bg-info text-dark">
                                                <i class="bi bi-clock"></i> Na tijd
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-box-arrow-right"></i> Exit intent
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($popup['trigger_type'] === 'time'): ?>
                                            <?= e($popup['trigger_delay']) ?>s
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $popup['show_once'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?>
                                    </td>
                                    <td><?= e($popup['cookie_days']) ?></td>
                                    <td>
                                        <?php if ($popup['status'] === 'active'): ?>
                                            <span class="badge bg-success">Actief</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactief</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-flex gap-2">
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="popup_id" value="<?= e($popup['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                    title="Status wijzigen">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('Popup verwijderen?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_popup">
                                            <input type="hidden" name="popup_id" value="<?= e($popup['id']) ?>">
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

<script>
// Hide delay field for exit_intent on page load
document.addEventListener('DOMContentLoaded', function () {
    var triggerSelect = document.getElementById('trigger_type');
    if (triggerSelect) {
        var delayGroup = document.getElementById('delay-group');
        if (triggerSelect.value === 'exit_intent') {
            delayGroup.style.display = 'none';
        }
    }
});
</script>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
