<?php
/**
 * Frontend Login Module — Uninstall Script
 * Wordt uitgevoerd vóór verwijdering van de module.
 */

$db = Database::getInstance();

$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "fl_users`");
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "fl_protected`");
$db->query("DELETE FROM `" . DB_PREFIX . "settings` WHERE `key` LIKE 'fl_%'");

return ['success' => true, 'message' => 'Frontend Login verwijderd.'];
