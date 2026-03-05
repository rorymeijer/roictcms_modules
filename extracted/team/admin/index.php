<?php
/**
 * Team Module - Admin
 */

require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

// Verwerk POST-acties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name         = trim($_POST['name'] ?? '');
        $role         = trim($_POST['role'] ?? '');
        $bio          = trim($_POST['bio'] ?? '');
        $photo_url    = trim($_POST['photo_url'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $linkedin_url = trim($_POST['linkedin_url'] ?? '');
        $sort_order   = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') {
            set_flash('error', 'Naam is verplicht.');
        } else {
            $stmt = $db->query("INSERT INTO " . DB_PREFIX . "team_members (name, role, bio, photo_url, email, linkedin_url, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)", [$name, $role, $bio, $photo_url, $email, $linkedin_url, $sort_order]);
            set_flash('success', 'Teamlid succesvol toegevoegd.');
        }
        redirect(BASE_URL . '/modules/team/admin/');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("DELETE FROM " . DB_PREFIX . "team_members WHERE id = ?", [$id]);
            set_flash('success', 'Teamlid verwijderd.');
        }
        redirect(BASE_URL . '/modules/team/admin/');
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->query("UPDATE " . DB_PREFIX . "team_members SET status = IF(status='active','inactive','active') WHERE id = ?", [$id]);
            set_flash('success', 'Status bijgewerkt.');
        }
        redirect(BASE_URL . '/modules/team/admin/');
    }
}

// Haal teamleden op
$stmt = $db->query("SELECT * FROM " . DB_PREFIX . "team_members ORDER BY sort_order ASC, id ASC");
$members = $stmt->fetchAll();

$pageTitle = 'Team beheer';
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-people me-2"></i>Team</h1>
    </div>

    <?php flash_messages(); ?>

    <div class="row">
        <!-- Lijst van teamleden -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Teamleden (<?= count($members) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($members)): ?>
                        <p class="text-muted p-3 mb-0">Nog geen teamleden toegevoegd.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Foto</th>
                                        <th>Naam</th>
                                        <th>Functie</th>
                                        <th>Volgorde</th>
                                        <th>Status</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($member['photo_url'])): ?>
                                                    <img src="<?= e($member['photo_url']) ?>" alt="" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px;font-weight:700;">
                                                        <?= e(mb_strtoupper(mb_substr($member['name'], 0, 1))) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= e($member['name']) ?></strong>
                                                <?php if (!empty($member['email'])): ?>
                                                    <br><small class="text-muted"><?= e($member['email']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= e($member['role']) ?></td>
                                            <td><?= e($member['sort_order']) ?></td>
                                            <td>
                                                <?php if ($member['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Actief</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactief</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Status wijzigen">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Teamlid verwijderen?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
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

        <!-- Formulier nieuw teamlid -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Teamlid toevoegen</h5>
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
                            <label class="form-label fw-semibold">Functietitel</label>
                            <input type="text" name="role" class="form-control" placeholder="bijv. CEO, Developer">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bio</label>
                            <textarea name="bio" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Foto URL</label>
                            <input type="text" name="photo_url" class="form-control" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">E-mailadres</label>
                            <input type="email" name="email" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">LinkedIn URL</label>
                            <input type="text" name="linkedin_url" class="form-control" placeholder="https://linkedin.com/in/...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Volgorde</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i>Teamlid toevoegen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
