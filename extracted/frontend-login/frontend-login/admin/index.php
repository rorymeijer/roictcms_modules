<?php
/**
 * Frontend Login — Gebruikersbeheer
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Frontend Login';
$activePage = 'frontend-login';

// ── Acties ────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$id     = (int) ($_GET['id'] ?? 0);

if ($action && $id) {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/frontend-login/admin/');
    }
    switch ($action) {
        case 'activate':
            $db->update(DB_PREFIX . 'fl_users', ['status' => 'active'], 'id = ?', [$id]);
            flash('success', 'Gebruiker geactiveerd.');
            break;
        case 'deactivate':
            $db->update(DB_PREFIX . 'fl_users', ['status' => 'inactive'], 'id = ?', [$id]);
            flash('success', 'Gebruiker gedeactiveerd.');
            break;
        case 'delete':
            $db->delete(DB_PREFIX . 'fl_users', 'id = ?', [$id]);
            flash('success', 'Gebruiker verwijderd.');
            break;
    }
    redirect(BASE_URL . '/modules/frontend-login/admin/');
}

// ── Nieuwe gebruiker toevoegen ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['fl_form'] ?? '') === 'add_user') {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/frontend-login/admin/');
    }
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $status   = in_array($_POST['status'] ?? '', ['active', 'inactive', 'pending'], true)
                ? $_POST['status'] : 'active';

    $errors = [];
    if (!$username)                                        $errors[] = 'Gebruikersnaam is verplicht.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = 'Vul een geldig e-mailadres in.';
    if (strlen($password) < 8)                             $errors[] = 'Wachtwoord moet minimaal 8 tekens zijn.';
    if ($db->fetch("SELECT id FROM `" . DB_PREFIX . "fl_users` WHERE `email` = ?", [$email]))
                                                           $errors[] = 'Dit e-mailadres is al in gebruik.';

    if ($errors) {
        flash('error', implode(' ', $errors));
    } else {
        $db->insert(DB_PREFIX . 'fl_users', [
            'username'   => $username,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'status'     => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        flash('success', 'Gebruiker toegevoegd.');
    }
    redirect(BASE_URL . '/modules/frontend-login/admin/');
}

// ── Statistieken ──────────────────────────────────────────────────────────
$counts = [
    'total'    => (int) ($db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "fl_users`")['c']              ?? 0),
    'active'   => (int) ($db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "fl_users` WHERE status='active'")['c'] ?? 0),
    'pending'  => (int) ($db->fetch("SELECT COUNT(*) c FROM `" . DB_PREFIX . "fl_users` WHERE status='pending'")['c'] ?? 0),
];

// ── Gebruikerslijst ophalen ───────────────────────────────────────────────
$users = $db->fetchAll(
    "SELECT * FROM `" . DB_PREFIX . "fl_users` ORDER BY created_at DESC"
);

require_once ADMIN_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">
            <i class="bi bi-person-lock me-2" style="color:var(--primary);"></i>Frontend Login
        </h1>
        <p class="text-muted mb-0" style="font-size:.85rem;">Beheer leden en beschermde inhoud</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/modules/" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Terug naar modules
    </a>
</div>

<!-- Sub-navigatie -->
<div class="mb-4 d-flex gap-2 flex-wrap">
    <?php
    $subnav = [
        BASE_URL . '/modules/frontend-login/admin/'            => ['Gebruikers',       'people'],
        BASE_URL . '/modules/frontend-login/admin/protected.php' => ['Beschermde inhoud', 'shield-lock'],
        BASE_URL . '/modules/frontend-login/admin/settings.php'  => ['Instellingen',      'gear'],
    ];
    $currentFile = 'index';
    foreach ($subnav as $href => $item):
        $isActive = str_ends_with($href, 'admin/') ? $currentFile === 'index' : false;
    ?>
    <a href="<?= $href ?>"
       style="display:inline-flex;align-items:center;gap:.45rem;padding:.45rem 1rem;border-radius:8px;text-decoration:none;font-size:.875rem;font-weight:600;border:1.5px solid <?= $isActive ? 'var(--primary)' : 'var(--border)' ?>;background:<?= $isActive ? 'var(--primary)' : 'white' ?>;color:<?= $isActive ? 'white' : 'var(--text-muted)' ?>;">
        <i class="bi bi-<?= $item[1] ?>"></i> <?= $item[0] ?>
        <?php if ($item[0] === 'Gebruikers' && $counts['pending'] > 0): ?>
        <span style="background:#ef4444;color:white;border-radius:999px;padding:.05rem .45rem;font-size:.65rem;font-weight:700;"><?= $counts['pending'] ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<?= renderFlash() ?>

<!-- Statistiekkaarten -->
<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['Totale leden',       $counts['total'],   'people',      '#2563eb'],
        ['Actieve leden',      $counts['active'],  'person-check','#16a34a'],
        ['Wachten op activ.',  $counts['pending'], 'person-exclamation','#d97706'],
    ];
    foreach ($stats as [$label, $value, $icon, $color]): ?>
    <div class="col-md-4">
        <div class="cms-card" style="border-left:4px solid <?= $color ?>;">
            <div class="cms-card-body d-flex align-items-center gap-3 py-3">
                <div style="width:44px;height:44px;border-radius:10px;background:<?= $color ?>1a;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-<?= $icon ?>" style="font-size:1.3rem;color:<?= $color ?>;"></i>
                </div>
                <div>
                    <div style="font-size:1.6rem;font-weight:800;line-height:1;"><?= $value ?></div>
                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:.1rem;"><?= $label ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Gebruikerslijst -->
    <div class="col-lg-8">
        <div class="cms-card">
            <div class="cms-card-header">
                <span class="cms-card-title">Geregistreerde leden</span>
            </div>
            <div class="cms-card-body p-0">
                <?php if (empty($users)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people" style="font-size:2rem;display:block;opacity:.3;margin-bottom:.5rem;"></i>
                    Nog geen leden geregistreerd.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Gebruiker</th>
                                <th>Status</th>
                                <th>Aangemeld</th>
                                <th>Laatste login</th>
                                <th class="text-end">Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $statusColor = match($user['status']) {
                                'active'   => '#16a34a',
                                'inactive' => '#6b7280',
                                'pending'  => '#d97706',
                                default    => '#6b7280',
                            };
                            $statusLabel = match($user['status']) {
                                'active'   => 'Actief',
                                'inactive' => 'Inactief',
                                'pending'  => 'In afwachting',
                                default    => $user['status'],
                            };
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;font-size:.9rem;"><?= e($user['username']) ?></div>
                                    <div style="font-size:.78rem;color:var(--text-muted);"><?= e($user['email']) ?></div>
                                </td>
                                <td>
                                    <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;border-radius:6px;background:<?= $statusColor ?>1a;color:<?= $statusColor ?>;font-size:.75rem;font-weight:700;">
                                        <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                                        <?= $statusLabel ?>
                                    </span>
                                </td>
                                <td style="font-size:.82rem;color:var(--text-muted);">
                                    <?= date('d-m-Y', strtotime($user['created_at'])) ?>
                                </td>
                                <td style="font-size:.82rem;color:var(--text-muted);">
                                    <?= $user['last_login'] ? date('d-m-Y H:i', strtotime($user['last_login'])) : '—' ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if ($user['status'] !== 'active'): ?>
                                        <a href="?action=activate&id=<?= $user['id'] ?>&csrf_token=<?= csrf_token() ?>"
                                           class="btn btn-sm btn-outline-success" title="Activeren">
                                            <i class="bi bi-person-check"></i>
                                        </a>
                                        <?php else: ?>
                                        <a href="?action=deactivate&id=<?= $user['id'] ?>&csrf_token=<?= csrf_token() ?>"
                                           class="btn btn-sm btn-outline-secondary" title="Deactiveren">
                                            <i class="bi bi-person-dash"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>/modules/frontend-login/admin/edit-user.php?id=<?= $user['id'] ?>"
                                           class="btn btn-sm btn-outline-primary" title="Bewerken">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?action=delete&id=<?= $user['id'] ?>&csrf_token=<?= csrf_token() ?>"
                                           class="btn btn-sm btn-outline-danger" title="Verwijderen"
                                           onclick="return confirm('Gebruiker definitief verwijderen?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
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

    <!-- Nieuwe gebruiker toevoegen -->
    <div class="col-lg-4">
        <div class="cms-card">
            <div class="cms-card-header">
                <span class="cms-card-title">Lid toevoegen</span>
            </div>
            <div class="cms-card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="fl_form" value="add_user">

                    <div class="mb-3">
                        <label class="form-label">Gebruikersnaam</label>
                        <input type="text" name="username" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mailadres</label>
                        <input type="email" name="email" class="form-control" required maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Wachtwoord</label>
                        <input type="password" name="password" class="form-control" required minlength="8"
                               placeholder="Minimaal 8 tekens">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Actief</option>
                            <option value="pending">In afwachting</option>
                            <option value="inactive">Inactief</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus me-1"></i> Toevoegen
                    </button>
                </form>
            </div>
        </div>

        <!-- Gebruiksinstructies -->
        <div class="cms-card mt-3">
            <div class="cms-card-header">
                <span class="cms-card-title">Gebruik in thema</span>
            </div>
            <div class="cms-card-body">
                <p class="text-muted" style="font-size:.82rem;margin-bottom:.75rem;">
                    Voeg de shortcode toe aan een CMS-pagina voor het inlogformulier:
                </p>
                <code style="display:block;background:#1e293b;color:#7dd3fc;padding:.75rem 1rem;border-radius:8px;font-size:.82rem;">[frontend_login]</code>
                <p class="text-muted mt-3 mb-1" style="font-size:.82rem;">Of gebruik in een PHP-template:</p>
                <code style="display:block;background:#1e293b;color:#86efac;padding:.75rem 1rem;border-radius:8px;font-size:.82rem;">echo fl_login_form();</code>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
