<?php
/**
 * Zoekmodule - Install
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();

// Standaard instellingen opslaan
$settings = [
    'search_max_results' => '10',
    'search_pages'       => '1',
    'search_news'        => '1',
];

foreach ($settings as $key => $value) {
    $exists = $db->fetchOne(
        "SELECT id FROM " . DB_PREFIX . "settings WHERE setting_key = ?",
        [$key]
    );
    if (!$exists) {
        $db->insert(DB_PREFIX . 'settings', [
            'setting_key'   => $key,
            'setting_value' => $value,
        ]);
    }
}
