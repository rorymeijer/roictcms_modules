<?php
defined('ROICT_CMS') or die('No direct access');

$db = Database::getInstance();

$db->query("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "google_maps_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        address VARCHAR(500) NOT NULL,
        latitude VARCHAR(20) DEFAULT NULL,
        longitude VARCHAR(20) DEFAULT NULL,
        zoom_level INT DEFAULT 14,
        map_width VARCHAR(20) DEFAULT '100%',
        map_height INT DEFAULT 400,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Default settings
if (empty(Settings::get('google_maps_api_key'))) {
    Settings::set('google_maps_api_key', '');
}
