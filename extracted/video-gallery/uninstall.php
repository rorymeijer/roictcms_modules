<?php
/**
 * Video Gallery Module - Uninstall
 * Verwijdert de databasetabel.
 */

$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "video_gallery_items");
