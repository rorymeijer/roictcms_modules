<?php
$db = Database::getInstance();
$db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "backup_log`");
$db->delete(DB_PREFIX . 'settings', '`key` = ?', ['backup_max_files']);
