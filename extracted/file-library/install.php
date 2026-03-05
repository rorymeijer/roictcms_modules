<?php
/**
 * File Library Module - Install
 * Maakt de benodigde databasetabel aan.
 */

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "file_library_files (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT,
        filename VARCHAR(255) NOT NULL DEFAULT '',
        original_name VARCHAR(255) NOT NULL DEFAULT '',
        file_size INT DEFAULT 0,
        file_type VARCHAR(100) DEFAULT '',
        download_count INT DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
