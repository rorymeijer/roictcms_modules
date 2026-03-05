<?php
/**
 * Role & Permission Module — Gebruikers & rollen
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db = Database::getInstance();

$pageTitle  = 'Gebruikers & Rollen';
$activePage = 'role-permission';

// Specifieke rol filteren?
$filterRoleId = isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0;

// Rol toewijzen / verwijderen via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'Ongeldige aanvraag.');
        redirect(BASE_URL . '/modules/role-permission/admin/users.php');
    }

    $userId  = (int) ($_POST['user_id'] ?? 0);
    $roleIds = array_map('intval', (array) ($_POST['role_ids'] ?? []));

    if ($userId > 0) {
        // Verwijder alle bestaande rolkoppelingen voor deze gebruiker
        $db->query(
            "DELETE FROM `" . DB_PREFIX . "rp_user_roles` WHERE user_id = ?",
            [$userId]
        );
        // Voeg geselecteerde rollen in
        foreach ($roleIds as $rid) {
            if ($rid > 0) {
                $db->query(
                    "INSERT IGNORE INTO `" . DB_PREFIX . "rp_user_roles` (user_id, role_id) VALUES (?, ?)",
                    [$userId, $rid]
                );
            }
        }
        flash('success', 'Rollen bijgewerkt.');
    }
    redirect(BASE_URL . '/modules/role-permission/admin/users.php' . ($filterRoleId ? '?role_id=' . $filterRoleId : ''));
}

// Alle gebruikers ophalen
$users = $db->fetchAll(
    "SELECT u.id, u.username, u.email, u.role, u.status
     FROM `" . DB_PREFIX . "users` u
     ORDER BY u.username"
);

// Alle rollen
$allRoles = $db->fetchAll(
    "SELECT * FROM `" . DB_PREFIX . "rp_roles` ORDER BY name"
);

// Gekoppelde rollen per gebruiker
$userRolesMap = [];
$rows = $db->fetchAll(
    "SELECT ur.user_id, ur.role_id FROM `" . DB_PREFIX . "rp_user_roles` ur"
);
foreach ($rows as $row) {
    $userRolesMap[$row['user_id']][] = (int) $row['role_id'];
}

// Filter op rol
$filterRole = null;
if ($filterRoleId) {
    $filterRole = $db->fetch(
        "SELECT * FROM `" . DB_PREFIX . "rp_roles` WHERE id = ?",
        [$filterRoleId]
    );
    if ($filterRole) {
        $filteredUserIds = $db->fetchAll(
            "SELECT user_id FROM `" . DB_PREFIX . "rp_user_roles` WHERE role_id = ?",
            [$filterRoleId]
        );
        $filteredIds = array_column($filteredUserIds, 'user_id');
        $users = array_filter($users, fn($u) => in_array($u['id'], $filteredIds));
    }
}

$builtinRoleLabels = [
    'admin'       => ['danger',    'Admin'],
    'editor'      => ['primary',   'Editor'],
    'author'      => ['secondary', 'Auteur'],
    'super_admin' => ['dark',      'Super Admin'],
];

require_once ADMIN_PATH . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/modules/role-permission/assets/css/role-permission.css">

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= BASE_URL ?>/modules/role-permission/admin/" class="btn btn-sm btn-outline-secondary btn-icon">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">Gebruikers &amp; Rollen</h1>
        <p class="text-muted mb-0" style="font-size:.85rem;">
            <?php if ($filterRole): ?>
                Gebruikers gefilterd op rol: <strong><?= e($filterRole['name']) ?></strong>
                — <a href="users.php">Alles tonen</a>
            <?php else: ?>
                Wijs aangepaste rollen toe aan gebruikers.
            <?php endif; ?>
        </p>
    </div>
</div>

<?= renderFlash() ?>

<?php if (empty($allRoles)): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Er zijn nog geen rollen aangemaakt. <a href="role.php">Maak eerst een rol aan</a> voordat u gebruikers kunt koppelen.
</div>
<?php endif; ?>

<div class="cms-card">
    <?php if (empty($users)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-people" style="font-size:2.5rem;opacity:.3;"></i>
        <p class="mt-3">Geen gebruikers gevonden.</p>
    </div>
    <?php else: ?>
    <table class="cms-table">
        <thead>
            <tr>
                <th>Gebruiker</th>
                <th>E-mail</th>
                <th>Ingebouwde rol</th>
                <th>Aangepaste rollen</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <?php
                $userId       = (int) $user['id'];
                $assignedIds  = $userRolesMap[$userId] ?? [];
                $bRole        = $builtinRoleLabels[$user['role']] ?? ['secondary', $user['role']];
            ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#2563eb,#7c3aed);
                                    display:flex;align-items:center;justify-content:center;
                                    font-size:.8rem;font-weight:700;color:white;flex-shrink:0;">
                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                        </div>
                        <span class="fw-semibold"><?= e($user['username']) ?></span>
                    </div>
                </td>
                <td class="text-muted"><?= e($user['email']) ?></td>
                <td>
                    <span class="badge bg-<?= $bRole[0] ?>"><?= $bRole[1] ?></span>
                </td>
                <td>
                    <?php if (empty($assignedIds)): ?>
                        <span class="text-muted small">—</span>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($allRoles as $r): ?>
                                <?php if (in_array((int) $r['id'], $assignedIds)): ?>
                                <span class="rp-role-badge" style="background:<?= e($r['color']) ?>20;color:<?= e($r['color']) ?>;border:1px solid <?= e($r['color']) ?>40;">
                                    <?= e($r['name']) ?>
                                </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($allRoles)): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            onclick="rpOpenModal(<?= $userId ?>, '<?= e(addslashes($user['username'])) ?>', <?= json_encode($assignedIds) ?>)">
                        <i class="bi bi-pencil"></i> Rollen
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if (!empty($allRoles)): ?>
<!-- Modal voor rol-toewijzing -->
<div class="modal fade" id="rpRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rollen toewijzen aan <span id="rpModalUsername"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rpRoleForm">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" id="rpModalUserId">
                <div class="modal-body">
                    <p class="text-muted small mb-3">Selecteer welke aangepaste rollen deze gebruiker krijgt. De ingebouwde rol wordt hier niet gewijzigd.</p>
                    <div class="rp-permission-list" id="rpModalRoles">
                        <?php foreach ($allRoles as $r): ?>
                        <label class="rp-perm-item">
                            <input type="checkbox"
                                   name="role_ids[]"
                                   value="<?= (int) $r['id'] ?>"
                                   class="rp-perm-checkbox rp-modal-check"
                                   data-role-id="<?= (int) $r['id'] ?>">
                            <div class="rp-perm-info">
                                <span class="rp-perm-name d-flex align-items-center gap-2">
                                    <span class="rp-role-dot" style="background:<?= e($r['color']) ?>;"></span>
                                    <?= e($r['name']) ?>
                                </span>
                                <?php if ($r['description']): ?>
                                <span class="rp-perm-desc"><?= e($r['description']) ?></span>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function rpOpenModal(userId, username, assignedIds) {
    document.getElementById('rpModalUserId').value = userId;
    document.getElementById('rpModalUsername').textContent = username;
    document.querySelectorAll('.rp-modal-check').forEach(cb => {
        cb.checked = assignedIds.includes(parseInt(cb.dataset.roleId));
    });
    new bootstrap.Modal(document.getElementById('rpRoleModal')).show();
}
</script>
<?php endif; ?>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
