<?php
defined('ROICT_CMS') or die('No direct access');

$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "rss_feeds");

// Remove all rss cache keys from settings
$stmt = $db->query("DELETE FROM " . DB_PREFIX . "settings WHERE setting_key LIKE 'rss_cache_%'");
