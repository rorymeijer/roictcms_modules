<?php
/**
 * Onderhoudspagina Module - Uninstall
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();

$keys = [
    'maintenance_page_enabled',
    'maintenance_page_title',
    'maintenance_page_message',
    'maintenance_page_bg_color',
    'maintenance_page_text_color',
    'maintenance_page_show_email',
    'maintenance_page_email',
];

foreach ($keys as $key) {
    $db->query("DELETE FROM " . DB_PREFIX . "settings WHERE `key` = ?", [$key]);
}
