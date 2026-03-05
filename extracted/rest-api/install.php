<?php
/**
 * REST API Module - Install
 */

defined('BASE_PATH') || exit('No direct access');

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "api_keys (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        api_key VARCHAR(64) NOT NULL,
        permissions TEXT NULL COMMENT 'JSON array',
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        last_used DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_api_key (api_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
