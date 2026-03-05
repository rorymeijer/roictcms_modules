<?php
/**
 * Slider Module - Install
 * Maakt de benodigde databasetabel aan.
 */

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "slider_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL DEFAULT '',
        subtitle VARCHAR(500) DEFAULT '',
        button_text VARCHAR(150) DEFAULT '',
        button_url VARCHAR(500) DEFAULT '',
        image_path VARCHAR(500) NOT NULL DEFAULT '',
        sort_order INT DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
