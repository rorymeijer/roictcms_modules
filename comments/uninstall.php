<?php
/**
 * Reacties Module — Uninstall Script
 */
$db = Database::getInstance();
$db->getPdo()->exec("DROP TABLE IF EXISTS `" . DB_PREFIX . "comments`");

$keys = ['comments_moderation', 'comments_honeypot', 'comments_ratelimit', 'comments_success_msg'];
foreach ($keys as $k) {
    $db->delete(DB_PREFIX . 'settings', '`key` = ?', [$k]);
}

return ['success' => true];
