<?php
defined('ROICT_CMS') or die('No direct access');

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "social_feed_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        platform ENUM('instagram','twitter','facebook','linkedin') DEFAULT 'instagram',
        post_text TEXT,
        post_url VARCHAR(500) DEFAULT NULL,
        image_url VARCHAR(500) DEFAULT NULL,
        posted_at DATETIME DEFAULT NULL,
        sort_order INT DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
