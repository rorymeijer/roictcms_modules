<?php
/**
 * Portfolio Module - Install
 * Maakt de benodigde databasetabellen aan.
 */

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "portfolio_categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL DEFAULT '',
        slug VARCHAR(150) NOT NULL DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "portfolio_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT,
        image_url VARCHAR(500) DEFAULT '',
        url VARCHAR(500) DEFAULT '',
        category_id INT DEFAULT NULL,
        sort_order INT DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
