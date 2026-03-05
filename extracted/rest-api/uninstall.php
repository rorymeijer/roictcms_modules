<?php
/**
 * REST API Module - Uninstall
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "api_keys");
