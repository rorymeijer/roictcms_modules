<?php
$db = Database::getInstance();
$db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "redirects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source` VARCHAR(500) NOT NULL UNIQUE,
    `destination` VARCHAR(500) NOT NULL,
    `type` SMALLINT NOT NULL DEFAULT 301,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `hits` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
Settings::set('redirects_log_hits', '1');
