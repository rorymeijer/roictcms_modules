<?php
/**
 * Zoekmodule - Uninstall
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();

$keys = ['search_max_results', 'search_pages', 'search_news'];
foreach ($keys as $key) {
    $db->query(
        "DELETE FROM " . DB_PREFIX . "settings WHERE setting_key = ?",
        [$key]
    );
}
