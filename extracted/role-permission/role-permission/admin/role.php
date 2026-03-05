<?php
/**
 * Role & Permission Module — Rol aanmaken / bewerken
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

$roleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $roleId > 0;

// Laad bestaande rol bij bewerken
$role = null;
if ($isEdit) {
    $role = $db->fetch(
        "SELECT * FROM `" . DB_PREFIX . "rp_roles` WHERE id = ?",
        [$roleId]
    );
    if (!$role) {
        flash('error', 'Rol niet gevonden.');
        redirect(BASE_URL . '/modules/role-permission/admin/');
    }
}

$pageTitle  = $isEdit ? 'Rol bewerken: ' . $role['name'] : 'Nieuwe rol';
$activePage = 'role-permission';

// Verwerk formulier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/role-permission/admin/role.php' . ($isEdit ? '?id=' . $roleId : ''));
    }

    $name        = trim($_POST['name'] ?? '');
    $slugInput   = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $color       = trim($_POST['color'] ?? '#2563eb');
    $permissions = array_map('intval', (array) ($_POST['permissions'] ?? []));

    // Validatie
    if ($name === '') {
        flash('error', 'Vul een naam in voor de rol.');
        redirect(BASE_URL . '/modules/role-permission/admin/role.php' . ($isEdit ? '?id=' . $roleId : ''));
    }

    // Slug automatisch genereren als leeg
    $slug = $slugInput !== '' ? $slugInput : slug($name);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);

    // Kleurvalidatie (hex)
    if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $color)) {
        $color = '#2563eb';
    }

    // Controleer uniekheid slug
    $existingSlug = $db->fetch(
        "SELECT id FROM `" . DB_PREFIX . "rp_roles` WHERE slug = ? AND id != ?",
        [$slug, $roleId]
    );
    if ($existingSlug) {
        flash('error', "De slug '{$slug}' is al in gebruik. Kies een andere naam of slug.");
        redirect(BASE_URL . '/modules/role-permission/admin/role.php' . ($isEdit ? '?id=' . $roleId : ''));
    }

    if ($isEdit) {
        $db->update(DB_PREFIX . 'rp_roles', [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'color'       => $color,
        ], 'id = ?', [$roleId]);
    } else {
        $roleId = $db->insert(DB_PREFIX . 'rp_roles', [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'color'       => $color,
        ]);
    }

    // Rechten opslaan: verwijder bestaande en voeg nieuwe in
    $db->query(
        "DELETE FROM `" . DB_PREFIX . "rp_role_permissions` WHERE role_id = ?",
        [$roleId]
    );
    foreach ($permissions as $permId) {
        if ($permId > 0) {
            $db->query(
                "INSERT IGNORE INTO `" . DB_PREFIX . "rp_role_permissions` (role_id, permission_id) VALUES (?, ?)",
                [$roleId, $permId]
            );
        }
    }

    flash('success', $isEdit ? 'Rol bijgewerkt.' : 'Rol aangemaakt.');
    redirect(BASE_URL . '/modules/role-permission/admin/');
}

// Alle rechten, gegroepeerd
$allPermissions = $db->fetchAll(
    "SELECT * FROM `" . DB_PREFIX . "rp_permissions` ORDER BY permission_group, name"
);
$grouped = [];
foreach ($allPermissions as $p) {
    $grouped[$p['permission_group']][] = $p;
}

// Huidige rechten van de rol
$currentPermIds = [];
if ($isEdit) {
    $rows = $db->fetchAll(
        "SELECT permission_id FROM `" . DB_PREFIX . "rp_role_permissions` WHERE role_id = ?",
        [$roleId]
    );
    $currentPermIds = array_column($rows, 'permission_id');
}

require_once ADMIN_PATH . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/modules/role-permission/assets/css/role-permission.css">

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= BASE_URL ?>/modules/role-permission/admin/" class="btn btn-sm btn-outline-secondary btn-icon">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;"><?= e($pageTitle) ?></h1>
        <p class="text-muted mb-0" style="font-size:.85rem;">
            <?= $isEdit ? 'Wijzig de naam, kleur en rechten van deze rol.' : 'Maak een nieuwe aangepaste rol aan.' ?>
        </p>
    </div>
</div>

<?= renderFlash() ?>

<form method="POST">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Linker kolom: rolgegevens -->
        <div class="col-lg-4">
            <div class="cms-card">
                <div style="padding:1.25rem;">
                    <h6 style="font-weight:700;margin-bottom:1rem;">Rolgegevens</h6>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Naam <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               value="<?= e($role['name'] ?? '') ?>"
                               placeholder="bijv. SEO Specialist" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Slug</label>
                        <input type="text" name="slug" class="form-control"
                               value="<?= e($role['slug'] ?? '') ?>"
                               placeholder="automatisch op basis van naam" maxlength="100"
                               pattern="[a-z0-9\-]*">
                        <div class="form-text">Alleen kleine letters, cijfers en koppeltekens. Wordt automatisch gegenereerd als leeg.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Beschrijving</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Wat kan een gebruiker met deze rol doen?" maxlength="500"><?= e($role['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kleur</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="color" class="form-control form-control-color"
                                   value="<?= e($role['color'] ?? '#2563eb') ?>" style="width:50px;height:38px;padding:2px;">
                            <span class="text-muted small">Wordt gebruikt in de interface om de rol te onderscheiden.</span>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i>
                            <?= $isEdit ? 'Wijzigingen opslaan' : 'Rol aanmaken' ?>
                        </button>
                        <a href="<?= BASE_URL ?>/modules/role-permission/admin/" class="btn btn-outline-secondary">
                            Annuleren
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rechter kolom: rechten -->
        <div class="col-lg-8">
            <div class="cms-card">
                <div style="padding:1.25rem;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 style="font-weight:700;margin:0;">Rechten toewijzen</h6>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="rpSelectAll(true)">Alles selecteren</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="rpSelectAll(false)">Alles deselecteren</button>
                        </div>
                    </div>

                    <?php if (empty($grouped)): ?>
                    <p class="text-muted text-center py-3">Geen rechten beschikbaar. <a href="permissions.php">Voeg rechten toe.</a></p>
                    <?php else: ?>

                    <?php foreach ($grouped as $group => $perms): ?>
                    <div class="rp-permission-group mb-3">
                        <div class="rp-group-header">
                            <span><?= e($group) ?></span>
                            <span class="badge bg-secondary"><?= count($perms) ?></span>
                        </div>
                        <div class="rp-permission-list">
                            <?php foreach ($perms as $perm): ?>
                            <label class="rp-perm-item">
                                <input type="checkbox"
                                       name="permissions[]"
                                       value="<?= (int) $perm['id'] ?>"
                                       class="rp-perm-checkbox"
                                       <?= in_array((int) $perm['id'], $currentPermIds) ? 'checked' : '' ?>>
                                <div class="rp-perm-info">
                                    <span class="rp-perm-name"><?= e($perm['name']) ?></span>
                                    <?php if ($perm['description']): ?>
                                    <span class="rp-perm-desc"><?= e($perm['description']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="rp-perm-slug"><code><?= e($perm['slug']) ?></code></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function rpSelectAll(checked) {
    document.querySelectorAll('.rp-perm-checkbox').forEach(cb => cb.checked = checked);
}
</script>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
