<?php
/**
 * Testimonials Module - Install
 * Maakt de benodigde databasetabel aan.
 */

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "testimonials (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL DEFAULT '',
        company VARCHAR(150) DEFAULT '',
        quote TEXT NOT NULL,
        rating TINYINT DEFAULT 5,
        avatar_url VARCHAR(500) DEFAULT '',
        status ENUM('active','inactive') DEFAULT 'active',
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
