<?php
/**
 * Frontend Login Module — Install Script
 * Wordt éénmalig uitgevoerd bij installatie van de module.
 */

$db = Database::getInstance();

// ── Tabel: frontendgebruikers ─────────────────────────────────────────────
$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "fl_users` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `username`   VARCHAR(100) NOT NULL,
        `email`      VARCHAR(150) NOT NULL,
        `password`   VARCHAR(255) NOT NULL,
        `status`     ENUM('active','inactive','pending') DEFAULT 'pending',
        `last_login` DATETIME NULL DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_fl_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Tabel: beschermde URL-paden ───────────────────────────────────────────
$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "fl_protected` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `path`        VARCHAR(500) NOT NULL,
        `description` VARCHAR(255) DEFAULT '',
        `active`      TINYINT(1) DEFAULT 1,
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Standaardinstellingen ─────────────────────────────────────────────────
$defaults = [
    'fl_login_page_slug'     => 'inloggen',
    'fl_allow_registration'  => '1',
    'fl_auto_activate'       => '1',
    'fl_redirect_after_login' => '',
];
foreach ($defaults as $key => $value) {
    if (Settings::get($key) === null) {
        Settings::set($key, $value);
    }
}

return ['success' => true, 'message' => 'Frontend Login geïnstalleerd.'];
