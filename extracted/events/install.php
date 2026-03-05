<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

$db = Database::getInstance();

$db->exec("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "events (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        location VARCHAR(255),
        start_date DATETIME NOT NULL,
        end_date DATETIME NULL,
        image_url VARCHAR(500),
        registration_url VARCHAR(500),
        status ENUM('active','cancelled') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
