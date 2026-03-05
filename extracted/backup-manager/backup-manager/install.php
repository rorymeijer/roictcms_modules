<?php
$db = Database::getInstance();
$db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "backup_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename` VARCHAR(255) NOT NULL,
    `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
Settings::set('backup_max_files', '10');
