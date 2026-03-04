<?php
/**
 * Reacties Module — Install Script
 * Wordt éénmalig uitgevoerd bij installatie van de module.
 */

$db = Database::getInstance();

$db->getPdo()->exec("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "comments` (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        post_id      VARCHAR(255) NOT NULL,
        author_name  VARCHAR(150) NOT NULL,
        author_email VARCHAR(150) NOT NULL,
        content      TEXT NOT NULL,
        ip_address   VARCHAR(45),
        user_agent   VARCHAR(255),
        status       ENUM('pending','approved','spam') DEFAULT 'pending',
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_post_status (post_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Standaard instellingen
$settings = [
    'comments_moderation'    => '1',
    'comments_honeypot'      => '1',
    'comments_ratelimit'     => '3',
    'comments_success_msg'   => 'Bedankt voor uw reactie! Deze wordt zo spoedig mogelijk beoordeeld.',
];
foreach ($settings as $k => $v) {
    if (Settings::get($k) === null) {
        Settings::set($k, $v);
    }
}

return ['success' => true, 'message' => 'Reacties module geïnstalleerd.'];
