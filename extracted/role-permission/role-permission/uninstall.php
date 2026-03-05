<?php
/**
 * Role & Permission Module — Uninstall Script
 * Wordt uitgevoerd vóór verwijdering van de module.
 */

$db = Database::getInstance();

$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "rp_user_roles`");
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "rp_role_permissions`");
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "rp_permissions`");
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "rp_roles`");
