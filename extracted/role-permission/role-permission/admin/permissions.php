<?php
/**
 * Role & Permission Module — Rechten beheren
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

$pageTitle  = 'Rechten beheren';
$activePage = 'role-permission';

// Recht verwijderen
if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id']) && csrf_verify()) {
    $id = (int) $_GET['id'];
    $db->query("DELETE FROM `" . DB_PREFIX . "rp_role_permissions` WHERE permission_id = ?", [$id]);
    $db->delete(DB_PREFIX . 'rp_permissions', 'id = ?', [$id]);
    flash('success', 'Recht verwijderd.');
    redirect(BASE_URL . '/modules/role-permission/admin/permissions.php');
}

// Recht toevoegen / bewerken
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/role-permission/admin/permissions.php');
    }

    $editId      = (int) ($_POST['edit_id'] ?? 0);
    $slug        = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $group       = trim($_POST['permission_group'] ?? 'Algemeen') ?: 'Algemeen';

    if ($name === '' || $slug === '') {
        flash('error', 'Naam en slug zijn verplicht.');
        redirect(BASE_URL . '/modules/role-permission/admin/permissions.php');
    }

    $duplicate = $db->fetch(
        "SELECT id FROM `" . DB_PREFIX . "rp_permissions` WHERE slug = ? AND id != ?",
        [$slug, $editId]
    );
    if ($duplicate) {
        flash('error', "Slug '{$slug}' is al in gebruik.");
        redirect(BASE_URL . '/modules/role-permission/admin/permissions.php');
    }

    if ($editId > 0) {
        $db->update(DB_PREFIX . 'rp_permissions', [
            'slug'             => $slug,
            'name'             => $name,
            'description'      => $description,
            'permission_group' => $group,
        ], 'id = ?', [$editId]);
        flash('success', 'Recht bijgewerkt.');
    } else {
        $db->insert(DB_PREFIX . 'rp_permissions', [
            'slug'             => $slug,
            'name'             => $name,
            'description'      => $description,
            'permission_group' => $group,
        ]);
        flash('success', 'Recht toegevoegd.');
    }
    redirect(BASE_URL . '/modules/role-permission/admin/permissions.php');
}

// Alle rechten ophalen
$permissions = $db->fetchAll(
    "SELECT p.*,
            COUNT(rp.role_id) AS role_count
     FROM `" . DB_PREFIX . "rp_permissions` p
     LEFT JOIN `" . DB_PREFIX . "rp_role_permissions` rp ON rp.permission_id = p.id
     GROUP BY p.id
     ORDER BY p.permission_group, p.name"
);

// Groepen voor dropdown
$groups = array_unique(array_column($permissions, 'permission_group'));
sort($groups);

require_once ADMIN_PATH . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/modules/role-permission/assets/css/role-permission.css">

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= BASE_URL ?>/modules/role-permission/admin/" class="btn btn-sm btn-outline-secondary btn-icon">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">Rechten beheren</h1>
        <p class="text-muted mb-0" style="font-size:.85rem;"><?= count($permissions) ?> rechten gedefinieerd</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm ms-auto" onclick="rpShowAddForm()">
        <i class="bi bi-plus-lg"></i> Nieuw recht
    </button>
</div>

<?= renderFlash() ?>

<!-- Formulier: nieuw / bewerken recht -->
<div class="cms-card mb-4" id="rpPermForm" style="display:none;">
    <div style="padding:1.25rem;">
        <h6 style="font-weight:700;margin-bottom:1rem;" id="rpPermFormTitle">Nieuw recht toevoegen</h6>
        <form method="POST" id="rpAddPermForm">
            <?= csrf_field() ?>
            <input type="hidden" name="edit_id" id="rpEditId" value="0">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                    <input type="text" name="slug" id="rpPermSlug" class="form-control"
                           placeholder="manage_something" required
                           pattern="[a-z0-9_]+"
                           title="Alleen kleine letters, cijfers en underscores">
                    <div class="form-text">Unieke sleutel, bijv. <code>manage_pages</code></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Naam <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="rpPermName" class="form-control"
                           placeholder="Pagina's beheren" required maxlength="150">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Groep</label>
                    <input type="text" name="permission_group" id="rpPermGroup" class="form-control"
                           placeholder="Algemeen" maxlength="100" list="rpGroupList">
                    <datalist id="rpGroupList">
                        <?php foreach ($groups as $g): ?>
                        <option value="<?= e($g) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Beschrijving</label>
                    <input type="text" name="description" id="rpPermDesc" class="form-control"
                           placeholder="Korte beschrijving" maxlength="255">
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg"></i> Opslaan
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="rpHideForm()">
                    Annuleren
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Rechten tabel -->
<div class="cms-card">
    <?php if (empty($permissions)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-key" style="font-size:2.5rem;opacity:.3;"></i>
        <p class="mt-3 mb-2">Nog geen rechten aangemaakt.</p>
        <button type="button" class="btn btn-primary btn-sm" onclick="rpShowAddForm()">
            <i class="bi bi-plus-lg"></i> Eerste recht toevoegen
        </button>
    </div>
    <?php else: ?>
    <table class="cms-table">
        <thead>
            <tr>
                <th>Slug</th>
                <th>Naam</th>
                <th>Groep</th>
                <th>Beschrijving</th>
                <th class="text-center">Rollen</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($permissions as $p): ?>
            <tr>
                <td><code class="rp-code"><?= e($p['slug']) ?></code></td>
                <td class="fw-semibold"><?= e($p['name']) ?></td>
                <td>
                    <span class="badge bg-light text-dark border"><?= e($p['permission_group']) ?></span>
                </td>
                <td class="text-muted small"><?= $p['description'] ? e($p['description']) : '—' ?></td>
                <td class="text-center">
                    <span class="badge bg-secondary"><?= (int) $p['role_count'] ?></span>
                </td>
                <td>
                    <div class="action-btns">
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary btn-icon"
                                title="Bewerken"
                                onclick="rpEditPerm(<?= (int) $p['id'] ?>, '<?= e(addslashes($p['slug'])) ?>', '<?= e(addslashes($p['name'])) ?>', '<?= e(addslashes($p['permission_group'])) ?>', '<?= e(addslashes($p['description'])) ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="?action=delete&id=<?= (int) $p['id'] ?>&<?= csrf_token() ?>=<?= $_SESSION['csrf_token'] ?? '' ?>"
                           class="btn btn-sm btn-outline-danger btn-icon"
                           title="Verwijderen"
                           onclick="return confirm('Recht \'<?= e(addslashes($p['name'])) ?>\' verwijderen? Het wordt ook uit alle rollen verwijderd.')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function rpShowAddForm() {
    document.getElementById('rpPermFormTitle').textContent = 'Nieuw recht toevoegen';
    document.getElementById('rpEditId').value = '0';
    document.getElementById('rpPermSlug').value = '';
    document.getElementById('rpPermName').value = '';
    document.getElementById('rpPermGroup').value = '';
    document.getElementById('rpPermDesc').value = '';
    document.getElementById('rpPermSlug').readOnly = false;
    document.getElementById('rpPermForm').style.display = 'block';
    document.getElementById('rpPermForm').scrollIntoView({behavior:'smooth'});
}

function rpEditPerm(id, slug, name, group, desc) {
    document.getElementById('rpPermFormTitle').textContent = 'Recht bewerken';
    document.getElementById('rpEditId').value = id;
    document.getElementById('rpPermSlug').value = slug;
    document.getElementById('rpPermSlug').readOnly = true; // slug niet wijzigen bij bewerken
    document.getElementById('rpPermName').value = name;
    document.getElementById('rpPermGroup').value = group;
    document.getElementById('rpPermDesc').value = desc;
    document.getElementById('rpPermForm').style.display = 'block';
    document.getElementById('rpPermForm').scrollIntoView({behavior:'smooth'});
}

function rpHideForm() {
    document.getElementById('rpPermForm').style.display = 'none';
}
</script>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
