<?php
defined('ROICT_CMS') or die('No direct access');

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "webhooks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        url VARCHAR(500) NOT NULL,
        event VARCHAR(100) NOT NULL,
        secret VARCHAR(255) DEFAULT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "webhook_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        webhook_id INT NOT NULL,
        event VARCHAR(100) NOT NULL,
        payload TEXT,
        response_code INT DEFAULT NULL,
        response_body TEXT,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
