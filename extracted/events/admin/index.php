<?php
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '') ?: null;
        $image_url = trim($_POST['image_url'] ?? '');
        $registration_url = trim($_POST['registration_url'] ?? '');

        if (empty($title)) {
            $errors[] = 'Titel is verplicht.';
        }
        if (empty($start_date)) {
            $errors[] = 'Startdatum is verplicht.';
        }

        if (empty($errors)) {
            $stmt = $db->query("
                INSERT INTO " . DB_PREFIX . "events
                    (title, description, location, start_date, end_date, image_url, registration_url)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [$title, $description, $location, $start_date, $end_date, $image_url, $registration_url]);
            flash('success', 'Evenement succesvol toegevoegd.');
            redirect(BASE_URL . '/modules/events/admin/');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "events WHERE id = ?", [$id]);
            flash('success', 'Evenement verwijderd.');
            redirect(BASE_URL . '/modules/events/admin/');
        }
    }
}

// Fetch all events
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "events ORDER BY start_date ASC");
$events = $stmt->fetchAll();

$pageTitle = 'Evenementen beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-calendar-event me-2"></i>Evenementen</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="bi bi-plus-lg me-1"></i>Nieuw evenement
        </button>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?= flash_display() ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Titel</th>
                        <th>Locatie</th>
                        <th>Startdatum</th>
                        <th>Einddatum</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Geen evenementen gevonden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?= e($event['title']) ?></td>
                                <td><?= e($event['location']) ?></td>
                                <td><?= e($event['start_date']) ?></td>
                                <td><?= $event['end_date'] ? e($event['end_date']) : '-' ?></td>
                                <td>
                                    <?php if ($event['status'] === 'active'): ?>
                                        <span class="badge bg-success">Actief</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Geannuleerd</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Evenement verwijderen?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Nieuw evenement toevoegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Titel <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschrijving</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Locatie</label>
                        <input type="text" name="location" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Startdatum & tijd <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Einddatum & tijd</label>
                            <input type="datetime-local" name="end_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Afbeelding URL</label>
                        <input type="url" name="image_url" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Aanmeldings URL</label>
                        <input type="url" name="registration_url" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Evenement opslaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
