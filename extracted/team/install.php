<?php
/**
 * Team Module - Install
 * Maakt de benodigde databasetabel aan.
 */

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "team_members (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL DEFAULT '',
        role VARCHAR(150) DEFAULT '',
        bio TEXT,
        photo_url VARCHAR(500) DEFAULT '',
        email VARCHAR(150) DEFAULT '',
        linkedin_url VARCHAR(500) DEFAULT '',
        sort_order INT DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
