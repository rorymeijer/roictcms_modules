<?php
/**
 * Cache Manager Module - Install
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();

$settings = [
    'cache_manager_enabled' => '0',
    'cache_manager_ttl'     => '3600',
    'cache_manager_exclude' => '/admin',
];

foreach ($settings as $key => $value) {
    $ex = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "settings WHERE setting_key = ?", [$key]);
    if (!$ex) {
        $db->insert(DB_PREFIX . 'settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}

// Maak cache directory aan
$cacheDir = BASE_PATH . '/cache/pages/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
