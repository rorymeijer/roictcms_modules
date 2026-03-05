<?php
$db = Database::getInstance();

// ── Maak tabellen aan (nieuwe installaties) ────────────────────────────────────
$db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "newsletter_subscribers` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`         VARCHAR(255) NOT NULL UNIQUE,
    `name`          VARCHAR(100) DEFAULT NULL,
    `status`        ENUM('pending','active','unsubscribed') NOT NULL DEFAULT 'pending',
    `token`         VARCHAR(64) NOT NULL,
    `confirm_token` VARCHAR(64) DEFAULT NULL,
    `subscribed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "newsletter_campaigns` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject`        VARCHAR(255) NOT NULL,
    `body`           LONGTEXT NOT NULL,
    `is_html`        TINYINT(1) NOT NULL DEFAULT 0,
    `sent_at`        DATETIME DEFAULT NULL,
    `recipient_count` INT NOT NULL DEFAULT 0,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ── Migraties voor bestaande installaties ─────────────────────────────────────
try { $db->query("ALTER TABLE `" . DB_PREFIX . "newsletter_subscribers`
    MODIFY COLUMN `status` ENUM('pending','active','unsubscribed') NOT NULL DEFAULT 'pending'");
} catch (Exception $e) {}

try { $db->query("ALTER TABLE `" . DB_PREFIX . "newsletter_subscribers`
    ADD COLUMN `confirm_token` VARCHAR(64) DEFAULT NULL");
} catch (Exception $e) {}

try { $db->query("ALTER TABLE `" . DB_PREFIX . "newsletter_campaigns`
    ADD COLUMN `is_html` TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) {}

// ── Standaardinstellingen ──────────────────────────────────────────────────────
Settings::set('newsletter_from_name',  Settings::get('site_name', 'Website'));
Settings::set('newsletter_from_email', Settings::get('admin_email', ''));
if (!Settings::get('newsletter_html_template', '')) {
    $siteName = Settings::get('site_name', 'Uw Website');
    Settings::set('newsletter_html_template', '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 0"><tr><td><table width="600" cellpadding="0" cellspacing="0" align="center" style="background:#ffffff;border-radius:8px;overflow:hidden;font-family:Arial,sans-serif"><tr><td style="background:#2563eb;padding:30px 40px"><h1 style="color:#ffffff;margin:0;font-size:24px">' . htmlspecialchars($siteName) . '</h1></td></tr><tr><td style="padding:40px"><p style="font-size:16px;color:#333;line-height:1.6">Beste abonnee,</p><p style="font-size:16px;color:#333;line-height:1.6">Schrijf hier uw bericht...</p><p style="font-size:16px;color:#333;line-height:1.6">Met vriendelijke groet,<br><strong>' . htmlspecialchars($siteName) . '</strong></p></td></tr></table></td></tr></table>');
}
