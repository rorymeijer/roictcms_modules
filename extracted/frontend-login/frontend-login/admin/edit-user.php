<?php
/**
 * Frontend Login — Gebruiker bewerken
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Gebruiker bewerken';
$activePage = 'frontend-login';

$id   = (int) ($_GET['id'] ?? 0);
$user = $id ? $db->fetch("SELECT * FROM `" . DB_PREFIX . "fl_users` WHERE id = ?", [$id]) : null;

if (!$user) {
    flash('error', 'Gebruiker niet gevonden.');
    redirect(BASE_URL . '/modules/frontend-login/admin/');
}

// ── Opslaan ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/frontend-login/admin/edit-user.php?id=' . $id);
    }

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $status   = in_array($_POST['status'] ?? '', ['active', 'inactive', 'pending'], true)
                ? $_POST['status'] : 'active';

    $errors = [];
    if (!$username) $errors[] = 'Gebruikersnaam is verplicht.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Vul een geldig e-mailadres in.';
    if ($password && strlen($password) < 8) $errors[] = 'Wachtwoord moet minimaal 8 tekens zijn.';

    // Controleer of e-mail al in gebruik is door een andere gebruiker
    $existing = $db->fetch(
        "SELECT id FROM `" . DB_PREFIX . "fl_users` WHERE `email` = ? AND `id` != ?",
        [$email, $id]
    );
    if ($existing) $errors[] = 'Dit e-mailadres is al in gebruik.';

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect(BASE_URL . '/modules/frontend-login/admin/edit-user.php?id=' . $id);
    }

    $data = [
        'username' => $username,
        'email'    => $email,
        'status'   => $status,
    ];
    if ($password) {
        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $db->update(DB_PREFIX . 'fl_users', $data, 'id = ?', [$id]);
    flash('success', 'Gebruiker bijgewerkt.');
    redirect(BASE_URL . '/modules/frontend-login/admin/');
}

require_once ADMIN_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">
            <i class="bi bi-person-lock me-2" style="color:var(--primary);"></i>Gebruiker bewerken
        </h1>
        <p class="text-muted mb-0" style="font-size:.85rem;"><?= e($user['username']) ?></p>
    </div>
    <a href="<?= BASE_URL ?>/modules/frontend-login/admin/" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Terug naar gebruikers
    </a>
</div>

<?= renderFlash() ?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="cms-card">
            <div class="cms-card-header">
                <span class="cms-card-title">Gegevens aanpassen</span>
            </div>
            <div class="cms-card-body">
                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Gebruikersnaam</label>
                        <input type="text" name="username" class="form-control" required maxlength="100"
                               value="<?= e($user['username']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">E-mailadres</label>
                        <input type="email" name="email" class="form-control" required maxlength="150"
                               value="<?= e($user['email']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nieuw wachtwoord <small class="text-muted">(leeg laten = niet wijzigen)</small></label>
                        <input type="password" name="password" class="form-control"
                               placeholder="Minimaal 8 tekens" autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active"   <?= $user['status'] === 'active'   ? 'selected' : '' ?>>Actief</option>
                            <option value="pending"  <?= $user['status'] === 'pending'  ? 'selected' : '' ?>>In afwachting</option>
                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactief</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Opslaan
                        </button>
                        <a href="<?= BASE_URL ?>/modules/frontend-login/admin/" class="btn btn-outline-secondary">
                            Annuleren
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Metadata -->
        <div class="cms-card mt-3">
            <div class="cms-card-body" style="font-size:.82rem;color:var(--text-muted);">
                <div class="d-flex gap-4 flex-wrap">
                    <div>
                        <strong>Aangemeld op</strong><br>
                        <?= date('d-m-Y H:i', strtotime($user['created_at'])) ?>
                    </div>
                    <div>
                        <strong>Laatste login</strong><br>
                        <?= $user['last_login'] ? date('d-m-Y H:i', strtotime($user['last_login'])) : 'Nooit' ?>
                    </div>
                    <div>
                        <strong>ID</strong><br>
                        #<?= (int) $user['id'] ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
