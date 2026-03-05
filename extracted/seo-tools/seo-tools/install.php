<?php
$db = Database::getInstance();

$db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "seo_meta` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `object_type` VARCHAR(50) NOT NULL DEFAULT 'page',
    `object_id` INT UNSIGNED NOT NULL,
    `meta_title` VARCHAR(255) DEFAULT NULL,
    `meta_description` TEXT DEFAULT NULL,
    `meta_keywords` VARCHAR(255) DEFAULT NULL,
    `og_title` VARCHAR(255) DEFAULT NULL,
    `og_description` TEXT DEFAULT NULL,
    `og_image` VARCHAR(500) DEFAULT NULL,
    `canonical_url` VARCHAR(500) DEFAULT NULL,
    `no_index` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `object` (`object_type`, `object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

Settings::set('seo_google_verify', '');
Settings::set('seo_robots_txt', "User-agent: *\nAllow: /\nDisallow: /admin/");
Settings::set('seo_sitemap_enabled', '1');
