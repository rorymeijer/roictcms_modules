<?php
/**
 * Contact Form Module — Install Script
 * Wordt éénmalig uitgevoerd bij installatie van de module.
 */

$db = Database::getInstance();

$db->getPdo()->exec("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "contact_messages` (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(150) NOT NULL,
        email       VARCHAR(150) NOT NULL,
        subject     VARCHAR(255) NOT NULL,
        message     TEXT NOT NULL,
        ip_address  VARCHAR(45),
        user_agent  VARCHAR(255),
        status      ENUM('unread','read','spam','archived') DEFAULT 'unread',
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Default module settings
$settings = [
    'contact_form_email'        => Settings::get('site_email', ''),
    'contact_form_subject'      => 'Nieuw contactbericht via {site_name}',
    'contact_form_honeypot'     => '1',
    'contact_form_ratelimit'    => '5',
    'contact_form_success_msg'  => 'Bedankt voor uw bericht! We nemen zo snel mogelijk contact met u op.',
];
foreach ($settings as $k => $v) {
    if (!Settings::get($k)) {
        Settings::set($k, $v);
    }
}

return ['success' => true, 'message' => 'Contact Form geïnstalleerd.'];
