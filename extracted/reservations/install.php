<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

$db = Database::getInstance();

$db->exec("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "reservation_slots (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slot_date DATE NOT NULL,
        slot_time TIME NOT NULL,
        max_reservations INT DEFAULT 1,
        title VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "reservations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slot_id INT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(50),
        notes TEXT,
        status ENUM('pending','confirmed','cancelled') DEFAULT 'pending',
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
