<?php
/**
 * Portfolio Module - Uninstall
 * Verwijdert de databasetabellen.
 */

$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "portfolio_items");
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "portfolio_categories");
