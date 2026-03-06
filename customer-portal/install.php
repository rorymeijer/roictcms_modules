<?php
/**
 * Customer Portal – install.php
 * Wordt eenmalig uitgevoerd bij installatie van de module.
 */

$db = Database::getInstance();
$p  = DB_PREFIX;

/* ---------------------------------------------------------------
 * Klanten
 * Koppeling via fl_user_id (frontend-login) OF e-mail.
 * --------------------------------------------------------------- */
$db->query("
    CREATE TABLE IF NOT EXISTS `{$p}cp_customers` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `fl_user_id`    INT DEFAULT NULL,
        `company_name`  VARCHAR(255) DEFAULT NULL,
        `contact_name`  VARCHAR(255) NOT NULL,
        `email`         VARCHAR(255) NOT NULL,
        `phone`         VARCHAR(50)  DEFAULT NULL,
        `address`       VARCHAR(255) DEFAULT NULL,
        `postcode`      VARCHAR(20)  DEFAULT NULL,
        `city`          VARCHAR(100) DEFAULT NULL,
        `country`       VARCHAR(100) DEFAULT 'Nederland',
        `kvk`           VARCHAR(50)  DEFAULT NULL,
        `btw`           VARCHAR(50)  DEFAULT NULL,
        `notes`         TEXT         DEFAULT NULL,
        `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ---------------------------------------------------------------
 * Offertes
 * --------------------------------------------------------------- */
$db->query("
    CREATE TABLE IF NOT EXISTS `{$p}cp_quotes` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `quote_number`  VARCHAR(50) NOT NULL,
        `customer_id`   INT NOT NULL,
        `title`         VARCHAR(255) NOT NULL,
        `intro`         TEXT DEFAULT NULL,
        `footer`        TEXT DEFAULT NULL,
        `subtotal`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `discount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `tax_rate`      DECIMAL(5,2)  NOT NULL DEFAULT 21.00,
        `tax_amount`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `total`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `status`        ENUM('concept','sent','accepted','rejected','expired') NOT NULL DEFAULT 'concept',
        `valid_until`   DATE DEFAULT NULL,
        `sent_at`       DATETIME DEFAULT NULL,
        `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_quote_number` (`quote_number`),
        KEY `fk_quote_customer` (`customer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ---------------------------------------------------------------
 * Offerte regels
 * --------------------------------------------------------------- */
$db->query("
    CREATE TABLE IF NOT EXISTS `{$p}cp_quote_items` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `quote_id`      INT NOT NULL,
        `description`   VARCHAR(500) NOT NULL,
        `quantity`      DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        `unit_price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `line_total`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `sort_order`    INT NOT NULL DEFAULT 0,
        KEY `fk_qi_quote` (`quote_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ---------------------------------------------------------------
 * Facturen
 * --------------------------------------------------------------- */
$db->query("
    CREATE TABLE IF NOT EXISTS `{$p}cp_invoices` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_number` VARCHAR(50) NOT NULL,
        `quote_id`      INT DEFAULT NULL,
        `customer_id`   INT NOT NULL,
        `title`         VARCHAR(255) NOT NULL,
        `intro`         TEXT DEFAULT NULL,
        `footer`        TEXT DEFAULT NULL,
        `subtotal`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `discount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `tax_rate`      DECIMAL(5,2)  NOT NULL DEFAULT 21.00,
        `tax_amount`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `total`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `status`        ENUM('concept','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'concept',
        `invoice_date`  DATE DEFAULT NULL,
        `due_date`      DATE DEFAULT NULL,
        `paid_at`       DATETIME DEFAULT NULL,
        `sent_at`       DATETIME DEFAULT NULL,
        `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_invoice_number` (`invoice_number`),
        KEY `fk_invoice_customer` (`customer_id`),
        KEY `fk_invoice_quote` (`quote_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ---------------------------------------------------------------
 * Factuur regels
 * --------------------------------------------------------------- */
$db->query("
    CREATE TABLE IF NOT EXISTS `{$p}cp_invoice_items` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_id`    INT NOT NULL,
        `description`   VARCHAR(500) NOT NULL,
        `quantity`      DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        `unit_price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `line_total`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `sort_order`    INT NOT NULL DEFAULT 0,
        KEY `fk_ii_invoice` (`invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ---------------------------------------------------------------
 * Standaard instellingen
 * --------------------------------------------------------------- */
$settings = Settings::getInstance();
$defaults = [
    'cp_portal_slug'      => 'klanten-portaal',
    'cp_company_name'     => '',
    'cp_company_address'  => '',
    'cp_company_postcode' => '',
    'cp_company_city'     => '',
    'cp_company_kvk'      => '',
    'cp_company_btw'      => '',
    'cp_company_iban'     => '',
    'cp_invoice_prefix'   => 'F',
    'cp_invoice_counter'  => '1',
    'cp_quote_prefix'     => 'O',
    'cp_quote_counter'    => '1',
    'cp_payment_days'     => '14',
    'cp_quote_valid_days' => '30',
    'cp_tax_rate'         => '21',
    'cp_portal_enabled'   => '1',
];

foreach ($defaults as $key => $value) {
    if ($settings->get($key) === null) {
        $settings->set($key, $value);
    }
}
