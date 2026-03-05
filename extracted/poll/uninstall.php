<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

$db = Database::getInstance();
$db->exec("DROP TABLE IF EXISTS " . DB_PREFIX . "poll_votes");
$db->exec("DROP TABLE IF EXISTS " . DB_PREFIX . "poll_options");
$db->exec("DROP TABLE IF EXISTS " . DB_PREFIX . "polls");
