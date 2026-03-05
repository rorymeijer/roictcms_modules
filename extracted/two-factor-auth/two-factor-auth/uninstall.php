<?php
/**
 * Two-Factor Authentication – Verwijderscript
 * Wordt uitgevoerd vóór verwijdering van de module.
 */
$db = Database::getInstance();

// Verwijder de 2FA-kolommen uit de users-tabel
$columns = $db->fetchAll(
    "SHOW COLUMNS FROM `" . DB_PREFIX . "users` LIKE 'tfa_enabled'"
);
if (!empty($columns)) {
    $db->query(
        "ALTER TABLE `" . DB_PREFIX . "users`
         DROP COLUMN IF EXISTS `tfa_enabled`,
         DROP COLUMN IF EXISTS `tfa_secret`"
    );
}

// Verwijder module-instellingen
$db->query(
    "DELETE FROM `" . DB_PREFIX . "settings` WHERE `key` LIKE 'tfa_%'"
);
