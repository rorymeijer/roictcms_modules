<?php
/**
 * Frontend Login — Beschermde inhoud beheren
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Beschermde inhoud';
$activePage = 'frontend-login';

// ── Acties ────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$id     = (int) ($_GET['id'] ?? 0);

if ($action && $id) {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/frontend-login/admin/protected.php');
    }
    switch ($action) {
        case 'enable':
            $db->update(DB_PREFIX . 'fl_protected', ['active' => 1], 'id = ?', [$id]);
            flash('success', 'Pad ingeschakeld.');
            break;
        case 'disable':
            $db->update(DB_PREFIX . 'fl_protected', ['active' => 0], 'id = ?', [$id]);
            flash('success', 'Pad uitgeschakeld.');
            break;
        case 'delete':
            $db->delete(DB_PREFIX . 'fl_protected', 'id = ?', [$id]);
            flash('success', 'Pad verwijderd.');
            break;
    }
    redirect(BASE_URL . '/modules/frontend-login/admin/protected.php');
}

// ── Nieuw pad toevoegen ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/frontend-login/admin/protected.php');
    }

    $path  = trim($_POST['path']        ?? '');
    $descr = trim($_POST['description'] ?? '');

    if (!$path) {
        flash('error', 'Vul een URL-pad in.');
    } elseif (!str_starts_with($path, '/')) {
        flash('error', 'Het pad moet beginnen met een schuine streep, bijv. /over-ons');
    } elseif ($db->fetch("SELECT id FROM `" . DB_PREFIX . "fl_protected` WHERE `path` = ?", [$path])) {
        flash('error', 'Dit pad is al toegevoegd.');
    } else {
        $db->insert(DB_PREFIX . 'fl_protected', [
            'path'        => $path,
            'description' => $descr,
            'active'      => 1,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        flash('success', 'Beschermd pad toegevoegd.');
    }
    redirect(BASE_URL . '/modules/frontend-login/admin/protected.php');
}

// ── Lijst ophalen ─────────────────────────────────────────────────────────
$protected = $db->fetchAll(
    "SELECT * FROM `" . DB_PREFIX . "fl_protected` ORDER BY created_at DESC"
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
        BASE_URL . '/modules/frontend-login/admin/'              => ['Gebruikers',        'people',      false],
        BASE_URL . '/modules/frontend-login/admin/protected.php' => ['Beschermde inhoud', 'shield-lock', true],
        BASE_URL . '/modules/frontend-login/admin/settings.php'  => ['Instellingen',      'gear',        false],
    ];
    foreach ($subnav as $href => [$label, $icon, $isActive]): ?>
    <a href="<?= $href ?>"
       style="display:inline-flex;align-items:center;gap:.45rem;padding:.45rem 1rem;border-radius:8px;text-decoration:none;font-size:.875rem;font-weight:600;border:1.5px solid <?= $isActive ? 'var(--primary)' : 'var(--border)' ?>;background:<?= $isActive ? 'var(--primary)' : 'white' ?>;color:<?= $isActive ? 'white' : 'var(--text-muted)' ?>;">
        <i class="bi bi-<?= $icon ?>"></i> <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?= renderFlash() ?>

<div class="row g-4">
    <!-- Lijst van beschermde paden -->
    <div class="col-lg-8">
        <div class="cms-card">
            <div class="cms-card-header">
                <span class="cms-card-title">Beschermde URL-paden</span>
                <span class="badge bg-secondary"><?= count($protected) ?></span>
            </div>
            <div class="cms-card-body p-0">
                <?php if (empty($protected)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-shield" style="font-size:2rem;display:block;opacity:.3;margin-bottom:.5rem;"></i>
                    Nog geen paden beschermd. Voeg een pad toe om te beginnen.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>URL-pad</th>
                                <th>Omschrijving</th>
                                <th>Status</th>
                                <th class="text-end">Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($protected as $row): ?>
                            <tr>
                                <td>
                                    <code style="font-size:.82rem;color:#2563eb;background:#eff6ff;padding:.2rem .5rem;border-radius:5px;">
                                        <?= e($row['path']) ?>
                                    </code>
                                    <?php if (str_ends_with($row['path'], '*')): ?>
                                    <span style="font-size:.7rem;background:#fef3c7;color:#92400e;border-radius:4px;padding:.1rem .4rem;margin-left:.3rem;">wildcard</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.82rem;color:var(--text-muted);">
                                    <?= $row['description'] ? e($row['description']) : '—' ?>
                                </td>
                                <td>
                                    <?php if ($row['active']): ?>
                                    <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;border-radius:6px;background:#dcfce71a;color:#16a34a;font-size:.75rem;font-weight:700;">
                                        <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>Actief
                                    </span>
                                    <?php else: ?>
                                    <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;border-radius:6px;background:#f1f5f9;color:#6b7280;font-size:.75rem;font-weight:700;">
                                        <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>Uitgeschakeld
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if ($row['active']): ?>
                                        <a href="?action=disable&id=<?= $row['id'] ?>&csrf_token=<?= csrf_token() ?>"
                                           class="btn btn-sm btn-outline-secondary" title="Uitschakelen">
                                            <i class="bi bi-toggle-on"></i>
                                        </a>
                                        <?php else: ?>
                                        <a href="?action=enable&id=<?= $row['id'] ?>&csrf_token=<?= csrf_token() ?>"
                                           class="btn btn-sm btn-outline-success" title="Inschakelen">
                                            <i class="bi bi-toggle-off"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="?action=delete&id=<?= $row['id'] ?>&csrf_token=<?= csrf_token() ?>"
                                           class="btn btn-sm btn-outline-danger" title="Verwijderen"
                                           onclick="return confirm('Dit pad definitief verwijderen?')">
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

    <!-- Formulier nieuw pad + uitleg -->
    <div class="col-lg-4">
        <div class="cms-card">
            <div class="cms-card-header">
                <span class="cms-card-title">Pad toevoegen</span>
            </div>
            <div class="cms-card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">URL-pad</label>
                        <input type="text" name="path" class="form-control" required
                               placeholder="/over-ons" maxlength="500">
                        <div class="form-text">Begin altijd met <code>/</code>. Gebruik <code>*</code> als wildcard.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Omschrijving <small class="text-muted">(optioneel)</small></label>
                        <input type="text" name="description" class="form-control"
                               placeholder="bijv. Ledenpagina" maxlength="255">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-shield-plus me-1"></i> Toevoegen
                    </button>
                </form>
            </div>
        </div>

        <!-- Voorbeelden -->
        <div class="cms-card mt-3">
            <div class="cms-card-header">
                <span class="cms-card-title">Voorbeelden</span>
            </div>
            <div class="cms-card-body" style="font-size:.82rem;">
                <p class="text-muted mb-2">Eén specifieke pagina:</p>
                <code style="display:block;background:#1e293b;color:#7dd3fc;padding:.6rem .9rem;border-radius:6px;margin-bottom:.9rem;">/leden-alleen</code>
                <p class="text-muted mb-2">Alle nieuwsberichten:</p>
                <code style="display:block;background:#1e293b;color:#7dd3fc;padding:.6rem .9rem;border-radius:6px;margin-bottom:.9rem;">/nieuws/*</code>
                <p class="text-muted mb-2">Gehele sectie + subpagina's:</p>
                <code style="display:block;background:#1e293b;color:#7dd3fc;padding:.6rem .9rem;border-radius:6px;">/members/*</code>
                <p class="text-muted mt-2 mb-0" style="font-size:.78rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    De inlogpagina zelf wordt nooit geblokkeerd, ook als die onder een beschermd pad valt.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
