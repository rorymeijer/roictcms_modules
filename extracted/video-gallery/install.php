<?php
/**
 * Video Gallery Module - Install
 * Maakt de benodigde databasetabel aan.
 */

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "video_gallery_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT,
        video_url VARCHAR(500) NOT NULL DEFAULT '',
        thumbnail_url VARCHAR(500) DEFAULT '',
        gallery_group VARCHAR(100) DEFAULT 'default',
        sort_order INT DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
