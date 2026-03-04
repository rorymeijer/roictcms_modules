<?php
/**
 * Site Statistieken – uninstall.php
 * Verwijdert databasetabellen en instellingen.
 */

$db = Database::getInstance();

$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "stats_pageviews`");
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "stats_sessions`");
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "stats_deletions`");

// Verwijder alle instellingen van deze module
$db->query("DELETE FROM `" . DB_PREFIX . "settings` WHERE `key` LIKE 'stats_%'");

return ['success' => true, 'message' => 'Site Statistieken verwijderd. Alle data is gewist.'];
