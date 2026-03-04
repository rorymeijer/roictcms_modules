<?php
/**
 * Site Statistieken – install.php
 * Maakt databasetabellen aan en stelt standaardinstellingen in.
 */

$db = Database::getInstance();

// ── Tabel: Paginaweergaven ────────────────────────────────────────────────────
if (!$db->tableExists(DB_PREFIX . 'stats_pageviews')) {
    $db->query("CREATE TABLE `" . DB_PREFIX . "stats_pageviews` (
        `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `visitor_hash`    VARCHAR(64)     NOT NULL,
        `session_hash`    VARCHAR(64)     NOT NULL,
        `url`             VARCHAR(512)    NOT NULL,
        `page_title`      VARCHAR(255)    NOT NULL DEFAULT '',
        `referrer_url`    VARCHAR(512)    NOT NULL DEFAULT '',
        `referrer_type`   ENUM('direct','search','social','referral','internal') NOT NULL DEFAULT 'direct',
        `referrer_source` VARCHAR(150)    NOT NULL DEFAULT '',
        `search_keyword`  VARCHAR(255)    DEFAULT NULL,
        `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_session` (`session_hash`),
        INDEX `idx_visitor` (`visitor_hash`),
        INDEX `idx_created` (`created_at`),
        INDEX `idx_url`     (`url`(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Tabel: Sessies ────────────────────────────────────────────────────────────
if (!$db->tableExists(DB_PREFIX . 'stats_sessions')) {
    $db->query("CREATE TABLE `" . DB_PREFIX . "stats_sessions` (
        `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `session_hash`      VARCHAR(64)     NOT NULL,
        `visitor_hash`      VARCHAR(64)     NOT NULL,
        `ip_address`        VARCHAR(45)     NOT NULL DEFAULT '',
        `device_type`       ENUM('desktop','tablet','mobile','bot','unknown') NOT NULL DEFAULT 'unknown',
        `browser`           VARCHAR(100)    NOT NULL DEFAULT '',
        `os`                VARCHAR(100)    NOT NULL DEFAULT '',
        `screen_resolution` VARCHAR(20)     NOT NULL DEFAULT '',
        `language`          VARCHAR(10)     NOT NULL DEFAULT '',
        `is_new_visitor`    TINYINT(1)      NOT NULL DEFAULT 1,
        `pages_count`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        `duration`          INT UNSIGNED    NOT NULL DEFAULT 0,
        `is_bounce`         TINYINT(1)      NOT NULL DEFAULT 1,
        `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_activity`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_session`   (`session_hash`),
        INDEX `idx_visitor`       (`visitor_hash`),
        INDEX `idx_created`       (`created_at`),
        INDEX `idx_last_activity` (`last_activity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Tabel: AVG-verwijderingslog ───────────────────────────────────────────────
if (!$db->tableExists(DB_PREFIX . 'stats_deletions')) {
    $db->query("CREATE TABLE `" . DB_PREFIX . "stats_deletions` (
        `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
        `deleted_by`      VARCHAR(100)    NOT NULL DEFAULT '',
        `criteria`        VARCHAR(512)    NOT NULL DEFAULT '',
        `records_deleted` INT UNSIGNED    NOT NULL DEFAULT 0,
        `deleted_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Standaardinstellingen ─────────────────────────────────────────────────────
Settings::setMultiple([
    'stats_enabled'          => '1',
    'stats_anonymize_ip'     => '1',
    'stats_respect_dnt'      => '1',
    'stats_retention_days'   => '395',
    'stats_exclude_admin'    => '1',
    'stats_exclude_bots'     => '1',
    'stats_exclude_ips'      => '',
    'stats_hash_salt'        => bin2hex(random_bytes(16)),
    'stats_session_timeout'  => '1800',
    'stats_require_consent'  => '0',
]);

return ['success' => true, 'message' => 'Site Statistieken succesvol geïnstalleerd.'];
