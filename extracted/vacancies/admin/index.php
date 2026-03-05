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
        $department = trim($_POST['department'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $employment_type = trim($_POST['employment_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $requirements = trim($_POST['requirements'] ?? '');
        $salary_range = trim($_POST['salary_range'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');

        if (empty($title)) {
            $errors[] = 'Titel is verplicht.';
        }

        if (empty($errors)) {
            $stmt = $db->query("
                INSERT INTO " . DB_PREFIX . "vacancies
                    (title, department, location, employment_type, description, requirements, salary_range, contact_email)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [$title, $department, $location, $employment_type, $description, $requirements, $salary_range, $contact_email]);
            flash('success', 'Vacature succesvol toegevoegd.');
            redirect(BASE_URL . '/modules/vacancies/admin/');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "vacancy_applications WHERE vacancy_id = ?", [$id]);
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "vacancies WHERE id = ?", [$id]);
            flash('success', 'Vacature verwijderd.');
            redirect(BASE_URL . '/modules/vacancies/admin/');
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $current = $_POST['current_status'] ?? 'open';
        $newStatus = ($current === 'open') ? 'closed' : 'open';
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "vacancies SET status = ? WHERE id = ?", [$newStatus, $id]);
            flash('success', 'Status bijgewerkt.');
            redirect(BASE_URL . '/modules/vacancies/admin/');
        }
    }
}

// View applications for a vacancy
$viewApplicationsId = (int)($_GET['applications'] ?? 0);
$applications = [];
$viewVacancy = null;
if ($viewApplicationsId > 0) {
    $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "vacancies WHERE id = ?", [$viewApplicationsId]);
    $viewVacancy = $stmt->fetch();

    if ($viewVacancy) {
        $stmt = $db->query("SELECT * FROM " . DB_PREFIX . "vacancy_applications WHERE vacancy_id = ? ORDER BY created_at DESC", [$viewApplicationsId]);
        $applications = $stmt->fetchAll();
    }
}

// Fetch all vacancies
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "vacancies ORDER BY created_at DESC");
$vacancies = $stmt->fetchAll();

$pageTitle = 'Vacatures beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-briefcase me-2"></i>Vacatures</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVacancyModal">
            <i class="bi bi-plus-lg me-1"></i>Nieuwe vacature
        </button>
    </div>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?= flash_display() ?>

    <?php if ($viewVacancy): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sollicitaties voor: <?= e($viewVacancy['title']) ?></h5>
                <a href="<?= BASE_URL ?>/modules/vacancies/admin/" class="btn btn-sm btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Terug
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Naam</th>
                            <th>E-mail</th>
                            <th>Telefoon</th>
                            <th>CV</th>
                            <th>Datum</th>
                            <th>Motivatie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Geen sollicitaties ontvangen.</td></tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?= e($app['name']) ?></td>
                                    <td><a href="mailto:<?= e($app['email']) ?>"><?= e($app['email']) ?></a></td>
                                    <td><?= e($app['phone']) ?></td>
                                    <td><?= e($app['cv_filename']) ?></td>
                                    <td><?= e($app['created_at']) ?></td>
                                    <td>
                                        <?php if (!empty($app['motivation'])): ?>
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="popover"
                                                data-bs-content="<?= htmlspecialchars($app['motivation'], ENT_QUOTES) ?>"
                                                data-bs-trigger="focus" tabindex="0">
                                                Lees
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Titel</th>
                        <th>Afdeling</th>
                        <th>Locatie</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Sollicitaties</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vacancies)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Geen vacatures gevonden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vacancies as $vacancy): ?>
                            <?php
                            $appCount = $db->query("SELECT COUNT(*) FROM " . DB_PREFIX . "vacancy_applications WHERE vacancy_id = ?", [$vacancy['id']])->fetchColumn();
                            ?>
                            <tr>
                                <td><?= e($vacancy['title']) ?></td>
                                <td><?= e($vacancy['department']) ?></td>
                                <td><?= e($vacancy['location']) ?></td>
                                <td><?= e($vacancy['employment_type']) ?></td>
                                <td>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$vacancy['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= e($vacancy['status']) ?>">
                                        <button type="submit" class="badge border-0 <?= $vacancy['status'] === 'open' ? 'bg-success' : 'bg-secondary' ?>" title="Klik om te wijzigen">
                                            <?= $vacancy['status'] === 'open' ? 'Open' : 'Gesloten' ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <a href="?applications=<?= (int)$vacancy['id'] ?>" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-people me-1"></i><?= (int)$appCount ?>
                                    </a>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Vacature en alle sollicitaties verwijderen?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$vacancy['id'] ?>">
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

<!-- Add Vacancy Modal -->
<div class="modal fade" id="addVacancyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Nieuwe vacature toevoegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Functietitel <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Afdeling</label>
                            <input type="text" name="department" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Locatie</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dienstverband</label>
                            <select name="employment_type" class="form-select">
                                <option value="">- Selecteer -</option>
                                <option value="Fulltime">Fulltime</option>
                                <option value="Parttime">Parttime</option>
                                <option value="Freelance">Freelance</option>
                                <option value="Tijdelijk">Tijdelijk</option>
                                <option value="Stage">Stage</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Salarisrange</label>
                            <input type="text" name="salary_range" class="form-control" placeholder="bijv. €3.000 - €4.000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact e-mail</label>
                            <input type="email" name="contact_email" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Functiebeschrijving</label>
                            <textarea name="description" class="form-control" rows="4"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Vereisten</label>
                            <textarea name="requirements" class="form-control" rows="4"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Vacature opslaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Enable popovers
document.addEventListener('DOMContentLoaded', function () {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (el) {
        return new bootstrap.Popover(el);
    });
});
</script>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
