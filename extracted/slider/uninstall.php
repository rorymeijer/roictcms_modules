<?php
/**
 * Slider Module - Uninstall
 * Verwijdert de databasetabel.
 */

$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "slider_items");
