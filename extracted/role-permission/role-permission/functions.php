<?php
/**
 * Role & Permission Module — Helper Functions
 */

/**
 * Controleer of de huidige ingelogde gebruiker een specifiek recht heeft.
 * Admins en super_admins hebben altijd alle rechten.
 *
 * @param string $permission  Rechtslug, bijv. 'manage_pages'
 * @return bool
 */
function rp_has_permission(string $permission): bool
{
    if (!Auth::isLoggedIn()) {
        return false;
    }

    // Admins en super_admins hebben altijd alle rechten
    $role = $_SESSION['user_role'] ?? '';
    if (in_array($role, ['admin', 'super_admin'], true)) {
        return true;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId === 0) {
        return false;
    }

    static $cache = [];
    if (array_key_exists($userId, $cache) && array_key_exists($permission, $cache[$userId])) {
        return $cache[$userId][$permission];
    }

    $db = Database::getInstance();
    $result = $db->fetch(
        "SELECT p.id
         FROM `" . DB_PREFIX . "rp_permissions` p
         INNER JOIN `" . DB_PREFIX . "rp_role_permissions` rp ON rp.permission_id = p.id
         INNER JOIN `" . DB_PREFIX . "rp_user_roles` ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = ? AND p.slug = ?
         LIMIT 1",
        [$userId, $permission]
    );

    $cache[$userId][$permission] = $result !== null;
    return $cache[$userId][$permission];
}

/**
 * Haal alle aangepaste rollen op van een gebruiker.
 *
 * @param int $userId
 * @return array
 */
function rp_get_user_roles(int $userId): array
{
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT r.*
         FROM `" . DB_PREFIX . "rp_roles` r
         INNER JOIN `" . DB_PREFIX . "rp_user_roles` ur ON ur.role_id = r.id
         WHERE ur.user_id = ?
         ORDER BY r.name",
        [$userId]
    );
}

/**
 * Haal alle rechten op van een specifieke rol.
 *
 * @param int $roleId
 * @return array
 */
function rp_get_role_permissions(int $roleId): array
{
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT p.*
         FROM `" . DB_PREFIX . "rp_permissions` p
         INNER JOIN `" . DB_PREFIX . "rp_role_permissions` rp ON rp.permission_id = p.id
         WHERE rp.role_id = ?
         ORDER BY p.permission_group, p.name",
        [$roleId]
    );
}

/**
 * Controleer of een gebruiker een specifieke rol heeft (op basis van slug).
 *
 * @param int    $userId
 * @param string $roleSlug
 * @return bool
 */
function rp_user_has_role(int $userId, string $roleSlug): bool
{
    $db = Database::getInstance();
    $result = $db->fetch(
        "SELECT ur.role_id
         FROM `" . DB_PREFIX . "rp_user_roles` ur
         INNER JOIN `" . DB_PREFIX . "rp_roles` r ON r.id = ur.role_id
         WHERE ur.user_id = ? AND r.slug = ?
         LIMIT 1",
        [$userId, $roleSlug]
    );
    return $result !== null;
}

/**
 * Haal alle beschikbare rechten op, gegroepeerd per groep.
 *
 * @return array  ['groepnaam' => [permissie, ...], ...]
 */
function rp_get_permissions_grouped(): array
{
    $db = Database::getInstance();
    $all = $db->fetchAll(
        "SELECT * FROM `" . DB_PREFIX . "rp_permissions` ORDER BY permission_group, name"
    );
    $grouped = [];
    foreach ($all as $p) {
        $grouped[$p['permission_group']][] = $p;
    }
    return $grouped;
}
