<?php
/**
 * Cache Manager Module - Uninstall
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();

foreach (['cache_manager_enabled', 'cache_manager_ttl', 'cache_manager_exclude'] as $key) {
    $db->query("DELETE FROM " . DB_PREFIX . "settings WHERE setting_key = ?", [$key]);
}

// Cache leegmaken
$cacheDir = BASE_PATH . '/cache/pages/';
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . '*.html') as $file) {
        @unlink($file);
    }
}
