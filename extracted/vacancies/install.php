<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

$db = Database::getInstance();

$db->exec("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "vacancies (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        department VARCHAR(150),
        location VARCHAR(150),
        employment_type VARCHAR(100),
        description TEXT,
        requirements TEXT,
        salary_range VARCHAR(100),
        contact_email VARCHAR(150),
        status ENUM('open','closed') DEFAULT 'open',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
    CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "vacancy_applications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        vacancy_id INT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(50),
        motivation TEXT,
        cv_filename VARCHAR(255),
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
