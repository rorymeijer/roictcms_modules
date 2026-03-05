<?php
defined('ROICT_CMS') or die('No direct access');

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "popups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        content TEXT NOT NULL,
        trigger_type ENUM('time','exit_intent') DEFAULT 'time',
        trigger_delay INT DEFAULT 5,
        show_once TINYINT(1) DEFAULT 1,
        cookie_days INT DEFAULT 7,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
