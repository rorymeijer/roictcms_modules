<?php
/**
 * Meertaligheid Module - Uninstall
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "translations");
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "languages");

foreach (['multilingual_show_switcher', 'multilingual_default_lang'] as $key) {
    $db->query("DELETE FROM " . DB_PREFIX . "settings WHERE setting_key = ?", [$key]);
}
