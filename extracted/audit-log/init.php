<?php
/**
 * Auditlog Module - Init
 */

defined('BASE_PATH') || exit('No direct access');

class AuditLog
{
    /**
     * Log een beheersactie naar de database.
     */
    public static function log(string $action, string $details = ''): void
    {
        try {
            $db = Database::getInstance();
            $user = Auth::currentUser();

            $userId   = $user ? (int) $user['id'] : null;
            $userName = $user ? ($user['username'] ?? '') : 'Gast';
            $ip       = $_SERVER['REMOTE_ADDR'] ?? '';

            $db->insert(DB_PREFIX . 'audit_log', [
                'user_id'    => $userId,
                'user_name'  => $userName,
                'action'     => $action,
                'details'    => $details,
                'ip_address' => $ip,
            ]);
        } catch (Exception $e) {
            // Stil falen – logging mag de applicatie niet breken
            error_log('AuditLog::log fout: ' . $e->getMessage());
        }
    }

    /**
     * Haal logs op (nieuwste eerst), met optionele gebruikersfilter en paginering.
     */
    public static function getLogs(int $page = 1, int $perPage = 50, string $userFilter = ''): array
    {
        $db     = Database::getInstance();
        $offset = ($page - 1) * $perPage;

        if ($userFilter !== '') {
            $rows = $db->fetchAll(
                "SELECT * FROM " . DB_PREFIX . "audit_log
                  WHERE user_name LIKE ?
                  ORDER BY created_at DESC
                  LIMIT ? OFFSET ?",
                ['%' . $userFilter . '%', $perPage, $offset]
            );
            $countRow = $db->fetch(
                "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "audit_log WHERE user_name LIKE ?",
                ['%' . $userFilter . '%']
            );
            $total = (int) ($countRow['total'] ?? 0);
        } else {
            $rows = $db->fetchAll(
                "SELECT * FROM " . DB_PREFIX . "audit_log ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$perPage, $offset]
            );
            $countRow = $db->fetch("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "audit_log");
            $total = (int) ($countRow['total'] ?? 0);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Verwijder logs ouder dan $days dagen.
     */
    public static function purgeOld(int $days = 90): int
    {
        $db = Database::getInstance();
        $db->query(
            "DELETE FROM " . DB_PREFIX . "audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        return $db->affectedRows();
    }
}

// Sidebar navigatie koppeling
add_action('admin_sidebar_nav', function () {
    $url = BASE_URL . '/modules/audit-log/admin/';
    echo '<li class="nav-item">'
        . '<a class="nav-link" href="' . $url . '">'
        . '<i class="bi bi-clock-history me-2"></i>Auditlog'
        . '</a></li>';
});
