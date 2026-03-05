<?php
/**
 * Role & Permission Module — Rollen overzicht
 */
require_once dirname(__DIR__, 3) . '/admin/includes/init.php';
Auth::requireAdmin();

$db        = Database::getInstance();
$pageTitle = 'Rollen & Rechten';
$activePage = 'role-permission';

// Rol verwijderen
if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id']) && csrf_verify()) {
    $id = (int) $_GET['id'];
    $db->query("DELETE FROM `" . DB_PREFIX . "rp_role_permissions` WHERE role_id = ?", [$id]);
    $db->query("DELETE FROM `" . DB_PREFIX . "rp_user_roles` WHERE role_id = ?", [$id]);
    $db->delete(DB_PREFIX . 'rp_roles', 'id = ?', [$id]);
    flash('success', 'Rol verwijderd.');
    redirect(BASE_URL . '/modules/role-permission/admin/');
}

// Alle rollen ophalen met aantal gekoppelde rechten en gebruikers
$roles = $db->fetchAll(
    "SELECT r.*,
            COUNT(DISTINCT rp.permission_id) AS permission_count,
            COUNT(DISTINCT ur.user_id)       AS user_count
     FROM `" . DB_PREFIX . "rp_roles` r
     LEFT JOIN `" . DB_PREFIX . "rp_role_permissions` rp ON rp.role_id = r.id
     LEFT JOIN `" . DB_PREFIX . "rp_user_roles` ur ON ur.role_id = r.id
     GROUP BY r.id
     ORDER BY r.name"
);

$totalPermissions = $db->fetch(
    "SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "rp_permissions`"
)['c'] ?? 0;

require_once ADMIN_PATH . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/modules/role-permission/assets/css/role-permission.css">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">Rollen &amp; Rechten</h1>
        <p class="text-muted mb-0" style="font-size:.85rem;"><?= count($roles) ?> rollen · <?= (int) $totalPermissions ?> beschikbare rechten</p>
    </div>
    <div class="d-flex gap-2">
        <a href="permissions.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-check"></i> Rechten beheren
        </a>
        <a href="role.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nieuwe rol
        </a>
    </div>
</div>

<?= renderFlash() ?>

<!-- Statistieken kaarten -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="rp-stat-card">
            <div class="rp-stat-icon" style="background:rgba(37,99,235,.12);color:#2563eb;">
                <i class="bi bi-shield-check"></i>
            </div>
            <div>
                <div class="rp-stat-value"><?= count($roles) ?></div>
                <div class="rp-stat-label">Aangepaste rollen</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rp-stat-card">
            <div class="rp-stat-icon" style="background:rgba(124,58,237,.12);color:#7c3aed;">
                <i class="bi bi-key"></i>
            </div>
            <div>
                <div class="rp-stat-value"><?= (int) $totalPermissions ?></div>
                <div class="rp-stat-label">Beschikbare rechten</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <?php
        $totalAssigned = $db->fetch(
            "SELECT COUNT(DISTINCT user_id) AS c FROM `" . DB_PREFIX . "rp_user_roles`"
        )['c'] ?? 0;
        ?>
        <div class="rp-stat-card">
            <div class="rp-stat-icon" style="background:rgba(22,163,74,.12);color:#16a34a;">
                <i class="bi bi-people"></i>
            </div>
            <div>
                <div class="rp-stat-value"><?= (int) $totalAssigned ?></div>
                <div class="rp-stat-label">Gebruikers met aangepaste rol</div>
            </div>
        </div>
    </div>
</div>

<!-- Rollen tabel -->
<div class="cms-card">
    <?php if (empty($roles)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-shield-plus" style="font-size:2.5rem;opacity:.3;"></i>
        <p class="mt-3 mb-2">Nog geen rollen aangemaakt.</p>
        <a href="role.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Eerste rol aanmaken
        </a>
    </div>
    <?php else: ?>
    <table class="cms-table">
        <thead>
            <tr>
                <th>Rol</th>
                <th>Beschrijving</th>
                <th class="text-center">Rechten</th>
                <th class="text-center">Gebruikers</th>
                <th>Aangemaakt</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $role): ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="rp-role-dot" style="background:<?= e($role['color']) ?>;"></span>
                        <strong><?= e($role['name']) ?></strong>
                    </div>
                </td>
                <td class="text-muted" style="max-width:260px;">
                    <?= $role['description'] ? e(mb_strimwidth($role['description'], 0, 80, '…')) : '<em class="text-muted">—</em>' ?>
                </td>
                <td class="text-center">
                    <span class="badge bg-primary"><?= (int) $role['permission_count'] ?></span>
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary"><?= (int) $role['user_count'] ?></span>
                </td>
                <td class="text-muted" style="font-size:.8rem;">
                    <?= date('d M Y', strtotime($role['created_at'])) ?>
                </td>
                <td>
                    <div class="action-btns">
                        <a href="role.php?id=<?= $role['id'] ?>"
                           class="btn btn-sm btn-outline-secondary btn-icon" title="Bewerken">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="users.php?role_id=<?= $role['id'] ?>"
                           class="btn btn-sm btn-outline-secondary btn-icon" title="Gebruikers">
                            <i class="bi bi-people"></i>
                        </a>
                        <a href="?action=delete&id=<?= $role['id'] ?>&csrf_token=<?= csrf_token() ?>"
                           class="btn btn-sm btn-outline-danger btn-icon"
                           title="Verwijderen"
                           onclick="return confirm('Rol \'<?= e(addslashes($role['name'])) ?>\' verwijderen? Gebruikers verliezen hierdoor hun rechten.')">
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

<!-- Ingebouwde rollen info -->
<div class="cms-card mt-4">
    <div class="card-body" style="padding:1.25rem;">
        <h6 class="mb-3" style="font-weight:700;"><i class="bi bi-info-circle text-primary me-2"></i>Ingebouwde rollen (CMS-kern)</h6>
        <p class="text-muted small mb-3">
            Naast de bovenstaande aangepaste rollen kent ROICT CMS drie vaste rollen. Deze worden beheerd via <a href="<?= BASE_URL ?>/admin/users/">Gebruikers</a>.
        </p>
        <div class="d-flex flex-wrap gap-3">
            <div class="rp-builtin-role">
                <span class="rp-role-dot" style="background:#ef4444;"></span>
                <div>
                    <strong>Admin</strong>
                    <div class="text-muted small">Volledige toegang tot alle functies</div>
                </div>
            </div>
            <div class="rp-builtin-role">
                <span class="rp-role-dot" style="background:#2563eb;"></span>
                <div>
                    <strong>Editor</strong>
                    <div class="text-muted small">Inhoud beheren en publiceren</div>
                </div>
            </div>
            <div class="rp-builtin-role">
                <span class="rp-role-dot" style="background:#64748b;"></span>
                <div>
                    <strong>Auteur</strong>
                    <div class="text-muted small">Eigen inhoud aanmaken</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ADMIN_PATH . '/includes/footer.php'; ?>
