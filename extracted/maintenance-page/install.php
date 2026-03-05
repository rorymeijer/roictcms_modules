<?php
/**
 * Onderhoudspagina Module - Install
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();

$settings = [
    'maintenance_page_enabled'    => '0',
    'maintenance_page_title'      => 'Even geduld...',
    'maintenance_page_message'    => 'We zijn druk bezig om de website te verbeteren. Kom snel terug!',
    'maintenance_page_bg_color'   => '#1a1a2e',
    'maintenance_page_text_color' => '#ffffff',
    'maintenance_page_show_email' => '0',
    'maintenance_page_email'      => '',
];

foreach ($settings as $key => $value) {
    $ex = $db->fetchOne("SELECT id FROM " . DB_PREFIX . "settings WHERE setting_key = ?", [$key]);
    if (!$ex) {
        $db->insert(DB_PREFIX . 'settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}
