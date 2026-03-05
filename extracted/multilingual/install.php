<?php
/**
 * Meertaligheid Module - Install
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "languages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(10) NOT NULL,
        flag_emoji VARCHAR(10) NOT NULL DEFAULT '',
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "translations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        language_code VARCHAR(10) NOT NULL,
        content_type VARCHAR(50) NOT NULL,
        content_id INT NOT NULL,
        field_name VARCHAR(100) NOT NULL,
        translated_value TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_translation (language_code, content_type, content_id, field_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Voeg standaard Nederlandse taal toe indien niet aanwezig
$exists = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "languages WHERE code = 'nl'");
if (!$exists) {
    $db->insert(DB_PREFIX . 'languages', [
        'name'       => 'Nederlands',
        'code'       => 'nl',
        'flag_emoji' => '🇳🇱',
        'status'     => 'active',
        'is_default' => 1,
        'sort_order' => 0,
    ]);
}

// Standaard instellingen
$settings = [
    'multilingual_show_switcher' => '1',
    'multilingual_default_lang'  => 'nl',
];

foreach ($settings as $key => $value) {
    $ex = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "settings WHERE setting_key = ?", [$key]);
    if (!$ex) {
        $db->insert(DB_PREFIX . 'settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}
