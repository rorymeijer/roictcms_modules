<?php
/**
 * Two-Factor Authentication – Installatiescript
 * Wordt eenmalig uitgevoerd door ModuleManager::install().
 */
$db = Database::getInstance();

// Voeg tfa_secret en tfa_enabled kolommen toe aan de users-tabel
// Gebruik ALTER TABLE met IF NOT EXISTS-check via kolominspectie
$columns = $db->fetchAll(
    "SHOW COLUMNS FROM `" . DB_PREFIX . "users` LIKE 'tfa_enabled'"
);
if (empty($columns)) {
    $db->query(
        "ALTER TABLE `" . DB_PREFIX . "users`
         ADD COLUMN `tfa_enabled` TINYINT(1) NOT NULL DEFAULT 0,
         ADD COLUMN `tfa_secret`  VARCHAR(64)          DEFAULT NULL"
    );
}

// Standaardinstellingen
Settings::set('tfa_required_for_admin', '0'); // 0 = optioneel, 1 = verplicht voor admins
Settings::set('tfa_issuer', Settings::get('site_name', 'ROICT CMS'));
