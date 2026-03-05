<?php
/**
 * Role & Permission Module — Init
 * Geladen bij elke pagina-aanvraag zolang de module actief is.
 */

require_once __DIR__ . '/functions.php';

// Voeg navigatielink toe aan het beheerpaneel
add_action('admin_sidebar_nav', function ($activePage) {
    $isActive = ($activePage ?? '') === 'role-permission' ? 'active' : '';
    echo '<a href="' . BASE_URL . '/modules/role-permission/admin/" class="nav-link ' . $isActive . '">'
       . '<i class="bi bi-shield-check"></i> Rollen & Rechten</a>';
});

// Voeg aangepaste rol-selectie toe aan het gebruiker bewerk-formulier
add_action('admin_user_form_fields', function ($user, $userId) {
    $db = Database::getInstance();
    $allRoles = $db->fetchAll(
        "SELECT * FROM `" . DB_PREFIX . "rp_roles` ORDER BY name"
    );
    if (empty($allRoles)) {
        return;
    }

    $assignedIds = [];
    if ($userId > 0) {
        $rows = $db->fetchAll(
            "SELECT role_id FROM `" . DB_PREFIX . "rp_user_roles` WHERE user_id = ?",
            [$userId]
        );
        $assignedIds = array_map('intval', array_column($rows, 'role_id'));
    }
    ?>
    <div class="mb-4">
        <label class="form-label">Aangepaste rollen</label>
        <div class="d-flex flex-wrap gap-3">
            <?php foreach ($allRoles as $role): ?>
            <div class="form-check">
                <input type="checkbox"
                       class="form-check-input"
                       name="custom_role_ids[]"
                       value="<?= (int) $role['id'] ?>"
                       id="crole_<?= (int) $role['id'] ?>"
                       <?= in_array((int) $role['id'], $assignedIds, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="crole_<?= (int) $role['id'] ?>">
                    <span style="display:inline-block;width:9px;height:9px;border-radius:50%;
                                 background:<?= e($role['color']) ?>;margin-right:4px;vertical-align:middle;"></span>
                    <?= e($role['name']) ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="form-text">Aangepaste rollen uit de Rollen &amp; Rechten module.</div>
    </div>
    <?php
});

// Custom rollen overrulen de standaard rol: als een gebruiker minstens één
// custom rol heeft, krijgt hij toegang tot het beheergedeelte.
add_filter('user_can_access_backend', function (bool $canAccess, int $userId): bool {
    if ($canAccess) {
        return true;
    }
    $db = Database::getInstance();
    $result = $db->fetch(
        "SELECT role_id FROM `" . DB_PREFIX . "rp_user_roles` WHERE user_id = ? LIMIT 1",
        [$userId]
    );
    return $result !== null;
}, 10);

// Sla aangepaste rollen op na het opslaan van een gebruiker
add_action('admin_user_saved', function ($userId, $isEdit) {
    if ($userId <= 0) {
        return;
    }

    $db = Database::getInstance();
    $roleIds = array_map('intval', (array) ($_POST['custom_role_ids'] ?? []));

    $db->query(
        "DELETE FROM `" . DB_PREFIX . "rp_user_roles` WHERE user_id = ?",
        [$userId]
    );

    foreach ($roleIds as $rid) {
        if ($rid > 0) {
            $db->query(
                "INSERT IGNORE INTO `" . DB_PREFIX . "rp_user_roles` (user_id, role_id) VALUES (?, ?)",
                [$userId, $rid]
            );
        }
    }
});
